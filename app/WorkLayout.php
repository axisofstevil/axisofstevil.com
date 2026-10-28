<?php

namespace App;

use DOMDocument;
use DOMElement;
use DOMNode;
use App\Services\ImageAttributes;
use DOMXPath;

/**
 * How a work's page is laid out, read from the shape of its body (rendered
 * HTML) and its division. The bodies follow a few reliable shapes:
 *
 * - plate: the picture is the work (comics; short Extras that open with a
 *   picture): the picture first, the title a label beneath it.
 * - movie: a short note and a player (Flash, short video Extras): the player
 *   first, the note as its caption.
 * - steve: an introduction, a portrait, the rest, then trailing pictures; the
 *   tall, narrow one is the film strip, set beside the text as it first ran.
 * - entry: a word (its heading), its pronunciation and definitions, an
 *   example and an illustration (Words).
 * - letter: a reader's question (the opening quote, signed) and Stevil's
 *   reply (Ask Stevil).
 * - article: everything else, read like a magazine piece; a picture that
 *   opens it leads the page. A body of pages (sections headed "## Page 2
 *   {#page-2}", a choose-your-own-adventure story) is a story: each page
 *   becomes a section, shown one at a time (site.js).
 *
 * Only the arrangement changes: every part of the body is kept, in order
 * within its part.
 */
class WorkLayout
{
    /**
     * Words under which a picture-led Extra is a plate with a caption, not an article.
     */
    private const PLATE_WORDS = 120;

