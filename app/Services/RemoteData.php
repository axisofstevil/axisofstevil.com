<?php

namespace App\Services;

use App\IssueLayout;
use App\WorkBody;
use App\WorkLayout;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Michelf\MarkdownExtra;
use RuntimeException;
use TightenCo\Jigsaw\Jigsaw;

/**
 * The channel: its works (entries), divisions (categories), weekly issues
 * (front pages) and pages (title and summary), read once before the build.
 *
 * The works are required: a missing URL, an error, something other than
 * JSON, or no works fails the build, so a broken feed never deploys an empty
 * site.
 */
class RemoteData
{
    /**
     * The organisation's pages the site publishes (by path), in the order its index lists them.
     */
    private const ORGANISATION = ['mission', 'history', 'shirts', 'advertising', 'authenticity', 'contact'];

    private const ORGANISATION_LABELS = ['mission' => 'Mission', 'history' => 'History', 'shirts' => 'Shirts', 'advertising' => 'Advertising', 'authenticity' => 'Authenticity', 'contact' => 'Contact'];

    private Client $api;

    /**
     * Each Flash movie's still, by its address (set by pages()), for the issues' teasers too.
     *
     * @var array<string, string>
     */
    private array $stills = [];

    public function __construct()
    {
        $this->api = new Client;
    }

    public function handle(Jigsaw $jigsaw): void
    {
        $channelUrl = $jigsaw->getConfig('channel_url');

        if (! is_string($channelUrl) || trim($channelUrl) === '') {
            throw new RuntimeException('CHANNEL_URL is not set: not building a site without its works.');
        }

        $channelUrl = rtrim($channelUrl, '/');
        $zone = $jigsaw->getConfig('timezone');

        $entries = $this->all($channelUrl.'/entries?per_page=100');
        $works = array_map(fn (array $e) => $this->work($e, $zone), $entries);

        if ($works === []) {
            throw new RuntimeException("The channel ({$channelUrl}) returned no works: not building a site without them.");
        }

        usort($works, fn ($a, $b) => strcmp($b['date'], $a['date']));

        // The weekly issues (front pages), each with its blocks in order, newest first.
        $frontPages = $this->json($channelUrl.'/front-pages?include=items')['data'] ?? [];
        usort($frontPages, fn ($a, $b) => strcmp($b['starts_on'], $a['starts_on']));

        $issues = array_map(fn (array $f) => [
            'slug' => $f['slug'],
            'title' => $f['title'],
            'starts_on' => $f['starts_on'],
            'ends_on' => $f['ends_on'],
            'address' => "/issues/{$f['slug']}/",
        ], $frontPages);

        $workPages = $this->pages($entries, $works, $this->ranIn($frontPages));
        $collections = $jigsaw->getConfig('collections');
        $collections->set('works.items', $workPages);
        $collections->set('issue_pages.items', $this->issuePages($frontPages, $workPages));

        $divisions = array_map(fn (array $c) => [
            'slug' => $c['slug'],
            'name' => $c['name'],
            'count' => $c['entries'],
        ], $this->json($channelUrl.'/categories')['data'] ?? []);

        $divisions = array_map(fn (array $d) => [...$d, 'series' => $this->series($d['slug'], $workPages)], $divisions);

        // Each series' banner and introduction (Markdown), as written for it.
        $introductions = array_column($this->all($channelUrl.'/series'), null, 'slug');

        $collections->set('divisions.items', $this->divisionPages($divisions, $workPages, count($works), $introductions));
        $jigsaw->setConfig('collections', $collections);

        $pages = [];
        $organisation = [];
        $timeline = $this->json($channelUrl.'/timeline')['data'] ?? [];

        foreach ($this->json($channelUrl.'/pages')['data'] ?? [] as $p) {
            $pages[$p['slug']] = ['title' => $p['title'], 'summary' => $p['summary'] ?? null, 'path' => $p['path']];

            // The organisation's own pages, each at its path (a page is known by its path, not its slug);
            // History carries the chronology.
            $path = trim((string) $p['path'], '/');

            if (in_array($path, self::ORGANISATION, true)) {
                $organisation[] = [
                    'filename' => $path,
                    'address' => '/'.$path.'/',
                    'title' => $p['title'],
                    'slug' => $path,
                    'label' => self::ORGANISATION_LABELS[$path],
                    'description' => $p['summary'] ?? null,
                    'summary' => $p['summary'] ?? null,
                    'image' => $p['image'] ?? null,
                    'original_url' => $p['original_url'] ?? null,
                    'timeline' => $path === 'history' ? array_map(fn (array $t) => ['year' => $t['year'], 'label' => $t['label'], 'description' => $t['description']], $timeline) : [],
                    // A page's lead image leads it; it isn't the picture of a Flash movie in its body (a work's is).
                    'content' => WorkBody::clean((string) ($p['body'] ?? ''), null, $p['title']),
                ];
            }
        }

        usort($organisation, fn ($a, $b) => array_search($a['slug'], self::ORGANISATION, true) <=> array_search($b['slug'], self::ORGANISATION, true));
        $collections = $jigsaw->getConfig('collections');
        $collections->set('organisation.items', $organisation);
        $jigsaw->setConfig('collections', $collections);
        $jigsaw->setConfig('organisation', array_map(fn ($o) => ['label' => $o['label'], 'address' => $o['address']], $organisation));

        // The old site's addresses, forwarded by the build (App\Services\Redirects).
        $jigsaw->setConfig('redirects', array_map(fn (array $r) => ['from' => $r['from'], 'to' => $r['to']], $this->json($channelUrl.'/redirects')['data'] ?? []));

        $jigsaw->setConfig('pages', $pages);
        $jigsaw->setConfig('works', $works);
        $jigsaw->setConfig('issues', $issues);
        $jigsaw->setConfig('divisions', $divisions);
        $jigsaw->setConfig('build_time', CarbonImmutable::now($zone));
    }

