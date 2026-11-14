<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use TightenCo\Jigsaw\Jigsaw;

/**
 * Every picture on every page gets its width and height, so the browser
 * keeps its space before it arrives and nothing jumps, and decodes off the
 * main thread. Pictures from the archive load lazily, except a work's first
 * (the one at the top of the page), which loads at once.
 *
 * Sizes are read from the start of each file (the header holds them) and kept
 * in .cache/image-sizes.json between builds.
 *
 * The CMS's alt text for an archive picture (config art_alts) is used wherever
 * the picture appears, and only its alt attribute changes: the owner's
 * replaces the site's own; Claude's unreviewed one only fills a picture with
 * no alt text, or only a file name (owner, 2026-11-13). Every picture's alt
 * text goes into the search index with the page's words.
 */
class ImageAttributes
{
    // Outside Jigsaw's own cache/, which it empties on every build.
    public const CACHE = '.cache/image-sizes.json';

    public function handle(Jigsaw $jigsaw): void
    {
        $build = $jigsaw->getDestinationPath();
        $alts = json_decode(json_encode($jigsaw->getConfig('art_alts') ?? []), true) ?: [];
        $files = $this->htmlFiles($build);
        $cachePath = dirname(__DIR__, 2).'/'.self::CACHE;
        $sizes = is_file($cachePath) ? (json_decode((string) file_get_contents($cachePath), true) ?: []) : [];

        $wanted = [];
        foreach ($files as $file) {
            preg_match_all('/<img\b[^>]*\bsrc="(https?:\/\/[^"]+)"/i', (string) file_get_contents($file), $m);
            foreach ($m[1] as $url) {
                $url = html_entity_decode($url);
                if (! array_key_exists($url, $sizes)) {
                    $wanted[$url] = true;
                }
            }
        }

        $sizes = [...$sizes, ...$this->measure(array_keys($wanted))];

        @mkdir(dirname($cachePath), 0777, true);
        file_put_contents($cachePath, json_encode($sizes, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        foreach ($files as $file) {
            $html = $original = (string) file_get_contents($file);
            $first = strpos($html, 'class="plate work ');
            $firstImage = $first === false ? false : stripos($html, '<img', $first);
            // A picture written without alt text (a work's, in its Markdown) is named for its page.
            $title = preg_match('/<title>(.*?)<\/title>/s', $html, $t) === 1 ? trim((string) preg_replace('/\s+—\s+The Axis of Stevil$/u', '', html_entity_decode($t[1]))) : '';

            $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $m) use ($sizes, $title, $alts) {
                $tag = self::alt($m[0], $alts);
                $src = preg_match('/\bsrc="([^"]+)"/i', $tag, $s) ? html_entity_decode($s[1]) : null;
                $add = '';

                if (! preg_match('/\balt=/i', $tag)) {
                    $add .= ' alt="'.htmlspecialchars($title, ENT_QUOTES).'"';
                }

                if ($src !== null && ! preg_match('/\bwidth=/i', $tag) && isset($sizes[$src][0])) {
                    $add .= ' width="'.$sizes[$src][0].'" height="'.$sizes[$src][1].'"';
                }

                if (! preg_match('/\bdecoding=/i', $tag)) {
                    $add .= ' decoding="async"';
                }

                // Its alt text is searchable (Pagefind indexes an attribute only when the element asks): a picture is
                // found by what it shows, wherever it sits in an indexed page.
                if (! preg_match('/\bdata-pagefind-index-attrs=/i', $tag)) {
                    $add .= ' data-pagefind-index-attrs="alt"';
                }

                // The site's own small images (the logo in the running head) load at once.
                if ($src !== null && preg_match('/^https?:/i', $src) && ! preg_match('/\bloading=/i', $tag)) {
                    $add .= ' loading="lazy"';
                }

                return $add === '' ? $tag : preg_replace('/^<img\b/i', '<img'.$add, $tag);
            }, $html);

            // A work's first picture is at the top of the page: it loads at once.
            if ($firstImage !== false) {
                $at = stripos($html, '<img', (int) strpos($html, 'class="plate work '));
                $end = strpos($html, '>', $at);
                $tag = substr($html, $at, $end - $at + 1);
                $eager = str_replace(' loading="lazy"', ' fetchpriority="high"', $tag);
                $html = substr_replace($html, $eager, $at, strlen($tag));
            }

            // Written only when changed: a file left alone keeps its date (the forwarding stubs are dated once, for the deploy).
            if ($html !== $original) {
                file_put_contents($file, $html);
            }
        }
    }

    /**
     * The picture's alt text from the CMS, when it has some: the owner's always; Claude's only where the tag has none,
     * or only a file name. Nothing but the alt attribute changes.
     *
     * @param  array<string, array{alt: string, reviewed: bool}>  $alts
     */
    public static function alt(string $tag, array $alts): string
    {
        $src = preg_match('/\bsrc="([^"]+)"/i', $tag, $s) ? html_entity_decode($s[1]) : null;
        $known = $src === null ? null : ($alts[$src] ?? null);

        if ($known === null) {
            return $tag;
        }

        $current = preg_match('/\balt="([^"]*)"/i', $tag, $a) ? trim(html_entity_decode($a[1])) : null;
        $placeholder = $current === null || $current === '' || preg_match('/^[\w .-]+\.(jpe?g|png|gif|webp|bmp|svg)$/i', $current) === 1;

        if (! $known['reviewed'] && ! $placeholder) {
            return $tag;
        }

        $attribute = 'alt="'.htmlspecialchars($known['alt'], ENT_QUOTES).'"';

        return $current === null
            ? (string) preg_replace('/^<img\b/i', '<img '.$attribute, $tag)
            : (string) preg_replace('/\balt="[^"]*"/i', $attribute, $tag, 1);
    }

    /**
     * @return list<string>
     */
    private function htmlFiles(string $dir): array
    {
        $found = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() === 'html') {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    /**
     * Width and height for each URL, read from the first 128 KB of the file;
     * null where the file can't be read (a missing host, a broken file).
     *
     * @param  list<string>  $urls
     * @return array<string, array{0: int, 1: int}|null>
     */
    private function measure(array $urls): array
    {
        $sizes = [];
        $client = new Client(['timeout' => 20, 'http_errors' => false]);
        $requests = function () use ($urls) {
            foreach ($urls as $url) {
                yield new Request('GET', $url, ['Range' => 'bytes=0-131071']);
            }
        };

        (new Pool($client, $requests(), [
            'concurrency' => 16,
            'fulfilled' => function (Response $response, int $i) use ($urls, &$sizes) {
                $info = @getimagesizefromstring((string) $response->getBody());
                $sizes[$urls[$i]] = $info ? [$info[0], $info[1]] : null;
            },
            'rejected' => function ($reason, int $i) use ($urls, &$sizes) {
                $sizes[$urls[$i]] = null;
            },
        ]))->promise()->wait();

        return $sizes;
    }
}
