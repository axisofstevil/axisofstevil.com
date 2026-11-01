<?php

namespace App\Services;

use RuntimeException;
use TightenCo\Jigsaw\Jigsaw;

/**
 * The site's search index (Pagefind, https://pagefind.app): built last, over
 * the finished pages, into /pagefind/. Only what a page marks data-pagefind-body
 * is indexed (a work, an issue's masthead, a listing's head and introduction,
 * an artist, a page of the organisation); a page marking nothing is left out.
 * The page's search dialog loads /pagefind/pagefind.js when first opened.
 */
class Pagefind
{
    public const string VERSION = '1.5.2';

    public function handle(Jigsaw $jigsaw): void
    {
        $build = $jigsaw->getDestinationPath();
        $command = sprintf('npx --yes pagefind@%s --site %s --quiet 2>&1', self::VERSION, escapeshellarg($build));
        $output = [];
        $status = 0;
        exec($command, $output, $status);

        if ($status !== 0) {
            throw new RuntimeException('Pagefind did not build the search index: '.implode("\n", $output));
        }

        if (! is_file($build.'/pagefind/pagefind.js')) {
            throw new RuntimeException('Pagefind ran but wrote no index.');
        }
    }
}