    /**
     * The issue that first carried each work (a teaser or banner for it, the
     * week it was new; re-features don't count), by the work's slug.
     *
     * @param  list<array<string, mixed>>  $frontPages  newest first
     * @return array<string, array{title: string, address: string, starts_on: string}>
     */
    private function ranIn(array $frontPages): array
    {
        $ranIn = [];

        foreach (array_reverse($frontPages) as $f) {
            foreach ($f['items'] ?? [] as $item) {
                if (($item['type'] ?? null) === 'entry' && in_array($item['role'] ?? null, ['teaser', 'banner'], true)) {
                    $ranIn[$item['entry']['slug']] ??= ['title' => $f['title'], 'address' => "/issues/{$f['slug']}/", 'starts_on' => $f['starts_on']];
                }
            }
        }

        return $ranIn;
    }

    /**
     * Each weekly issue's page at /issues/{slug}/: its number (1 is the
     * first), its run, and its blocks in the order the week ran them, each
     * ready for the page; then its earlier and later issues.
     *
     * @param  list<array<string, mixed>>  $frontPages  newest first
     * @param  list<array<string, mixed>>  $workPages
     * @return list<array<string, mixed>>
     */
    private function issuePages(array $frontPages, array $workPages): array
    {
        $bySlug = array_column($workPages, null, 'slug');
        $oldestFirst = array_reverse($frontPages);
        $pick = fn (?array $f) => $f === null ? null : ['title' => $f['title'], 'address' => "/issues/{$f['slug']}/", 'starts_on' => $f['starts_on'], 'ends_on' => $f['ends_on']];
        $items = [];

        foreach ($oldestFirst as $i => $f) {
            $blocks = self::shownOnce(array_values(array_filter(array_map(fn (array $item) => $this->block($item, $bySlug), $f['items'] ?? []))));

            $items[] = [
                'filename' => $f['slug'],
                'address' => "/issues/{$f['slug']}/",
                'name' => $f['title'],
                'title' => $f['title'].' — Issue No. '.($i + 1).' — The Axis of Stevil',
                'starts_on' => $f['starts_on'],
                'ends_on' => $f['ends_on'],
                'number' => $i + 1,
                'of' => count($oldestFirst),
                'description' => 'Issue No. '.($i + 1).' of the Axis of Stevil, the week of '.CarbonImmutable::parse($f['starts_on'])->format('F j, Y').'.',
                'blocks' => $blocks,
                // The week composed as a front page: ranked, in rows that end together.
                'layout' => IssueLayout::compose($blocks, WorkLayout::knownSizes()),
                'works' => count(array_filter($blocks, fn ($b) => isset($b['address']) && ! $b['refeature'])),
                'earlier' => $pick($oldestFirst[$i - 1] ?? null),
                'later' => $pick($oldestFirst[$i + 1] ?? null),
            ];
        }

        return $items;
    }

