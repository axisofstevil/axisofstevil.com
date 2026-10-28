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

        $collections->set('divisions.items', $this->divisionPages($divisions, $workPages, count($works)));
        $jigsaw->setConfig('collections', $collections);

        $pages = [];

        foreach ($this->json($channelUrl.'/pages')['data'] ?? [] as $p) {
            $pages[$p['slug']] = ['title' => $p['title'], 'summary' => $p['summary'] ?? null, 'path' => $p['path']];
        }

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
            'image' => $e['image'] ?? null,
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
     * Each division's page: its share of the output, its series, and its
     * works (oldest first, labelled by their place numbers) for the index and
     * the yearbook; then All Works, every work labelled by its division.
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

            $series = [];
            foreach ($works as $w) {
                if ($w['series'] !== null) {
                    $series[$w['series']] = ($series[$w['series']] ?? 0) + 1;
                }
            }
            arsort($series);

            $items[] = [
                'filename' => $division['slug'],
                'title' => $division['name'].' — The Axis of Stevil',
                'name' => $division['name'],
                'slug' => $division['slug'],
                'description' => "Every work filed in {$division['name']}, the Axis of Stevil's division of ".count($works).' works.',
                'count' => count($works),
                'figure' => (count($works) === 1 ? 'work' : 'works').' · '.($total > 0 ? (int) round(count($works) / $total * 100) : 0).'% of output',
                'note_label' => 'Series',
                'note' => $series,
                'works' => array_map(fn ($w) => [...$this->listed($w), 'label' => 'Plate '.$w['place']], $works),
            ];
        }

        $all = $workPages;
        usort($all, fn ($a, $b) => [$a['date'], $a['place']] <=> [$b['date'], $b['place']]);

        $items[] = [
            'filename' => 'works',
            'title' => 'All Works — The Axis of Stevil',
            'name' => 'All Works',
            'slug' => 'works',
            'description' => 'Every work filed by the Axis of Stevil since 2005: '.count($all).' works in '.count($divisions).' divisions.',
            'count' => count($all),
            'figure' => 'works · '.count($divisions).' divisions',
            'note_label' => 'Divisions',
            'note' => array_column($divisions, 'count', 'name'),
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
