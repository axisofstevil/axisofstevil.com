<?php

namespace App;

use TightenCo\Jigsaw\PageVariable;

/**
 * A page's structured data (schema.org, as JSON-LD in its head), for search
 * engines and whoever else reads it: the site and its publisher on the home
 * page; a work as an article (a comic as a comic story) with its credits,
 * its division and its series; an issue as an issue of the periodical with
 * the works it ran; a listing as a collection; the gallery as a gallery of
 * its photographs; the tunes as a playlist and each artist as a group with
 * their recordings; and on every page but the home, the trail back to it.
 */
class StructuredData
{
    private const string NAME = 'The Axis of Stevil';

    /**
     * @return array<string, mixed> empty when the page has none
     */
    public static function for(PageVariable $page): array
    {
        $base = rtrim((string) $page->baseUrl, '/');
        $url = $base.$page->link();
        $path = $page->getPath();
        $collection = $page->_meta->collection ?? null;
        $publisher = ['@id' => $base.'/#organization'];
        $site = ['@id' => $base.'/#website'];

        // The site and its publisher, on every page (the home page says the most about them).
        $home = $path === '' || $path === '/';
        $website = [
            '@type' => 'WebSite',
            '@id' => $base.'/#website',
            'url' => $base.'/',
            'name' => self::NAME,
            'alternateName' => $home ? 'Axis of Stevil' : null,
            'description' => $home ? $page->description : null,
            'inLanguage' => 'en',
            'publisher' => $publisher,
        ];
        $organization = [
            '@type' => 'Organization',
            '@id' => $base.'/#organization',
            'name' => self::NAME,
            'url' => $base.'/',
            'logo' => ['@type' => 'ImageObject', 'url' => $base.'/assets/logo/axis-symbol.png'],
            'image' => $home ? $base.'/assets/social/card.png' : null,
            'foundingDate' => $home ? (string) $page->founded : null,
            'slogan' => $home ? $page->tagline : null,
        ];

        if ($home) {
            return self::graph([$website, $organization]);
        }

        $trail = [['Home', '/']];
        $main = null;

        switch (true) {
            case $collection === 'works':
                $divisions = self::rows($page->divisions);
                $division = self::find($divisions, 'slug', $page->division);
                $series = $division === null ? null : self::find($division['series'] ?? [], 'slug', $page->series_slug);
                $credits = [];

                foreach (self::rows($page->roles) as $credit) {
                    $credits[$credit['name']] ??= ['@type' => 'Person', 'name' => $credit['name']];
                }

                $main = [
                    '@type' => $page->division === 'comics' ? 'ComicStory' : 'Article',
                    'mainEntityOfPage' => $url,
                    'url' => $url,
                    'headline' => $page->title,
                    'description' => $page->description,
                    'image' => $page->image,
                    'datePublished' => $page->date,
                    'dateModified' => $page->updated ?? $page->date,
                    'author' => array_values($credits) ?: null,
                    'publisher' => $publisher,
                    'articleSection' => $page->division_name,
                    'isPartOf' => $series === null ? $site : ['@type' => 'CreativeWorkSeries', 'name' => $series['name'], 'url' => $base.$series['address']],
                    'keywords' => implode(', ', self::rows($page->keywords)) ?: null,
                    'isAccessibleForFree' => true,
                    'inLanguage' => 'en',
                ];

                if ($page->division_name) {
                    $trail[] = [$page->division_name, "/{$page->division}/"];
                }

                if ($series !== null) {
                    $trail[] = [$series['name'], $series['address']];
                }

                $trail[] = [$page->title, $page->link()];
                break;

            case $collection === 'issue_pages':
                $works = array_values(array_filter(self::rows($page->blocks), fn ($b) => isset($b['address']) && empty($b['refeature'])));

                $main = [
                    '@type' => 'PublicationIssue',
                    'mainEntityOfPage' => $url,
                    'url' => $url,
                    'name' => $page->name,
                    'issueNumber' => $page->number,
                    'datePublished' => $page->starts_on,
                    'description' => $page->description,
                    'isPartOf' => ['@type' => 'Periodical', 'name' => self::NAME, 'url' => $base.'/issues/', 'publisher' => $publisher],
                    'hasPart' => array_map(fn ($w) => ['@type' => 'Article', 'headline' => $w['title'], 'url' => $base.$w['address']], $works) ?: null,
                    'inLanguage' => 'en',
                ];
                $trail[] = ['Issues', '/issues/'];
                $trail[] = [$page->name, $page->link()];
                break;

            case $collection === 'divisions':
                $parent = $page->parent ? self::find(self::rows($page->divisions), 'slug', $page->parent) : null;

                $main = [
                    '@type' => 'CollectionPage',
                    'url' => $url,
                    'name' => $page->name,
                    'description' => $page->description,
                    'about' => $parent === null ? null : ['@type' => 'CreativeWorkSeries', 'name' => $page->name, 'url' => $url],
                    'isPartOf' => $site,
                    'inLanguage' => 'en',
                ];

                if ($parent !== null) {
                    $trail[] = [$parent['name'], "/{$parent['slug']}/"];
                }

                $trail[] = [$page->name, $page->link()];
                break;

            case $collection === 'gallery_pages':
                $photos = array_map(fn ($p) => self::clean([
                    '@type' => 'ImageObject',
                    'contentUrl' => $p['image'],
                    'width' => $p['width'],
                    'height' => $p['height'],
                    'caption' => $p['caption'],
                    'datePublished' => $p['date'],
                    'creator' => array_map(fn ($c) => ['@type' => 'Person', 'name' => $c['name']], $p['credits'] ?? []) ?: null,
                    'creditText' => implode(', ', array_column($p['credits'] ?? [], 'name')) ?: null,
                ]), self::rows($page->photos));

                $main = [
                    '@type' => 'ImageGallery',
                    'url' => $url,
                    'name' => $page->parent ? $page->name.' — '.self::NAME.' Gallery' : $page->name,
                    'description' => $page->description,
                    'image' => $page->banner,
                    'about' => $page->parent ? ['@type' => 'Person', 'name' => $page->name] : null,
                    'hasPart' => $photos ?: null,
                    'isPartOf' => $site,
                    'inLanguage' => 'en',
                ];
                $trail[] = ['Gallery', '/gallery/'];

                if ($page->parent) {
                    $trail[] = [$page->name, $page->link()];
                }
                break;

            case $collection === 'tune_pages':
                $tracks = array_map(fn ($t) => self::clean([
                    '@type' => 'MusicRecording',
                    'name' => $t['title'],
                    'byArtist' => ['@type' => 'MusicGroup', 'name' => $t['artist'], 'url' => $base.$t['artist_address']],
                    'image' => $t['image'],
                    'audio' => $t['audio'] ? ['@type' => 'AudioObject', 'contentUrl' => $t['audio'], 'encodingFormat' => 'audio/mpeg'] : null,
                    'datePublished' => $t['from'],
                ]), self::rows($page->listed));

                if ($page->parent) {
                    $theirs = $page->website ? self::rows($page->website) : null;
                    $main = [
                        '@type' => 'MusicGroup',
                        'mainEntityOfPage' => $url,
                        'url' => $url,
                        'name' => $page->name,
                        'image' => $page->image,
                        'description' => $page->introduction ? self::words((string) $page->introduction) : $page->description,
                        'sameAs' => $theirs['href'] ?? null,
                        'track' => $tracks ?: null,
                    ];
                } else {
                    $main = [
                        '@type' => 'MusicPlaylist',
                        'url' => $url,
                        'name' => self::NAME.': the Tunes of the Week',
                        'description' => $page->description,
                        'numTracks' => count($tracks),
                        'track' => $tracks ?: null,
                        'isPartOf' => $site,
                    ];
                }

                $trail[] = ['Tunes', '/tunes/'];

                if ($page->parent) {
                    $trail[] = [$page->name, $page->link()];
                }
                break;

            case $collection === 'organisation':
                $main = [
                    '@type' => match ($page->slug) {
                        'mission', 'history', 'authenticity' => 'AboutPage',
                        'contact' => 'ContactPage',
                        default => 'WebPage',
                    },
                    'url' => $url,
                    'name' => $page->title,
                    'description' => $page->description,
                    'image' => $page->image,
                    'dateModified' => $page->updated,
                    'isPartOf' => $site,
                    'publisher' => $publisher,
                    'inLanguage' => 'en',
                ];
                $trail[] = [$page->title, $page->link()];
                break;

            case rtrim($path, '/') === '/issues':
                $main = [
                    '@type' => 'CollectionPage',
                    'url' => $url,
                    'name' => 'Issues',
                    'description' => $page->description,
                    'isPartOf' => $site,
                    'inLanguage' => 'en',
                ];
                $trail[] = ['Issues', '/issues/'];
                break;

            default:
                return [];
        }

        return self::graph([
            $main,
            $website,
            $organization,
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn ($crumb, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb[0],
                    'item' => $base.$crumb[1],
                ], $trail, array_keys($trail)),
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private static function graph(array $nodes): array
    {
        return ['@context' => 'https://schema.org', '@graph' => array_map(self::clean(...), $nodes)];
    }

    /**
     * The node without its empty properties.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function clean(array $node): array
    {
        return array_filter($node, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * A page value as plain arrays, however the page holds it.
     *
     * @return array<mixed>
     */
    private static function rows(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : (array) json_decode((string) json_encode($value), true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private static function find(array $rows, string $key, mixed $value): ?array
    {
        foreach ($rows as $row) {
            if (($row[$key] ?? null) === $value) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Rendered text as its words: a first paragraph or two, within a few hundred characters.
     */
    private static function words(string $html): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return mb_strlen($text) > 500 ? rtrim(mb_substr($text, 0, 480)).'…' : $text;
    }
}