    /**
     * One block of an issue, described by what it is, so the page can set
     * each where a front page would: a story (a publication or a letter, an
     * excerpt ending in a jump line to the rest, or whole), a strip for the
     * Funnies, a plate (the week's gags, art and Flash, whole, captioned), a
     * notice (a few words from the offices), a letter to Ask Stevil (whole),
     * the week's Language entry (whole) or Featured Steve (its portrait and
     * opening line), the Tune of the Week, or Back Issues. A block for a work
     * no longer on file is left out.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, array<string, mixed>>  $works  by slug
     * @return array<string, mixed>|null
     */
    private function block(array $item, array $works): ?array
    {
        $markdown = fn (string $text, ?string $image, string $title) => trim(MarkdownExtra::defaultTransform(WorkBody::clean($text, $image, $title, $this->stills)));
        // Words a reader reads (not picture references, pictures or markup), and the pictures and movies shown.
        $words = fn (string $md) => preg_match_all("/[\pL\pN']+/u", (string) preg_replace(['/^\[[^\]]+\]:.*$/m', '/!\[[^\]]*\](?:\[[^\]]*\]|\([^)]*\))|!#flash\([^)]*\)|<[^>]+>|\([^)]*\)/'], '', $md));
        $pictures = fn (string $md) => preg_match_all('/!\[|!#flash|<img|<embed/i', $md);

        switch ($item['type'] ?? null) {
            case 'entry':
                $w = $works[$item['entry']['slug']] ?? null;

                if ($w === null) {
                    return null;
                }

                $text = trim((string) ($item['teaser'] ?? ''));
                $body = (string) ($w['content'] ?? '');
                $role = $item['role'] ?? 'teaser';
                $block = [
                    'section' => $w['division'],
                    'date' => $w['date'],
                    'kicker' => $item['heading'] ?? null,
                    'title' => $w['title'],
                    'address' => $w['address'],
                    'division' => $w['division_name'],
                    'place' => $w['place'],
                    'series' => $w['series'],
                    'refeature' => $role === 'refeature',
                    'image' => $w['image'],
                    'html' => $text === '' ? '' : $markdown($text, $w['image'], $w['title']),
                    // An excerpt (the rest is on the work's page): fewer than nine in ten of its words.
                    'whole' => $words($text) >= $words($body) * 0.9,
                    'shows_picture' => $pictures($text) > 0,
                ];

                $kinded = match (true) {
                    $w['division'] === 'language' => [...$block, 'kind' => 'language', 'html' => $markdown($body, null, $w['title']), 'whole' => true],
                    $w['division'] === 'featured-steves' => [...$block, 'kind' => 'steve', 'html' => $w['excerpt'] !== null ? $markdown($w['excerpt'], null, $w['title']) : $this->opening($body)],
                    // A strip is the comic itself, whole: its pictures or movie, never a teaser's words.
                    $w['division'] === 'comics' => [...$block, 'kind' => 'strip', 'html' => $markdown($body, $w['image'], $w['title']), 'shows_picture' => $pictures($body) > 0],
                    $w['division'] === 'ask-stevil' => [...$block, 'kind' => 'letter'],
                    // A story's words alone (any pictures they carried dropped); the work's own picture goes beside them.
                    in_array($w['division'], ['publications', 'letters'], true) => [...$block, 'kind' => 'story', 'letter' => $w['division'] === 'letters', 'html' => self::wordsOnly($block['html']), 'shows_picture' => false],
                    // The week's gags, art and movies: a picture, whole. A few words and nothing else: a notice.
                    $pictures($text) > 0 || ($text === '' && $w['image'] !== null) => [...$block, 'kind' => 'plate'],
                    default => [...$block, 'kind' => 'notice'],
                };

                // How much reading it is, for the page's composition (App\IssueLayout).
                return [...$kinded, 'words' => str_word_count(strip_tags((string) $kinded['html']))];

            case 'featured_track':
                return [
                    'kind' => 'tune',
                    'artist' => $item['artist']['name'],
                    'image' => $item['artist']['image'] ?? null,
                    'track' => $item['track']['title'] ?? null,
                    'html' => $markdown((string) ($item['track']['bio'] ?? ''), null, $item['artist']['name']),
                ];

            case 'back_issues':
                return [
                    'kind' => 'archive',
                    'entries' => array_values(array_filter(array_map(fn (array $e) => isset($works[$e['slug']]) ? [
                        'title' => $works[$e['slug']]['title'],
                        'address' => $works[$e['slug']]['address'],
                        'date' => $works[$e['slug']]['date'],
                        'image' => $works[$e['slug']]['image'],
                        'division' => $works[$e['slug']]['division_name'],
                    ] : null, $item['entries'] ?? []))),
                ];
        }

        return null;
    }

