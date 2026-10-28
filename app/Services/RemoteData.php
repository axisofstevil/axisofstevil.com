<?php

namespace App\Services;

use App\WorkBody;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
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

        $workPages = $this->pages($entries, $works);
        $collections = $jigsaw->getConfig('collections');
        $collections->set('works.items', $workPages);

        $issues = array_map(fn (array $f) => [
            'slug' => $f['slug'],
            'title' => $f['title'],
            'starts_on' => $f['starts_on'],
            'ends_on' => $f['ends_on'],
        ], $this->all($channelUrl.'/front-pages?per_page=100'));

        usort($issues, fn ($a, $b) => strcmp($b['starts_on'], $a['starts_on']));

        $divisions = array_map(fn (array $c) => [
            'slug' => $c['slug'],
            'name' => $c['name'],
            'count' => $c['entries'],
        ], $this->json($channelUrl.'/categories')['data'] ?? []);

        $divisions = array_map(fn (array $d) => [...$d, 'series' => $this->series($d['slug'], $workPages)], $divisions);

        $collections->set('divisions.items', $this->divisionPages($divisions, $workPages, count($works)));
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
                    'content' => WorkBody::clean((string) ($p['body'] ?? ''), $p['image'] ?? null, $p['title']),
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
    private function pages(array $entries, array $works): array
    {
        $bySlug = array_column($entries, null, 'slug');
        $byDivision = [];

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
                    'keywords' => array_values(array_filter(array_map('trim', explode(',', (string) ($e['meta_keywords'] ?? ''))))),
                    'roles' => array_map(fn ($c) => ['name' => $c['name'], 'roles' => $c['roles'] ?? []], $e['credits'] ?? []),
                    'place' => $i + 1,
                    'of' => count($list),
                    'older' => $pick($list[$i - 1] ?? null),
                    'newer' => $pick($list[$i + 1] ?? null),
                    'content' => WorkBody::clean((string) ($e['body'] ?? ''), $e['image'] ?? null, $e['title']),
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
     * @return list<array<string, mixed>>
     */
    private function divisionPages(array $divisions, array $workPages, int $total): array
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