    /**
     * @param  array<string, array{0: int, 1: int}>  $sizes  known picture sizes, by address
     * @return array{layout: string, lead: string, body: string, aside: string, quote: string, asker: string, slides: list<array{html: string, caption: string}>, dropCap: bool, story: bool}
     */
    public static function split(string $html, ?string $division, array $sizes = []): array
    {
        $parts = ['layout' => 'article', 'lead' => '', 'body' => $html, 'aside' => '', 'quote' => '', 'asker' => '', 'slides' => [], 'dropCap' => false, 'story' => false];

        if (trim($html) === '') {
            return $parts;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="work-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = (new DOMXPath($dom))->query('//div[@id="work-root"]')->item(0);

        if (! $root instanceof DOMElement) {
            return $parts;
        }

        /** @var list<DOMNode> $blocks */
        $blocks = [];
        foreach ($root->childNodes as $node) {
            if ($node instanceof DOMElement || trim((string) $node->textContent) !== '') {
                $blocks[] = $node;
            }
        }

        $kind = fn (DOMNode $node): string => match (true) {
            self::isMedia($node) => 'media',
            $node instanceof DOMElement && in_array($node->tagName, ['h1', 'h2', 'h3', 'h4'], true) => 'heading',
            $node instanceof DOMElement && $node->tagName === 'blockquote' => 'quote',
            default => 'text',
        };
        $kinds = array_map($kind, $blocks);
        $words = str_word_count(implode(' ', array_map(
            fn (DOMNode $node, string $k): string => $k === 'media' ? '' : (string) $node->textContent,
            $blocks,
            $kinds,
        )));
        $html = fn (array $nodes): string => implode("\n", array_map(fn (DOMNode $node): string => (string) $dom->saveHTML($node), $nodes));
        $player = fn (DOMNode $node): bool => $node instanceof DOMElement && $node->tagName === 'figure' && preg_match('/\b(flash|video)\b/', (string) $node->getAttribute('class')) === 1;

        // Words: the heading repeats the title, so the entry starts at its pronunciation.
        if ($division === 'language') {
            $media = array_keys(array_filter($kinds, fn (string $k): bool => $k === 'media'));
            $text = array_values(array_filter($blocks, fn (DOMNode $node, int $i): bool => $kinds[$i] !== 'media' && ! ($i === 0 && $kinds[$i] === 'heading'), ARRAY_FILTER_USE_BOTH));

            return [...$parts, 'layout' => 'entry', 'body' => $html($text), 'aside' => $html(array_map(fn (int $i): DOMNode => $blocks[$i], $media))];
        }

        // Ask Stevil: the question opens the piece, signed by the reader on its last line.
        if ($division === 'ask-stevil' && ($kinds[0] ?? null) === 'quote') {
            $question = $blocks[0];
            $lines = array_values(array_filter(iterator_to_array($question->childNodes), fn (DOMNode $node): bool => trim((string) $node->textContent) !== ''));
            // The page sets its own dash before the reader's name: theirs (-, --, ~) goes, and a row of dashes signs nothing.
            $asker = count($lines) > 1 ? trim((string) preg_replace('/^[\s\\\\\-~—–]+|[\s\-~—–]+$/u', '', (string) end($lines)->textContent)) : '';
            $quote = count($lines) > 1 ? $html(array_slice($lines, 0, -1)) : $html($lines);

            return [...$parts, 'layout' => 'letter', 'quote' => $quote, 'asker' => $asker, 'body' => $html(array_slice($blocks, 1))];
        }

        // A player with a short note: the player leads, the note captions it.
        $players = array_keys(array_filter($blocks, $player));
        if ($players !== [] && $words <= 80 && in_array($division, ['flash', 'extras'], true)) {
            $lead = array_map(fn (int $i): DOMNode => $blocks[$i], $players);
            $rest = array_values(array_filter($blocks, fn (DOMNode $node): bool => ! in_array($node, $lead, true)));

            return [...$parts, 'layout' => 'movie', 'lead' => $html($lead), 'body' => $html($rest)];
        }

        // The picture is the work: comics, and short Extras that open with a picture.
        if (($kinds[0] ?? null) === 'media' && ($division === 'comics' || ($division === 'extras' && $words <= self::PLATE_WORDS))) {
            $lead = [];
            foreach ($blocks as $i => $node) {
                if ($kinds[$i] !== 'media') {
                    break;
                }
                $lead[] = $node;
            }

            $body = $html(array_slice($blocks, count($lead)));

            // A comic of several pictures is a slideshow: its own order, a front-page teaser (or thumbnail) last.
            $images = $division === 'comics' ? self::images($lead) : [];
            if (count($images) > 1) {
                $teaser = fn (DOMElement $image): bool => preg_match('/(?:tease|\.thumb\.)/i', basename($image->getAttribute('src'))) === 1;
                usort($images, fn (DOMElement $a, DOMElement $b): int => $teaser($a) <=> $teaser($b));
                $slides = array_map(fn (DOMElement $image): array => ['html' => (string) $dom->saveHTML($image), 'caption' => $teaser($image) ? 'Front-page teaser' : ''], $images);

                return [...$parts, 'layout' => 'plate', 'slides' => $slides, 'body' => $body];
            }

            return [...$parts, 'layout' => 'plate', 'lead' => $html($lead), 'body' => $body];
        }

        // A Featured Steve: the pictures after the last paragraph trail it; the tall, narrow one is the
        // film strip. It often shares a paragraph with the photo before it, so each picture is judged alone.
        if ($division === 'featured-steves') {
            $last = max(array_keys(array_filter($kinds, fn (string $k): bool => $k !== 'media')) ?: [-1]);
            $strip = [];

            foreach (array_slice($blocks, $last + 1) as $node) {
                foreach (self::images([$node]) as $image) {
                    if (self::isTall($image, $sizes)) {
                        $strip[] = (string) $dom->saveHTML($image);
                        $image->parentNode?->removeChild($image);
                    }
                }
            }

            // A paragraph the strip leaves empty goes with it.
            $kept = array_values(array_filter($blocks, fn (DOMNode $node): bool => ! $node instanceof DOMElement || $node->tagName !== 'p' || trim((string) $node->textContent) !== '' || $node->getElementsByTagName('img')->length > 0));

            return [...$parts, 'layout' => 'steve', 'body' => $html($kept), 'aside' => implode("\n", $strip)];
        }

        // A story of pages: each heading named page-… and what follows it, as a section of that name.
        $pages = array_keys(array_filter($blocks, fn (DOMNode $node): bool => $node instanceof DOMElement && $node->tagName === 'h2' && str_starts_with($node->getAttribute('id'), 'page-')));

        if (count($pages) > 1) {
            $sections = [];
            $before = array_slice($blocks, 0, $pages[0]);

            foreach ($pages as $n => $start) {
                $end = $pages[$n + 1] ?? count($blocks);
                $heading = $blocks[$start];
                $id = $heading instanceof DOMElement ? $heading->getAttribute('id') : '';
                $heading instanceof DOMElement && $heading->removeAttribute('id');
                $sections[] = '<section class="story-page" id="'.htmlspecialchars($id).'" data-story-page>'.$html(array_slice($blocks, $start, $end - $start)).'</section>';
            }

            return [...$parts, 'body' => $html($before).implode("\n", $sections), 'story' => true];
        }

        // An article: a picture that opens it leads the page. A drop cap only when it opens on a
        // paragraph of a few lines that starts with a letter (not a one-line note, a quote or a date).
        $leads = ($kinds[0] ?? null) === 'media' && ! $player($blocks[0]);
        $opening = $blocks[$leads ? 1 : 0] ?? null;
        $dropCap = $opening instanceof DOMElement && $opening->tagName === 'p'
            && mb_strlen(trim((string) $opening->textContent)) >= 200
            && preg_match('/^\p{L}/u', trim((string) $opening->textContent)) === 1;

        if ($leads) {
            return [...$parts, 'lead' => $html([$blocks[0]]), 'body' => $html(array_slice($blocks, 1)), 'dropCap' => $dropCap];
        }

        return [...$parts, 'dropCap' => $dropCap];
    }

    /**
     * The picture sizes ImageAttributes measured on earlier builds.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function knownSizes(): array
    {
        static $sizes = null;
        $path = dirname(__DIR__).'/'.ImageAttributes::CACHE;

        return $sizes ??= is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    /**
     * Every picture in the blocks, in order.
     *
     * @param  list<DOMNode>  $blocks
     * @return list<DOMElement>
     */
    private static function images(array $blocks): array
    {
        $images = [];

        foreach ($blocks as $node) {
            if ($node instanceof DOMElement) {
                foreach ($node->tagName === 'img' ? [$node] : iterator_to_array($node->getElementsByTagName('img')) as $image) {
                    $images[] = $image;
                }
            }
        }

        return $images;
    }

    /**
     * A paragraph holding only pictures, a figure, or a player.
     */
    private static function isMedia(DOMNode $node): bool
    {
        if (! $node instanceof DOMElement) {
            return false;
        }

        if (in_array($node->tagName, ['figure', 'img', 'video', 'iframe'], true)) {
            return true;
        }

        if ($node->tagName !== 'p' || $node->getElementsByTagName('img')->length === 0) {
            return false;
        }

        $text = trim((string) preg_replace('/\s+/u', '', (string) $node->textContent));

        return $text === '';
    }

    /**
     * Twice as tall as it is wide, by its size (measured now if no build has yet), or named as a strip
     * (…bar.jpg, …mural.jpg) when it can't be measured.
     *
     * @param  array<string, array{0: int, 1: int}>  $sizes
     */
    private static function isTall(DOMNode $node, array $sizes): bool
    {
        if (! $node instanceof DOMElement) {
            return false;
        }

        $image = $node->tagName === 'img' ? $node : $node->getElementsByTagName('img')->item(0);
        $src = $image instanceof DOMElement ? $image->getAttribute('src') : '';

        // Not measured yet (a fresh checkout): measure it now.
        if (! isset($sizes[$src][0]) && preg_match('/^https?:\/\//', $src) === 1) {
            $size = @getimagesize($src);
            $sizes[$src] = $size === false ? [0, 0] : [$size[0], $size[1]];
        }

        if (isset($sizes[$src][0], $sizes[$src][1]) && $sizes[$src][0] > 0) {
            return $sizes[$src][1] >= 2 * $sizes[$src][0];
        }

        return preg_match('/(?:bar|mural)\.(?:jpe?g|png|gif)$/i', $src) === 1;
    }
}