    /**
     * A week's pictures and movies, each shown once: a strip or plate whose
     * every picture and movie an earlier block already shows (the same Carlton
     * movie as a comic and as Flash) is left out.
     *
     * @param  list<array<string, mixed>>  $blocks  in the order the week ran them
     * @return list<array<string, mixed>>
     */
    private static function shownOnce(array $blocks): array
    {
        $seen = [];
        $kept = [];

        foreach ($blocks as $block) {
            preg_match_all('/(?:src|data-swf)="([^"]+)"/', (string) ($block['html'] ?? ''), $m);
            $media = array_unique([...$m[1], ...(in_array($block['kind'], ['strip', 'plate'], true) && ! ($block['shows_picture'] ?? false) && ($block['image'] ?? null) ? [$block['image']] : [])]);

            if (in_array($block['kind'], ['strip', 'plate'], true) && $media !== [] && array_diff($media, $seen) === []) {
                continue;
            }

            array_push($seen, ...$media);
            $kept[] = $block;
        }

        return $kept;
    }

    /**
     * A story's words without the pictures and movies its teaser carried.
     */
    private static function wordsOnly(string $html): string
    {
        $html = (string) preg_replace(['#<figure\b.*?</figure>#s', '#<img\b[^>]*>#', '#<p>\s*(?:<a\b[^>]*>\s*</a>\s*)*</p>#'], '', $html);

        return trim($html);
    }

    /**
     * A piece's opening line, for a box that points to it: its first paragraph
     * of words, cut at a sentence near thirty words.
     */
    private function opening(string $markdown): string
    {
        foreach (preg_split('/\n\s*\n/', $markdown) ?: [] as $paragraph) {
            $paragraph = trim((string) preg_replace(['/^\[[^\]]+\]:.*$/m', '/!\[[^\]]*\](?:\[[^\]]*\]|\([^)]*\))/', '/^#+\s*|\s*#+$/m', '/^>\s?/m'], '', $paragraph));
            $plain = trim(strip_tags(MarkdownExtra::defaultTransform($paragraph)));

            if (str_word_count($plain) >= 8) {
                $cut = preg_match('/^(.{80,220}?[.!?])(\s|$)/u', $plain, $m) === 1 ? $m[1] : (mb_strlen($plain) > 220 ? rtrim(mb_substr($plain, 0, 200)).'…' : $plain);

                return '<p>'.e($cut).'</p>';
            }
        }

        return '';
    }

    /**
     * A work as the views use it: its address on the site, its date in the
     * publication's time zone, and its division.
     *
     * @param  array<string, mixed>  $e
     * @return array<string, mixed>
     */
    private function work(array $e, string $zone): array
    {
        return [
            'slug' => $e['slug'],
            'title' => $e['title'],
            'address' => (string) parse_url((string) $e['url'], PHP_URL_PATH),
            'date' => CarbonImmutable::parse($e['published_at'])->setTimezone($zone)->format('Y-m-d'),
            'division' => $e['category']['slug'] ?? null,
            'division_name' => $e['category']['name'] ?? null,
            'series' => $e['series']['name'] ?? null,
            'series_slug' => $e['series']['slug'] ?? null,
            'image' => $e['image'] ?? null,
            'rating' => $e['rating'] ?? null,
            'credits' => array_map(fn ($c) => $c['name'], $e['credits'] ?? []),
        ];
    }

