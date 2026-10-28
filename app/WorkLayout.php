<?php

namespace App;

use DOMDocument;
use DOMElement;
use DOMNode;
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
 *   opens it leads the page.
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
     * @return array{layout: string, lead: string, body: string, aside: string, quote: string, asker: string}
     */
    public static function split(string $html, ?string $division, array $sizes = []): array
    {
        $parts = ['layout' => 'article', 'lead' => '', 'body' => $html, 'aside' => '', 'quote' => '', 'asker' => ''];

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
            $asker = count($lines) > 1 ? trim((string) end($lines)->textContent) : '';
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

            return [...$parts, 'layout' => 'plate', 'lead' => $html($lead), 'body' => $html(array_slice($blocks, count($lead)))];
        }

        // A Featured Steve: the pictures after the last paragraph trail it; the tall, narrow one is the film strip.
        if ($division === 'featured-steves') {
            $last = max(array_keys(array_filter($kinds, fn (string $k): bool => $k !== 'media')) ?: [-1]);
            $trailing = array_slice($blocks, $last + 1);
            $strip = array_values(array_filter($trailing, fn (DOMNode $node): bool => self::isTall($node, $sizes)));
            $kept = array_values(array_filter($blocks, fn (DOMNode $node): bool => ! in_array($node, $strip, true)));

            return [...$parts, 'layout' => 'steve', 'body' => $html($kept), 'aside' => $html($strip)];
        }

        // An article: a picture that opens it leads the page.
        if (($kinds[0] ?? null) === 'media' && ! $player($blocks[0])) {
            return [...$parts, 'lead' => $html([$blocks[0]]), 'body' => $html(array_slice($blocks, 1))];
        }

        return $parts;
    }

    /**
     * The picture sizes ImageAttributes measured on earlier builds.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function knownSizes(): array
    {
        static $sizes = null;
        $path = dirname(__DIR__).'/cache/image-sizes.json';

        return $sizes ??= is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
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
     * Twice as tall as it is wide, by its known size, or named as a strip (…bar.jpg) when its size isn't known.
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

        if (isset($sizes[$src][0], $sizes[$src][1]) && $sizes[$src][0] > 0) {
            return $sizes[$src][1] >= 2 * $sizes[$src][0];
        }

        return preg_match('/bar\.(?:jpe?g|png|gif)$/i', $src) === 1;
    }
}
