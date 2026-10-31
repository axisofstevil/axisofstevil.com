<?php

namespace App\Services;

use App\IssueParts;
use App\WorkBody;
use App\WorkLayout;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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
     * The playlist: each Tune of the Week that has audio, oldest first.
     *
     * @var list<array<string, mixed>>
     */
    private array $tunes = [];

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

        // The Tunes of the Week: the artists with their tracks; the playlist every page carries (the tunes with
        // audio, oldest first), what an issue's tune plays, and the Tunes section's pages.
        $artists = $this->featuredArtists($channelUrl);
        $this->tunes = $this->tunes($artists);
        $jigsaw->setConfig('tunes', $this->tunes);

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
            // How its page describes itself, as written for it (search listings, link previews); none: a generated line.
            'description' => $this->plain((string) ($c['description'] ?? '')) ?: null,
            'count' => $c['entries'],
        ], $this->json($channelUrl.'/categories')['data'] ?? []);

        $divisions = array_map(fn (array $d) => [...$d, 'series' => $this->series($d['slug'], $workPages)], $divisions);

        // Each series' banner and introduction (Markdown), as written for it.
        $introductions = array_column($this->all($channelUrl.'/series'), null, 'slug');

        $collections->set('divisions.items', $this->divisionPages($divisions, $workPages, count($works), $introductions));

        // The Gallery: the channel's first gallery, its photographs and each credited photographer's listing.
        $gallery = $this->gallery($channelUrl);
        $collections->set('gallery_pages.items', $gallery === null ? [] : $this->galleryPages($gallery));
        $jigsaw->setConfig('gallery', $gallery === null ? null : [
            'name' => $gallery['name'],
            'count' => count($gallery['photos']),
            'photographers' => $gallery['photographers'],
        ]);

        // The Tunes: a yearbook of every tune, and each artist's page.
        $tunePages = $this->tunePages($artists);
        $collections->set('tune_pages.items', $tunePages);
        $jigsaw->setConfig('tunes_section', $tunePages === [] ? null : [
            'count' => $tunePages[0]['count'],
            'artists' => $tunePages[0]['artists'],
        ]);
        $jigsaw->setConfig('collections', $collections);

        $pages = [];
        $organisation = [];
        $timeline = $this->json($channelUrl.'/timeline')['data'] ?? [];

        foreach ($this->json($channelUrl.'/pages')['data'] ?? [] as $p) {
            // The page at llms.txt is the owner's opening of /llms.txt (App\Services\LlmsFile), not a page of the site.
            if (trim((string) $p['path'], '/') === 'llms.txt') {
                $jigsaw->setConfig('llms', $p['body'] ?? null);

                continue;
            }

            // The page at error is the site's error page, a file at /error.html (source/error.blade.php), not indexed:
            // its title, its summary, its lead picture and its body, rendered.
            if (trim((string) $p['path'], '/') === 'error') {
                $jigsaw->setConfig('error', [
                    'title' => $p['title'],
                    'summary' => $p['summary'] ?? null,
                    'image' => $p['image'] ?? null,
                    'content' => MarkdownExtra::defaultTransform(WorkBody::clean((string) ($p['body'] ?? ''), null, $p['title'])),
                ]);

                continue;
            }

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
                    'updated' => isset($p['updated_at']) ? substr((string) $p['updated_at'], 0, 10) : null,
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
            $blocks = array_values(array_filter(array_map(fn (array $item) => $this->block($item, $bySlug), $f['items'] ?? [])));

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
                // The week as laid out (by hand, or automatically until it is): rows of slots on 12 columns.
                'rows' => $rows = $this->rows($f['layout']['rows'] ?? [], $blocks),
                // The works of the week the page left out, re-runs included, oldest first: listed under it.
                'also' => $this->leftOut($blocks, $rows),
                'works' => count(array_filter($blocks, fn ($b) => isset($b['address']) && ! $b['refeature'])),
                'earlier' => $pick($oldestFirst[$i - 1] ?? null),
                'later' => $pick($oldestFirst[$i + 1] ?? null),
            ];
        }

        return $items;
    }

    /**
     * A week's layout filled in: each row's slots (a span of 12 columns, its
     * pieces one under another), each piece its block with how it shows that
     * week (treatment, picture) and its summary there, ready for the page.
     * A piece the layout names that the week doesn't have is left out.
     *
     * @param  list<list<array<string, mixed>>>  $layout
     * @param  list<array<string, mixed>>  $blocks
     * @return list<list<array{span: int, pieces: list<array<string, mixed>>}>>
     */
    /**
     * The week's works no row placed (re-runs included), oldest first.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<list<array<string, mixed>>>  $rows
     * @return list<array<string, mixed>>
     */
    private function leftOut(array $blocks, array $rows): array
    {
        $placed = [];

        foreach ($rows as $row) {
            foreach ($row as $slot) {
                foreach ($slot['pieces'] as $piece) {
                    $placed[$piece['slug']] = true;
                }
            }
        }

        $left = [];

        foreach ($blocks as $block) {
            if (isset($block['address'], $block['slug']) && ! isset($placed[$block['slug']]) && ! isset($left[$block['slug']])) {
                $left[$block['slug']] = ['title' => $block['title'], 'address' => $block['address'], 'date' => $block['date'], 'image' => $block['image'], 'division' => $block['division'], 'refeature' => $block['refeature']];
            }
        }

        usort($left, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        return array_values($left);
    }

    private function rows(array $layout, array $blocks): array
    {
        $bySlug = [];

        foreach ($blocks as $block) {
            if (isset($block['slug'])) {
                $bySlug[$block['slug']] ??= $block;
            }
        }

        $rows = [];

        foreach ($layout as $row) {
            $slots = [];

            foreach ($row as $slot) {
                $pieces = [];

                foreach ($slot['pieces'] ?? [] as $placed) {
                    $block = $bySlug[$placed['entry'] ?? ''] ?? null;

                    if ($block === null) {
                        continue;
                    }

                    $summary = trim((string) ($placed['summary'] ?? ''));
                    $summaryHtml = $summary === '' ? '' : self::wordsOnly(trim(WorkBody::oneSize(MarkdownExtra::defaultTransform(WorkBody::clean($summary, $block['image'], $block['title'], $this->stills)))));
                    $pieces[] = [
                        ...$block,
                        'treatment' => (string) ($placed['treatment'] ?? 'whole'),
                        'picture' => (string) ($placed['picture'] ?? 'whole'),
                        // This week's summary, its words alone (the piece's own picture goes beside it); none written: the teaser's words.
                        'summary_html' => $summaryHtml !== '' ? $summaryHtml : self::wordsOnly((string) $block['html']),
                        // Whether the work has more than the page shows: its words beyond the summary's, or pictures beyond the one beside it.
                        'more' => self::hasMore($summaryHtml !== '' ? $summaryHtml : (string) $block['html'], (string) $block['full_html'], $block['image'] !== null),
                        // Pictures in a row: its words without them, and its pictures (with their sizes) to set side by side beneath.
                        'row' => ($placed['picture'] ?? '') === 'row' ? self::pictureRow(in_array($block['kind'], ['story', 'steve'], true) ? (string) $block['full_html'] : ((string) $block['html'] !== '' ? (string) $block['html'] : (string) $block['full_html'])) : null,
                        // Its "Continued" line as set for the week: always, never, or (null) when it has more.
                        'continued' => in_array($placed['continued'] ?? null, ['show', 'hide'], true) ? $placed['continued'] : null,
                        'parts' => ($placed['treatment'] ?? '') === 'parts' ? IssueParts::split((string) $block['html']) : [],
                        // One part of the piece in a slot of its own (its pictures, or its words), from Alfred's Markdown for it.
                        'part' => isset($placed['part']) ? (int) $placed['part'] : null,
                        'part_html' => isset($placed['part'], $placed['markdown']) ? trim(WorkBody::oneSize(MarkdownExtra::defaultTransform(WorkBody::clean((string) $placed['markdown'], null, $block['title'], $this->stills)))) : '',
                    ];
                }

                if ($pieces !== []) {
                    $slots[] = ['span' => max(1, min(12, (int) ($slot['span'] ?? 12))), 'pieces' => $pieces];
                }
            }

            if ($slots !== []) {
                $rows[] = $slots;
            }
        }

        return $rows;
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
        $markdown = fn (string $text, ?string $image, string $title) => trim(WorkBody::oneSize(MarkdownExtra::defaultTransform(WorkBody::clean($text, $image, $title, $this->stills))));
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
                    'slug' => $w['slug'],
                    // The whole piece, for a front page that runs it whole.
                    'full_html' => $markdown($body, $w['image'], $w['title']),
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

                // How much reading it is.
                return [...$kinded, 'words' => str_word_count(strip_tags((string) $kinded['html']))];

            case 'featured_track':
                return [
                    'kind' => 'tune',
                    'artist' => $item['artist']['name'],
                    'image' => $item['artist']['image'] ?? null,
                    'track' => $item['track']['title'] ?? null,
                    'from' => $item['track']['featured_started_at'] ?? null,
                    'until' => $item['track']['featured_ended_at'] ?? null,
                    // What was said that week, and the artist's own bio and website: a line break is a new line.
                    'html' => $markdown(self::lineBreaks((string) ($item['track']['bio'] ?? '')), null, $item['artist']['name']),
                    'about' => $markdown(self::lineBreaks((string) ($item['artist']['bio'] ?? '')), null, $item['artist']['name']),
                    'website' => self::website($item['artist']['url'] ?? null),
                    // The playlist entry it plays (the same artist and title), when the tune has audio; the artist's page.
                    'play' => collect($this->tunes)->first(fn (array $t) => $t['artist'] === $item['artist']['name'] && $t['title'] === ($item['track']['title'] ?? null))['id'] ?? null,
                    'artist_address' => ($slug = collect($this->tunes)->first(fn (array $t) => $t['artist'] === $item['artist']['name'])['artist_slug'] ?? null) === null ? null : "/tunes/{$slug}/",
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
     * A piece's words without its pictures, and its pictures with their
     * sizes, to set side by side beneath the words at one height.
     *
     * @return array{words: string, pictures: list<array{src: string, width: int, height: int}>}
     */
    private static function pictureRow(string $html): array
    {
        preg_match_all('/<img\b[^>]*\bsrc="([^"]+)"[^>]*>/i', $html, $m);
        $sizes = WorkLayout::knownSizes();
        $words = (string) preg_replace(['/<img\b[^>]*>/i', '/<a\b[^>]*>\s*<\/a>/i', '/<p>\s*<\/p>/i'], '', $html);

        return [
            'words' => trim($words),
            'pictures' => array_map(fn (string $src): array => [
                'src' => $src,
                'width' => (int) ($sizes[html_entity_decode($src)][0] ?? 600),
                'height' => (int) ($sizes[html_entity_decode($src)][1] ?? 450),
            ], $m[1]),
        ];
    }

    /**
     * Whether a work has more than a summary shows of it: its own words the
     * summary leaves out (compared word for word, in any order, so a summary
     * that is the whole text rearranged counts as all of it; a stray word or
     * two aside), or pictures beyond the one shown beside it. A summary that
     * is the whole of it needs no way on.
     */
    private static function hasMore(string $summaryHtml, string $wholeHtml, bool $pictureBeside): bool
    {
        $bag = function (string $html): array {
            preg_match_all("/[\pL\pN']+/u", mb_strtolower(html_entity_decode(strip_tags($html))), $m);

            return array_count_values($m[0]);
        };
        $whole = $bag($wholeHtml);
        $summary = $bag($summaryHtml);
        $total = array_sum($whole);
        $covered = 0;

        foreach ($whole as $word => $count) {
            $covered += min($count, $summary[$word] ?? 0);
        }

        $pictures = (int) preg_match_all('/<img\b|<figure class="flash"/i', $wholeHtml);

        return $covered < $total * 0.95 - 2 || $pictures > ($pictureBeside ? 1 : 0);
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
     * A work described by its facts, for one with no words of its own: what
     * it is, whose it is, and when it was filed.
     *
     * @param  array<string, mixed>  $work
     * @param  array<string, mixed>  $e
     */
    private function facts(array $work, array $e): string
    {
        $what = match ($work['division']) {
            'comics' => 'a comic'.($work['series'] ? " in the {$work['series']} series" : ''),
            'flash' => 'a Flash movie',
            'featured-steves' => 'a Featured Steve',
            'ask-stevil' => 'a letter to Ask Stevil',
            'letters' => 'a letter'.($work['series'] ? " from {$work['series']}" : ''),
            default => 'a work'.($work['division_name'] ? " from {$work['division_name']}" : '').($work['series'] ? " ({$work['series']})" : ''),
        };
        $names = array_map(fn ($c) => $c['name'], $e['credits'] ?? []);
        $by = $names === [] ? '' : ' by '.implode(' and ', $names);

        return ucfirst($what).$by.', filed by the Axis of Stevil on '.CarbonImmutable::parse($work['date'])->format('F j, Y').'.';
    }

    /**
     * A body's words alone: its Flash directives, embedded players, bare addresses, link definitions and pictures by reference taken out.
     */
    private static function wordsOf(string $markdown): string
    {
        return (string) preg_replace(['/!#flash\([^)\s]+\)/', '/<(?:embed|object|iframe|video)\b[^>]*>(?:.*?<\/(?:embed|object|iframe|video)>)?/is', '/^https?:\/\/\S+$/m', '/\[[^\]]+\]:\s*https?:\/\/\S+(?:\s*"[^"]*")?/', '/!\[[^\]]*\]\[[^\]]*\]/'], '', $markdown);
    }

    /**
     * A work's title for the browser tab and the search listing: its own, with
     * its date when another work shares the title, and a number when the
     * other ran the same day.
     *
     * @param  array<string, mixed>  $work
     * @param  array<string, int>  $titles  how many works share each title
     * @param  array<string, int>  $days  how many share each title and day
     * @param  array<string, int>  $seen  how many of each title and day so far
     */
    private function pageTitle(array $work, array $titles, array $days, array &$seen): string
    {
        $title = mb_strtolower((string) $work['title']);

        if (($titles[$title] ?? 1) < 2) {
            return (string) $work['title'];
        }

        $day = $title.'|'.$work['date'];
        $seen[$day] = ($seen[$day] ?? 0) + 1;
        $when = CarbonImmutable::parse($work['date'])->format('F j, Y');

        return $work['title'].' ('.$when.(($days[$day] ?? 1) > 1 ? ', No. '.$seen[$day] : '').')';
    }

    /**
     * Text as a description, within the length a search listing shows (cut at a
     * sentence, else a word), or none when it says too little (a heading alone).
     */
    private function said(string $text): ?string
    {
        $plain = $this->plain($text);

        if (str_word_count($plain) < 4) {
            return null;
        }

        if (mb_strlen($plain) <= 160) {
            return $plain;
        }

        if (preg_match('/^(.{60,157}?[.!?])(?:\s|$)/u', $plain, $m) === 1) {
            return $m[1];
        }

        return rtrim((string) preg_replace('/\s+\S*$/u', '', mb_substr($plain, 0, 157))).'…';
    }

    /**
     * Markdown or HTML as one line of plain words.
     */
    private function plain(string $text): string
    {
        $html = str_contains($text, '<') ? $text : MarkdownExtra::defaultTransform($text);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
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
            // The link preview's picture, where one was chosen (else the lead image, else the site's card).
            'social_image' => $e['social_image'] ?? null,
            // The day its record last changed (the page's dateModified and its place in the sitemap).
            'updated' => isset($e['updated_at']) ? substr((string) $e['updated_at'], 0, 10) : null,
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
        $titles = array_count_values(array_map(fn ($w) => mb_strtolower((string) $w['title']), $works));
        $days = array_count_values(array_map(fn ($w) => mb_strtolower((string) $w['title']).'|'.$w['date'], $works));
        $seen = [];

        foreach ($byDivision as $division => $list) {
            foreach ($list as $i => $work) {
                $e = $bySlug[$work['slug']];
                $pick = fn (?array $w) => $w === null ? null : ['title' => $w['title'], 'address' => $w['address'], 'image' => $w['image'], 'date' => $w['date']];

                $pages[] = [
                    ...$work,
                    'filename' => $work['slug'],
                    // Its title for the browser tab and search listing: with its date when another work shares the title, numbered when the same day's.
                    'page_title' => $this->pageTitle($work, $titles, $days, $seen),
                    // Its summary, else the teaser written for a front page, else its opening words, else its facts: the page's description.
                    'description' => $this->said(self::wordsOf((string) ($e['summary'] ?? ''))) ?? $this->said(self::wordsOf((string) ($e['teaser'] ?? ''))) ?? $this->said($this->opening(self::wordsOf((string) ($e['body'] ?? '')))) ?? $this->facts($work, $e),
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
                'description' => $division['description'] ?? "Every work filed in {$division['name']}, the Axis of Stevil's division of ".count($works).' works.',
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
     * The featured artists with their tracks, as the API lists them; none
     * when the channel has no front pages.
     *
     * @return list<array<string, mixed>>
     */
    private function featuredArtists(string $channelUrl): array
    {
        try {
            return $this->json($channelUrl.'/featured-artists')['data'] ?? [];
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return [];
            }

            throw $e;
        }
    }

    /**
     * The Tunes of the Week with audio, oldest first, each as the player needs it.
     *
     * @param  list<array<string, mixed>>  $artists
     * @return list<array<string, mixed>>
     */
    private function tunes(array $artists): array
    {
        $tunes = [];

        foreach ($artists as $artist) {
            foreach ($this->merged($artist) as $track) {
                if ($track['audio'] === null) {
                    continue;
                }

                $tunes[] = [
                    'id' => $track['id'],
                    'title' => $track['title'],
                    'artist' => $artist['name'],
                    'artist_slug' => $artist['slug'],
                    'image' => $track['image'],
                    'audio' => $track['audio'],
                    'from' => $track['from'],
                    'until' => $track['until'],
                ];
            }
        }

        usort($tunes, fn ($a, $b) => [$a['from'], $a['id']] <=> [$b['from'], $b['id']]);

        return $tunes;
    }

    /**
     * An artist's tracks as tunes, as the API gives them: each with every
     * run it had (oldest first) and the text each run carried.
     *
     * @param  array<string, mixed>  $artist
     * @return list<array<string, mixed>>
     */
    private function merged(array $artist): array
    {
        $tunes = [];

        foreach ($artist['tracks'] ?? [] as $track) {
            $runs = array_values(array_map(fn ($run) => ['from' => $run['featured_started_at'], 'until' => $run['featured_ended_at'] ?? null], $track['runs'] ?? []));
            usort($runs, fn ($a, $b) => $a['from'] <=> $b['from']);

            if ($runs === []) {
                continue;
            }

            $tunes[] = [
                'id' => $artist['slug'].'/'.$track['slug'],
                'title' => $track['title'],
                'image' => $track['image'] ?? $artist['image'] ?? null,
                'audio' => $track['audio'] ?? null,
                'from' => $runs[0]['from'],
                'until' => $runs[0]['until'],
                'runs' => $runs,
                'bios' => array_map(fn ($run) => $run['bio'] ?? null, $track['runs'] ?? []),
            ];
        }

        usort($tunes, fn ($a, $b) => [$a['from'], $a['id']] <=> [$b['from'], $b['id']]);

        return $tunes;
    }

    /**
     * The Tunes section: every tune as a yearbook, then each artist's page
     * with their picture, their story (the bios their tunes ran with, each
     * once) and their tunes. An artist with no tune is left out.
     *
     * @param  list<array<string, mixed>>  $artists
     * @return list<array<string, mixed>>
     */
    private function tunePages(array $artists): array
    {
        $all = [];
        $byArtist = [];

        foreach ($artists as $artist) {
            $theirs = [];

            foreach ($this->merged($artist) as $track) {
                $theirs[] = [
                    'id' => $track['id'],
                    'title' => $track['title'],
                    'artist' => $artist['name'],
                    'artist_address' => "/tunes/{$artist['slug']}/",
                    'image' => $track['image'],
                    'audio' => $track['audio'],
                    'from' => $track['from'],
                    'until' => $track['until'],
                    'runs' => $track['runs'],
                    'bio' => implode("\n\n", array_filter($track['bios'])) ?: null,
                ];
            }

            if ($theirs === []) {
                continue;
            }

            usort($theirs, fn ($a, $b) => [$a['from'], $a['id']] <=> [$b['from'], $b['id']]);
            $byArtist[] = ['name' => $artist['name'], 'slug' => $artist['slug'], 'image' => $artist['image'] ?? null, 'bio' => $artist['bio'] ?? null, 'url' => $artist['url'] ?? null, 'address' => "/tunes/{$artist['slug']}/", 'count' => count($theirs), 'tunes' => $theirs];
            array_push($all, ...$theirs);
        }

        if ($all === []) {
            return [];
        }

        usort($all, fn ($a, $b) => [$a['from'], $a['id']] <=> [$b['from'], $b['id']]);
        usort($byArtist, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);
        $years = array_values(array_unique(array_map(fn ($t) => substr((string) $t['from'], 0, 4), $all)));
        sort($years);
        $span = $years === [] ? null : ($years[0] === end($years) ? $years[0] : $years[0].'–'.end($years));
        $plural = fn (int $n, string $one, string $many) => $n === 1 ? $one : $many;
        $artistsListed = array_map(fn ($a) => ['name' => $a['name'], 'slug' => $a['slug'], 'address' => $a['address'], 'count' => $a['count']], $byArtist);

        $items = [[
            'filename' => 'tunes',
            'address' => '/tunes/',
            'title' => 'Tunes — The Axis of Stevil',
            'name' => 'Tunes',
            'slug' => 'tunes',
            'parent' => null,
            'description' => 'Every Tune of the Week the Axis of Stevil featured: '.count($all).' tunes by '.count($byArtist).' '.$plural(count($byArtist), 'artist', 'artists').($span ? ", {$span}" : '').'.',
            'image' => null,
            'count' => count($all),
            'figure' => $plural(count($all), 'tune', 'tunes').' · '.count($byArtist).' '.$plural(count($byArtist), 'artist', 'artists').($span ? " · {$span}" : ''),
            'banner' => null,
            'introduction' => null,
            'note_label' => 'Artists',
            'note' => array_column($artistsListed, 'count', 'name'),
            'note_links' => array_column($artistsListed, 'address', 'name'),
            'artists' => $artistsListed,
            'listed' => $all,
        ]];

        // Artists A to Z (a leading article set aside), each page knowing its neighbours.
        $alphabetical = $byArtist;
        $sortKey = fn (array $a): string => mb_strtolower((string) preg_replace('/^(?:(?:a|an|the)\s+)?/iu', '', (string) preg_replace('/^[^\pL\pN]+/u', '', $a['name'])));
        usort($alphabetical, fn ($a, $b) => strcmp($sortKey($a), $sortKey($b)));
        $neighbour = fn (?array $a) => $a === null ? null : ['name' => $a['name'], 'address' => $a['address']];

        foreach ($byArtist as $artist) {
            $at = array_search($artist['slug'], array_column($alphabetical, 'slug'), true);
            $at = $at === false ? 0 : $at;

            // Their story: their own bio. What a run said that week stays with that week's page.
            $story = trim(str_replace(["\r\n", "\r"], "\n", (string) ($artist['bio'] ?? '')));

            $items[] = [
                'filename' => 'tunes--'.$artist['slug'],
                'address' => $artist['address'],
                'title' => $artist['name'].' — Tunes — The Axis of Stevil',
                'name' => $artist['name'],
                'slug' => $artist['slug'],
                'parent' => 'tunes',
                'description' => $artist['name'].', featured by the Axis of Stevil: '.$artist['count'].' '.$plural($artist['count'], 'tune', 'tunes').' of the week.',
                'image' => $artist['image'],
                'count' => $artist['count'],
                'figure' => $plural($artist['count'], 'tune', 'tunes').' of the week',
                'banner' => $artist['image'],
                'introduction' => $story === '' ? null : MarkdownExtra::defaultTransform(self::lineBreaks($story)),
                'website' => self::website($artist['url']),
                'earlier' => $neighbour($alphabetical[$at - 1] ?? null),
                'later' => $neighbour($alphabetical[$at + 1] ?? null),
                'note_label' => null,
                'note' => [],
                'note_links' => [],
                'artists' => $artistsListed,
                'listed' => $artist['tunes'],
            ];
        }

        return $items;
    }

    /**
     * The channel's first gallery with its photographs in the gallery's order
     * and its photographers (everyone credited, most photographs first), or
     * null when the channel has no gallery.
     *
     * @return array<string, mixed>|null
     */
    private function gallery(string $channelUrl): ?array
    {
        try {
            $galleries = $this->json($channelUrl.'/galleries')['data'] ?? [];
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }

        $first = $galleries[0] ?? null;

        if ($first === null) {
            return null;
        }

        $data = $this->json($channelUrl.'/galleries/'.$first['slug'])['data'] ?? [];
        $photographers = [];
        $photos = [];

        foreach ($data['items'] ?? [] as $item) {
            $names = array_column($item['credits'] ?? [], 'name');

            foreach ($names as $name) {
                $slug = $this->slug($name);
                $photographers[$slug] ??= ['name' => $name, 'slug' => $slug, 'count' => 0, 'address' => "/gallery/{$slug}/"];
                $photographers[$slug]['count']++;
            }

            $photos[] = [
                'image' => $item['image'],
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
                'caption' => $item['caption'] ?? null,
                'date' => $item['date'] ?? null,
                'credits' => array_map(fn (string $name) => ['name' => $name, 'address' => '/gallery/'.$this->slug($name).'/'], $names),
                // The photographer it files under: the first credited (none: unnamed).
                'photographer' => $names === [] ? null : ['name' => $names[0], 'slug' => $this->slug($names[0]), 'address' => '/gallery/'.$this->slug($names[0]).'/'],
            ];
        }

        usort($photographers, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return [
            'slug' => $data['slug'] ?? $first['slug'],
            'name' => $data['name'] ?? $first['name'],
            'banner' => $data['banner'] ?? null,
            'introduction' => ($text = $data['description'] ?? null) === null ? null : MarkdownExtra::defaultTransform($text),
            'photos' => $photos,
            'photographers' => array_values($photographers),
        ];
    }

    /**
     * The Gallery's page, then each photographer's narrowed listing under the same banner.
     *
     * @param  array<string, mixed>  $gallery
     * @return list<array<string, mixed>>
     */
    private function galleryPages(array $gallery): array
    {
        $photos = $gallery['photos'];
        $dates = array_values(array_filter(array_map(fn ($p) => substr((string) $p['date'], 0, 4) ?: null, $photos)));
        sort($dates);
        $span = $dates === [] ? null : ($dates[0] === end($dates) ? $dates[0] : $dates[0].'–'.end($dates));
        $people = count($gallery['photographers']);
        $plural = fn (int $n, string $one, string $many) => $n === 1 ? $one : $many;

        $items = [[
            'filename' => 'gallery',
            'address' => '/gallery/',
            'title' => 'Gallery — The Axis of Stevil',
            'name' => $gallery['name'],
            'slug' => 'gallery',
            'parent' => null,
            'description' => "The Axis of Stevil's gallery: ".count($photos).' photographs by '.$people.' '.$plural($people, 'photographer', 'photographers').($span ? ", {$span}" : '').'.',
            'image' => $gallery['banner'],
            'count' => count($photos),
            'figure' => $plural(count($photos), 'photograph', 'photographs').' · '.$people.' '.$plural($people, 'photographer', 'photographers').($span ? " · {$span}" : ''),
            'banner' => $gallery['banner'],
            'introduction' => $gallery['introduction'],
            'note_label' => 'Photographers',
            'note' => array_column($gallery['photographers'], 'count', 'name'),
            'note_links' => array_column($gallery['photographers'], 'address', 'name'),
            'photos' => $photos,
        ]];

        foreach ($gallery['photographers'] as $photographer) {
            $theirs = array_values(array_filter($photos, fn ($p) => in_array($photographer['name'], array_column($p['credits'], 'name'), true)));

            $items[] = [
                'filename' => 'gallery--'.$photographer['slug'],
                'address' => $photographer['address'],
                'title' => $photographer['name'].' — Gallery — The Axis of Stevil',
                'name' => $photographer['name'],
                'slug' => $photographer['slug'],
                'parent' => 'gallery',
                'description' => count($theirs).' '.$plural(count($theirs), 'photograph', 'photographs')." by {$photographer['name']} in the Axis of Stevil's gallery.",
                'image' => $gallery['banner'],
                'count' => count($theirs),
                'figure' => $plural(count($theirs), 'photograph', 'photographs').' · '.(count($photos) > 0 ? (int) round(count($theirs) / count($photos) * 100) : 0).'% of the gallery',
                'banner' => $gallery['banner'],
                'introduction' => $gallery['introduction'],
                'note_label' => null,
                'note' => [],
                'note_links' => [],
                'photos' => $theirs,
            ];
        }

        return $items;
    }

    /**
     * A tune's words with every line break kept: a bio or a line-up is written
     * a line at a time, so a single line break is a new line (Markdown would
     * otherwise run the lines together unless two spaces end each).
     */
    public static function lineBreaks(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        return (string) preg_replace('/(?<!\n)(?<!  )[ ]?\n(?!\n)/', "  \n", $markdown);
    }

    /**
     * A website as a link: the address alone (scheme and www left off), and
     * for a copy on the Wayback Machine the original address, marked archived.
     *
     * @return array{href: string, text: string, archived: bool}|null
     */
    public static function website(?string $url): ?array
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $archived = preg_match('#^https?://web\.archive\.org/web/\d+[a-z_]*/(.+)$#i', $url, $m) === 1;
        $shown = $archived ? $m[1] : $url;

        return ['href' => $url, 'text' => (string) preg_replace('#^https?://(www\.)?#i', '', rtrim($shown, '/')), 'archived' => $archived];
    }

    /**
     * A name as an address: lower case, letters and digits, hyphens between.
     */
    private function slug(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
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