    /**
     * Each work's own page: its body as Markdown, its credits by role, its
     * place in its division (1 is the earliest) and its neighbours there.
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  list<array<string, mixed>>  $works  newest first
     * @return list<array<string, mixed>>
     */
    private function pages(array $entries, array $works, array $ranIn = []): array
    {
        $bySlug = array_column($entries, null, 'slug');
        $byDivision = [];

        // Each Flash movie's own still: the lead image of a work whose body is that one movie and a few words.
        $stills = [];

        foreach ($entries as $e) {
            $body = (string) ($e['body'] ?? '');

            if (($e['image'] ?? null) !== null && preg_match_all('/!#flash\(([^)\s]+)\)/', $body, $movies) === 1 && ! str_contains($body, (string) $e['image']) && str_word_count(strip_tags($body)) <= 60) {
                $stills[$movies[1][0]] ??= $e['image'];
            }
        }

        // Else the still the archive keeps beside a movie ({name}.thumb.png), where there is one.
        $stills = [...$this->archivedStills($entries, $stills), ...$stills];
        $this->stills = $stills;

        foreach (array_reverse($works) as $work) {
            $byDivision[$work['division']][] = $work;
        }

        $pages = [];

        foreach ($byDivision as $division => $list) {
            foreach ($list as $i => $work) {
                $e = $bySlug[$work['slug']];
                $pick = fn (?array $w) => $w === null ? null : ['title' => $w['title'], 'address' => $w['address'], 'image' => $w['image'], 'date' => $w['date']];

                $pages[] = [
                    ...$work,
                    'filename' => $work['slug'],
                    'description' => $e['summary'] ?? $e['teaser'] ?? null,
                    // Where the work first ran, on the site before this one.
                    'original_url' => $e['original_url'] ?? null,
                    // The excerpt written for it (an issue shows it where it points to the piece).
                    'excerpt' => ($e['teaser'] ?? null) ?: null,
                    'keywords' => array_values(array_filter(array_map('trim', explode(',', (string) ($e['meta_keywords'] ?? ''))))),
                    'roles' => array_map(fn ($c) => ['name' => $c['name'], 'roles' => $c['roles'] ?? []], $e['credits'] ?? []),
                    'place' => $i + 1,
                    'of' => count($list),
                    // The weekly issue that first carried it, if one did.
                    'ran_in' => $ranIn[$work['slug']] ?? null,
                    'older' => $pick($list[$i - 1] ?? null),
                    'newer' => $pick($list[$i + 1] ?? null),
                    'content' => WorkBody::clean((string) ($e['body'] ?? ''), $e['image'] ?? null, $e['title'], $stills),
                ];
            }
        }

        return $pages;
    }

