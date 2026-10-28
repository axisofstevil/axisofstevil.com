<?php

namespace App\Services;

use TightenCo\Jigsaw\Jigsaw;

/**
 * The old site's addresses, forwarded to the new ones, in a form any host can
 * use (none is chosen yet):
 *
 * - _redirects: every forward as "from to 301", the format Netlify and
 *   Cloudflare Pages read, easily turned into any other host's rules;
 * - a page at each old page address (….htm, ….html, a folder, with or
 *   without its slash) that forwards
 *   at once and names the new address as canonical, for a host that only
 *   serves files. An old file of another kind (a PDF, a movie) is forwarded
 *   by _redirects alone.
 *
 * A forward to another site (a file still on an old host) is kept as it is.
 * One to an address this site doesn't have (yet: the front pages) is left
 * out and reported, so the forwards never point at a missing page.
 */
class Redirects
{
    public function handle(Jigsaw $jigsaw): void
    {
        $build = $jigsaw->getDestinationPath();
        $base = rtrim((string) $jigsaw->getConfig('baseUrl'), '/');
        $rules = [];
        $missing = [];

        foreach ($jigsaw->getConfig('redirects') ?? [] as $redirect) {
            $from = '/'.ltrim((string) $redirect['from'], '/');
            $external = (bool) preg_match('#^https?://#i', (string) $redirect['to']);
            $to = $external ? (string) $redirect['to'] : $this->address((string) $redirect['to']);

            if (! $external && ! is_file($build.rtrim($to, '/').'/index.html') && ! is_file($build.$to)) {
                $missing[] = "{$from} → {$to}";

                continue;
            }

            $rules[] = "{$from} {$to} 301";

            $file = match (true) {
                str_ends_with($from, '/') => $from.'index.html',
                (bool) preg_match('/\.html?$/i', $from) => $from,
                // A folder address written without its slash (/steves).
                ! preg_match('/\.[a-z0-9]{2,4}$/i', $from) => $from.'/index.html',
                default => null,
            };

            if ($file !== null && ! is_file($build.$file)) {
                @mkdir(dirname($build.$file), 0777, true);
                file_put_contents($build.$file, $this->stub($to, $base));
            }
        }

        if ($missing !== []) {
            fwrite(STDERR, 'Not forwarded, the site has no such page yet: '.implode('; ', $missing).PHP_EOL);
        }

        file_put_contents($build.'/_redirects', implode("\n", $rules)."\n");
    }

    /**
     * A page address with the trailing slash the site uses; a file keeps its name.
     */
    private function address(string $to): string
    {
        $to = '/'.ltrim($to, '/');

        return preg_match('/\.[a-z0-9]{2,4}$/i', $to) || str_ends_with($to, '/') ? $to : $to.'/';
    }

    private function stub(string $to, string $base): string
    {
        $url = htmlspecialchars($to, ENT_QUOTES);
        $canonical = htmlspecialchars(preg_match('#^https?://#i', $to) ? $to : $base.$to, ENT_QUOTES);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <title>Moved</title>
            <link rel="canonical" href="{$canonical}">
            <meta name="robots" content="noindex">
            <meta http-equiv="refresh" content="0; url={$url}">
            <script>location.replace("{$url}" + location.hash)</script>
            </head>
            <body><p>This page has moved to <a href="{$url}">{$canonical}</a>.</p></body>
            </html>

            HTML;
    }
}
