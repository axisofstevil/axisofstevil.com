<?php

namespace App\Services;

use MatthiasMullie\Minify;
use TightenCo\Jigsaw\Jigsaw;

/**
 * The stylesheet and the script, minified and named for their contents
 * (site.3f9a1c2b.css), so browsers and the CDN can keep them for a year: a
 * change makes a new name. Before the build, the names are set for the
 * templates; after it, the files are written and the sources removed.
 */
class Assets
{
    private const STYLES = ['assets/site.css'];

    private const SCRIPTS = ['assets/site.js'];

    public function handle(Jigsaw $jigsaw): void
    {
        $source = dirname(__DIR__, 2).'/source/';
        $css = '/assets/site.'.self::hash($source, self::STYLES).'.css';
        $js = '/assets/site.'.self::hash($source, self::SCRIPTS).'.js';

        if ($jigsaw->getConfig('assetCss') === null) {
            $jigsaw->setConfig('assetCss', $css);
            $jigsaw->setConfig('assetJs', $js);

            return;
        }

        $build = $jigsaw->getDestinationPath();
        (new Minify\CSS(...array_map(fn ($file) => $source.$file, self::STYLES)))->minify($build.$css);
        (new Minify\JS(...array_map(fn ($file) => $source.$file, self::SCRIPTS)))->minify($build.$js);

        foreach ([...self::STYLES, ...self::SCRIPTS] as $file) {
            @unlink($build.'/'.$file);
        }
    }

    /**
     * @param  list<string>  $files
     */
    private static function hash(string $source, array $files): string
    {
        return substr(md5(implode('', array_map(fn ($file) => (string) file_get_contents($source.$file), $files))), 0, 10);
    }
}