    /**
     * A division's series, most works first, each at /{division}/{series}/.
     *
     * @param  list<array<string, mixed>>  $workPages
     * @return list<array{name: string, slug: string, count: int, address: string}>
     */
    private function series(string $division, array $workPages): array
    {
        $series = [];

        foreach ($workPages as $w) {
            if ($w['division'] === $division && $w['series_slug'] !== null) {
                $series[$w['series_slug']] ??= ['name' => $w['series'], 'slug' => $w['series_slug'], 'count' => 0, 'address' => "/{$division}/{$w['series_slug']}/"];
                $series[$w['series_slug']]['count']++;
            }
        }

        usort($series, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return $series;
    }

    /**
     * Each division's page: its share of the output, its series, and its
     * works (oldest first, labelled by their place numbers) for the index and
     * the yearbook; then each series' page beneath its division, the same
     * listing for its works; then All Works, every work labelled by its
     * division.
     *
     * @param  list<array<string, mixed>>  $divisions
     * @param  list<array<string, mixed>>  $workPages
     * @param  array<string, array<string, mixed>>  $introductions  each series' banner and description, by slug
     * @return list<array<string, mixed>>
     */
    private function divisionPages(array $divisions, array $workPages, int $total, array $introductions = []): array
    {
        $items = [];

        foreach ($divisions as $division) {
            $works = array_values(array_filter($workPages, fn ($w) => $w['division'] === $division['slug']));
            usort($works, fn ($a, $b) => $a['place'] <=> $b['place']);

            $items[] = [
                'filename' => $division['slug'],
                'address' => "/{$division['slug']}/",
                'title' => $division['name'].' — The Axis of Stevil',
                'name' => $division['name'],
                'slug' => $division['slug'],
                'description' => "Every work filed in {$division['name']}, the Axis of Stevil's division of ".count($works).' works.',
                'count' => count($works),
                'figure' => (count($works) === 1 ? 'work' : 'works').' · '.($total > 0 ? (int) round(count($works) / $total * 100) : 0).'% of output',
                'note_label' => 'Series',
                'note' => array_column($division['series'], 'count', 'name'),
                'note_links' => array_column($division['series'], 'address', 'name'),
                'works' => array_map(fn ($w) => [...$this->listed($w), 'label' => 'Plate '.$w['place']], $works),
            ];

            foreach ($division['series'] as $series) {
                $inSeries = array_values(array_filter($works, fn ($w) => $w['series_slug'] === $series['slug']));

                $items[] = [
                    'filename' => $division['slug'].'--'.$series['slug'],
                    'address' => $series['address'],
                    'title' => $series['name'].' — '.$division['name'].' — The Axis of Stevil',
                    'name' => $series['name'],
                    'slug' => $series['slug'],
                    'parent' => $division['slug'],
                    'description' => "Every work in {$series['name']}, a series of {$series['count']} in the Axis of Stevil's {$division['name']} division.",
                    'count' => $series['count'],
                    'figure' => ($series['count'] === 1 ? 'work' : 'works').' · '.(count($works) > 0 ? (int) round($series['count'] / count($works) * 100) : 0).'% of '.$division['name'],
                    'note_label' => null,
                    'note' => [],
                    'banner' => $introductions[$series['slug']]['banner'] ?? null,
                    'introduction' => ($text = $introductions[$series['slug']]['description'] ?? null) === null ? null : MarkdownExtra::defaultTransform($text),
                    'works' => array_map(fn ($w) => [...$this->listed($w), 'label' => 'Plate '.$w['place']], $inSeries),
                ];
            }
        }

        $all = $workPages;
        usort($all, fn ($a, $b) => [$a['date'], $a['place']] <=> [$b['date'], $b['place']]);

        $items[] = [
            'filename' => 'works',
            'address' => '/works/',
            'title' => 'All Works — The Axis of Stevil',
            'name' => 'All Works',
            'slug' => 'works',
            'description' => 'Every work filed by the Axis of Stevil since 2005: '.count($all).' works in '.count($divisions).' divisions.',
            'count' => count($all),
            'figure' => 'works · '.count($divisions).' divisions',
            'note_label' => 'Divisions',
            'note' => array_column($divisions, 'count', 'name'),
            'note_links' => array_map(fn ($slug) => "/{$slug}/", array_column($divisions, 'slug', 'name')),
            'works' => array_map(fn ($w) => [...$this->listed($w), 'label' => $w['division_name']], $all),
        ];

        return $items;
    }

    /**
     * The movies with no still from a work, and the stills the archive keeps beside them
     * ({name}.thumb.png). Each answer is remembered between builds (.cache/stills.json).
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, string>  $known
     * @return array<string, string>
     */
    private function archivedStills(array $entries, array $known): array
    {
        $path = dirname(__DIR__, 2).'/.cache/stills.json';
        $cache = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $found = [];

        foreach ($entries as $e) {
            preg_match_all('/!#flash\(([^)\s]+)\)/', (string) ($e['body'] ?? ''), $movies);

            foreach ($movies[1] as $movie) {
                if (isset($known[$movie]) || ! str_ends_with(strtolower($movie), '.swf')) {
                    continue;
                }

                $still = substr($movie, 0, -4).'.thumb.png';

                if (! array_key_exists($still, $cache)) {
                    try {
                        $cache[$still] = $this->api->head($still, ['http_errors' => false])->getStatusCode() === 200;
                    } catch (\Throwable) {
                        $cache[$still] = false;
                    }
                }

                if ($cache[$still]) {
                    $found[$movie] = $still;
                }
            }
        }

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $found;
    }

    /**
     * A work as a division page lists it.
     *
     * @param  array<string, mixed>  $w
     * @return array<string, mixed>
     */
    private function listed(array $w): array
    {
        return [
            'title' => $w['title'],
            'address' => $w['address'],
            'date' => $w['date'],
            'image' => $w['image'],
            'place' => $w['place'],
        ];
    }

    /**
     * Every row of a paged listing, following its next links.
     *
     * @return list<array<string, mixed>>
     */
    private function all(string $url): array
    {
        $rows = [];

        while ($url) {
            $page = $this->json($url);
            array_push($rows, ...($page['data'] ?? []));
            $url = $page['links']['next'] ?? null;
        }

        return $rows;
    }

    /**
     * Guzzle already throws on an error status.
     *
     * @return array<string, mixed>
     */
    private function json(string $url): array
    {
        $body = json_decode((string) $this->api->get($url, ['headers' => ['Accept' => 'application/json']])->getBody(), true);

        if (! is_array($body)) {
            throw new RuntimeException("{$url} did not return JSON.");
        }

        return $body;
    }
}
