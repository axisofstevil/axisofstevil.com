<?php

namespace App\Services;

use TightenCo\Jigsaw\Jigsaw;

/**
 * The sitemap (/sitemap.xml): every page of the site at its address, with
 * the date it last changed where that is known (a work's, a page's), and
 * robots.txt naming it. The forwarding stubs and the 404 are not pages.
 */
class Sitemap
{
    public const array COLLECTIONS = ['organisation', 'divisions', 'issue_pages', 'gallery_pages', 'tune_pages', 'works'];

    public function handle(Jigsaw $jigsaw): void
    {
        $base = rtrim((string) $jigsaw->getConfig('baseUrl'), '/');
        $build = $jigsaw->getDestinationPath();
        $urls = [[$base.'/', null], [$base.'/issues/', null]];

        foreach (self::COLLECTIONS as $name) {
            foreach ($jigsaw->getCollection($name) ?? [] as $item) {
                $urls[] = [$base.$item->link(), $item->updated ?? null];
            }
        }

        $entries = array_map(fn (array $url) => '    <url><loc>'.htmlspecialchars($url[0], ENT_XML1).'</loc>'.($url[1] === null ? '' : '<lastmod>'.$url[1].'</lastmod>').'</url>', $urls);

        file_put_contents($build.'/sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".implode("\n", $entries)."\n</urlset>\n");
        file_put_contents($build.'/robots.txt', "User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n");
    }
}
