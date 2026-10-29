<?php

namespace App;

/**
 * A weekly issue composed as a front page (owner, 2026-10-28): the week's
 * pieces ranked by what they are, not when they ran, and set in rows whose
 * cells end together.
 *
 * - Rank: publications, Flash movies, Extras, comics, Ask Stevil, letters
 *   (Thurston's Log), Featured Steve, Language; within a rank, the newest
 *   first (the order the week ran them).
 * - The lead: the top piece, with the next pieces of writing stacked beside
 *   it, the stack as tall as the lead.
 * - The rest, in rank order, packed into rows. Each row is solved as type is
 *   set: every cell's height is a function of its width (a picture: its width
 *   over its shape, never past its own size; writing: its area over its
 *   width, plus its heading and any pictures it carries; a story: as tall as
 *   asked, its own picture cropped to take the slack), and the row is given
 *   the one height at which the widths fill it. Of the groupings the next few
 *   pieces allow, the row that solves best is set.
 * - A short notice from the offices runs as the issue's note, under the
 *   masthead.
 *
 * Widths are worked out for the sheet at a desktop width and set as the
 * cells' proportions, so they hold as the sheet narrows; on a phone every
 * cell takes the full width.
 */
class IssueLayout
{
    public const array RANK = [
        'publications' => 1,
        'flash' => 2,
        'extras' => 3,
        'comics' => 4,
        'ask-stevil' => 5,
        'letters' => 6,
        'featured-steves' => 7,
        'language' => 8,
    ];

    /** The row's content width the solution is worked out for (the sheet at a common desktop width). */
    private const float WIDTH = 1300;

    /** The space a cell takes beside its neighbour: the gap, and a rule's padding. */
    private const float GUTTER = 44;

    /** A figure's caption beneath its picture. */
    private const float CAPTION = 52;

    /** Square pixels of the page one word of a summary or letter fills (17px Source Serif, 1.55 leading). */
    private const float WORD = 1300;

    /** The narrowest column writing is set in. */
    private const float NARROW = 240;

    /**
     * Heights, widths and parts already worked out for this issue (each is
     * asked for many times while its rows are solved).
     *
     * @var array<string, mixed>
     */
    private static array $memo = [];

    /**
     * @param  list<array<string, mixed>>  $blocks  the week's blocks, in the order it ran them
     * @param  array<string, array{0: int, 1: int}>  $sizes  each picture's size, by address
     * @return array{note: list<array<string, mixed>>, rows: list<list<array<string, mixed>>>}
     */
    public static function compose(array $blocks, array $sizes): array
    {
        $note = [];
        $pieces = [];
        self::$memo = [];

        foreach ($blocks as $i => $block) {
            if (in_array($block['kind'], ['tune', 'archive'], true)) {
                continue;
            }

            if ($block['kind'] === 'notice' && ($block['words'] ?? 0) <= 60) {
                $note[] = $block;

                continue;
            }

            $pieces[] = self::cell($block, $i, $sizes);
        }

        usort($pieces, fn ($a, $b) => [$a['rank'], $a['at']] <=> [$b['rank'], $b['at']]);

        if ($pieces === []) {
            return ['note' => $note, 'rows' => []];
        }

        $rows = [self::leadRow($pieces)];

        // The rest, in rank order: of the next few pieces, the grouping that solves best.
        while ($pieces !== []) {
            $best = null;

            foreach (self::groupings($pieces) as $take) {
                $row = array_map(fn ($k) => $pieces[$k], $take);
                $solved = self::solve($row);

                if ($best === null || $solved['score'] < $best['score']) {
                    $best = [...$solved, 'take' => $take];
                }
            }

            $rows[] = $best['cells'];
            $pieces = array_values(array_diff_key($pieces, array_flip($best['take'])));
        }

        return ['note' => $note, 'rows' => array_map(fn ($row) => array_map(fn ($c) => self::forPage($c), $row), $rows)];
    }

    /**
     * The lead and the writing stacked beside it, the stack as near the
     * lead's height as its width allows.
     *
     * @param  list<array<string, mixed>>  $pieces  ranked; the lead and its stack are taken from it
     * @return list<array<string, mixed>>
     */
    private static function leadRow(array &$pieces): array
    {
        $lead = array_shift($pieces);
        $lead['lead'] = true;
        $stack = [];
        $words = 0;

        // Stories and writing without pictures, in rank order, while the stack stays no taller than the lead.
        $leadHeight = self::height($lead, self::WIDTH - 420 - self::GUTTER);
        $stackHeight = 0;

        foreach ($pieces as $k => $piece) {
            $fits = $piece['type'] === 'story' || ($piece['type'] === 'text' && $piece['pictures'] === []);
            $height = self::height($piece, 420) + ($stack === [] ? 0 : 36);

            if ($fits && count($stack) < 3 && $stackHeight + $height <= $leadHeight + 40) {
                $stack[] = $piece;
                $stackHeight += $height;
                $words += $piece['words'];
                unset($pieces[$k]);
            }
        }

        $pieces = array_values($pieces);

        if ($stack === []) {
            $solved = self::solve($pieces === [] ? [$lead] : [$lead, array_shift($pieces)]);

            return $solved['cells'];
        }

        // The stack's width: where its height comes nearest the lead's at the width left to it.
        $best = null;

        for ($w = 300; $w <= 560; $w += 10) {
            $stackHeight = array_sum(array_map(fn ($c) => self::height($c, $w), $stack)) + 36 * (count($stack) - 1);
            $leadHeight = self::height($lead, self::WIDTH - $w - self::GUTTER);
            $miss = abs($stackHeight - $leadHeight) + ($stackHeight > $leadHeight ? ($stackHeight - $leadHeight) : 0);

            if ($best === null || $miss < $best['miss']) {
                $best = ['miss' => $miss, 'width' => $w];
            }
        }

        $lead['width'] = self::WIDTH - $best['width'] - self::GUTTER;

        return [$lead, ['type' => 'stack', 'cells' => $stack, 'width' => $best['width'], 'rank' => 0, 'words' => $words]];
    }

    /**
     * The groupings a row may take from the front of the ranked pieces: the
     * next one to four, and the next one to three with the first piece of
     * writing further on (so pictures that can't fill a row have words beside them).
     *
     * @param  list<array<string, mixed>>  $pieces
     * @return list<list<int>>
     */
    private static function groupings(array $pieces): array
    {
        $options = [];
        $count = count($pieces);

        for ($n = 1; $n <= min(4, $count); $n++) {
            $options[] = range(0, $n - 1);
        }

        foreach ($pieces as $k => $piece) {
            if ($k >= 3 && $piece['type'] !== 'whole') {
                for ($n = 1; $n <= min(3, $count); $n++) {
                    $options[] = [...range(0, $n - 1), $k];
                }

                break;
            }
        }

        return $options;
    }

    /**
     * A row solved: the shared height at which its cells' widths fill it, each
     * cell's width, and how good a row that makes (lower is better).
     *
     * @param  list<array<string, mixed>>  $row
     * @return array{cells: list<array<string, mixed>>, score: float}
     */
    private static function solve(array $row): array
    {
        $space = self::WIDTH - self::GUTTER * (count($row) - 1);
        $best = null;

        // Every height in coarse steps, then finely around the best of them.
        $heights = range(160, 1600, 24);
        $tried = [];

        for ($pass = 0; $pass < 2; $pass++) {
            if ($pass === 1) {
                $heights = range(max(160, $best['height'] - 24), min(1600, $best['height'] + 24), 4);
            }

            foreach ($heights as $h) {
                if (isset($tried[$h])) {
                    continue;
                }

                $tried[$h] = true;
                $widths = array_map(fn ($c) => self::widthAt($c, $h), $row);
                $fixed = array_sum(array_filter($widths, fn ($w) => $w !== null));
                $elastic = array_keys(array_filter($widths, fn ($w) => $w === null));

                // Stories take what's left between them; each must still fit at that width and height.
                if ($elastic !== []) {
                    $each = ($space - $fixed) / count($elastic);

                    foreach ($elastic as $k) {
                        $widths[$k] = $each;
                    }

                    $fits = $each >= self::NARROW && array_reduce($elastic, fn ($ok, $k) => $ok && self::height($row[$k], $each) <= $h + 4, true);
                    $miss = $fits ? 0 : 1000;
                } else {
                    $miss = abs($space - $fixed);
                }

                $score = $miss + self::penalty($row, $widths, $h, $space);

                if ($best === null || $score < $best['score']) {
                    $best = ['score' => $score, 'widths' => $widths, 'height' => $h];
                }
            }
        }

        // Fewer, fuller rows read better than many thin ones; a lone piece is a last resort.
        $score = $best['score'] + (count($row) === 1 ? 260 : 0) - 30 * count($row);
        $cells = array_map(fn ($c, $w) => [...$c, 'width' => max(1, $w)], $row, $best['widths']);

        return ['cells' => $cells, 'score' => $score];
    }

    /**
     * What a solution costs beyond its fit: writing set too narrow or too
     * wide, pictures left short of the row's height (they never grow past
     * their own size), widths that don't add up.
     *
     * @param  list<array<string, mixed>>  $row
     * @param  list<float|null>  $widths
     */
    private static function penalty(array $row, array $widths, float $h, float $space): float
    {
        $penalty = abs(array_sum($widths) - $space) * 0.5;

        foreach ($row as $k => $c) {
            $w = (float) $widths[$k];

            if ($c['type'] === 'whole') {
                // Held at its own size: the height it falls short of the row by is empty page.
                $short = $h - self::height($c, $w);
                $penalty += max(0, $short) * $w / 400;
            } else {
                $penalty += $w < self::NARROW ? (self::NARROW - $w) * 4 : 0;
                $penalty += $w > 760 ? ($w - 760) * 0.6 : 0;
                // Writing shorter than the row (it doesn't stretch, unless it's a story) leaves a gap beneath it.
                $penalty += in_array($c['type'], ['text', 'group'], true) ? max(0, $h - self::height($c, $w)) * $w / 500 : 0;
            }
        }

        return $penalty;
    }

    /**
     * The width a cell takes to stand exactly as tall as the row, or null
     * for a story (it takes the width left).
     *
     * @param  array<string, mixed>  $c
     */
    private static function widthAt(array $c, float $h): ?float
    {
        return match ($c['type']) {
            'whole' => min($c['natural'], max(0, ($h - self::CAPTION) * $c['ratio'])),
            'story' => null,
            default => self::textWidth($c, $h),
        };
    }

    /**
     * The width at which writing (and the pictures it carries) stands as tall
     * as the row, by halving between the narrowest and widest it may be.
     *
     * @param  array<string, mixed>  $c
     */
    private static function textWidth(array $c, float $h): float
    {
        return self::$memo['width|'.$c['at'].'|'.(int) $h] ??= self::searchWidth($c, $h);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private static function searchWidth(array $c, float $h): float
    {
        [$low, $high] = [120.0, self::WIDTH];

        for ($i = 0; $i < 30; $i++) {
            $mid = ($low + $high) / 2;
            // Taller than the row at this width: it needs more width (until its pictures make it taller again).
            self::height($c, $mid) > $h ? $low = $mid : $high = $mid;
        }

        return $high;
    }

    /**
     * A cell's height at a width.
     *
     * @param  array<string, mixed>  $c
     */
    private static function height(array $c, float $w): float
    {
        $w = max(60, $w);

        return isset($c['at']) ? self::$memo['height|'.$c['at'].'|'.($c['lead'] ? 'lead' : '').'|'.(int) round($w)] ??= self::measure($c, $w) : self::measure($c, $w);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private static function measure(array $c, float $w): float
    {
        $headline = fn (float $size) => ceil(mb_strlen((string) $c['block']['title']) * $size * 0.55 / $w) * $size * 1.05;

        return match ($c['type']) {
            'whole' => min($w, $c['natural']) / $c['ratio'] + self::CAPTION,
            'group' => 34 + $headline(22) + self::partsAt($c['parts'], $w)['height'],
            'part-whole' => min($w, $c['natural']) / $c['ratio'],
            'part-text' => $c['words'] * self::WORD * 0.8 / $w,
            'story' => ($c['lead'] ?? false)
                ? 30 + $headline(54) + 320 + $c['words'] * self::WORD / $w + 40
                : 30 + $headline(30) + 140 + $c['words'] * self::WORD / $w + 40,
            default => 34 + ($c['titled'] ? $headline(22) : 0) + $c['words'] * self::WORD * 0.8 / $w
                + array_sum(array_map(fn ($p) => min($w, $p[0]) * $p[1] / max(1, $p[0]) + 12, $c['pictures'])),
        };
    }

    /**
     * A piece's parts set side by side in a width: the shared height at which
     * their widths fill it (pictures never past their own size), and each
     * part's width.
     *
     * @param  list<array<string, mixed>>  $parts
     * @return array{height: float, widths: list<float>}
     */
    private static function partsAt(array $parts, float $width): array
    {
        return self::$memo['parts|'.md5(serialize(array_column($parts, 'html'))).'|'.(int) round($width)] ??= self::setParts($parts, $width);
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @return array{height: float, widths: list<float>}
     */
    private static function setParts(array $parts, float $width): array
    {
        $space = $width - 24 * (count($parts) - 1);
        $best = null;
        $at = fn (float $h) => array_map(fn ($p) => $p['type'] === 'whole'
            ? min($p['natural'], $h * $p['ratio'])
            : max(self::NARROW * 0.75, $p['words'] * self::WORD * 0.8 / $h), $parts);

        // Coarse steps, then fine around the best.
        foreach ([range(60, 1600, 30), null] as $heights) {
            $heights ??= range(max(60, $best['height'] - 30), min(1600, $best['height'] + 30), 3);

            foreach ($heights as $h) {
                $widths = $at((float) $h);
                $miss = abs(array_sum($widths) - $space);

                if ($best === null || $miss < $best['miss']) {
                    $best = ['miss' => $miss, 'height' => (float) $h, 'widths' => $widths];
                }
            }
        }

        // Each part's height at its width: the tallest is the row's.
        $height = max(array_map(fn ($p, $w) => $p['type'] === 'whole' ? min($w, $p['natural']) / $p['ratio'] : $p['words'] * self::WORD * 0.8 / max(60, $w), $parts, $best['widths']));

        return ['height' => $height, 'widths' => $best['widths']];
    }

    /**
     * A piece's parts in order: each run of pictures one part (whole, its
     * shape and size), each run of words another.
     *
     * @param  array<string, array{0: int, 1: int}>  $sizes
     * @return list<array<string, mixed>>
     */
    private static function parts(string $html, array $sizes): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $root = $dom->documentElement;
        $parts = [];

        foreach ($root?->childNodes ?? [] as $node) {
            if ($node instanceof \DOMText && trim($node->textContent) === '') {
                continue;
            }

            $markup = (string) $dom->saveHTML($node);
            $picture = $node instanceof \DOMElement && (in_array($node->nodeName, ['figure', 'img'], true) || (trim($node->textContent) === '' && str_contains($markup, '<img')));
            $type = $picture ? 'whole' : 'text';
            $last = array_key_last($parts);

            if ($last !== null && $parts[$last]['type'] === $type) {
                $parts[$last]['html'] .= $markup;
            } else {
                $parts[] = ['type' => $type, 'html' => $markup];
            }
        }

        return array_map(function (array $part) use ($sizes) {
            if ($part['type'] === 'text') {
                return [...$part, 'words' => str_word_count(strip_tags($part['html']))];
            }

            $media = self::media(['html' => $part['html'], 'kind' => 'part'], $sizes);
            $width = $media === [] ? 600 : max(array_map(fn ($m) => $m[0], $media));
            $height = $media === [] ? 450 : array_sum(array_map(fn ($m) => $m[1] * $width / max(1, $m[0]), $media));

            return [...$part, 'ratio' => $width / max(1, $height), 'natural' => (float) $width];
        }, $parts);
    }

    /**
     * A piece as a cell: a whole piece (pictures or a movie, few words: its
     * shape and its own width), a story (its words; its own picture takes the
     * slack), or writing (its words and the pictures it carries).
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, array{0: int, 1: int}>  $sizes
     * @return array<string, mixed>
     */
    private static function cell(array $block, int $at, array $sizes): array
    {
        // A re-feature (an older piece the week ran again) ranks after every new piece.
        $rank = (self::RANK[$block['section'] ?? ''] ?? 9) + (($block['refeature'] ?? false) ? 20 : 0);
        $base = ['block' => $block, 'rank' => $rank, 'at' => $at, 'words' => (int) ($block['words'] ?? 0), 'lead' => false];
        $media = self::media($block, $sizes);

        if (in_array($block['kind'], ['plate', 'strip'], true) && $base['words'] < 25 && $media !== []) {
            $width = max(array_map(fn ($m) => $m[0], $media));
            $height = array_sum(array_map(fn ($m) => $m[1] * $width / max(1, $m[0]), $media)) + 6 * (count($media) - 1);

            return [...$base, 'type' => 'whole', 'ratio' => $width / max(1, $height), 'natural' => (float) $width];
        }

        if ($block['kind'] === 'story') {
            return [...$base, 'type' => 'story'];
        }

        // Pictures and words in turn (a picture, its words, a picture): its parts, in order, side by side.
        $parts = self::parts((string) $block['html'], $sizes);

        if (count($parts) > 1 && count(array_filter($parts, fn ($p) => $p['type'] === 'whole')) > 0 && count(array_filter($parts, fn ($p) => $p['type'] === 'text')) > 0) {
            return [...$base, 'type' => 'group', 'parts' => $parts];
        }

        return [...$base, 'type' => 'text', 'pictures' => $media, 'titled' => ! in_array($block['kind'], ['letter', 'language'], true)];
    }

    /**
     * The pictures and movies a block shows, each as its own size.
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, array{0: int, 1: int}>  $sizes
     * @return list<array{0: int, 1: int}>
     */
    private static function media(array $block, array $sizes): array
    {
        $html = (string) $block['html'];
        $media = [];

        // Movies: their size from the file's name, else their still, else 550 by 413.
        $html = (string) preg_replace_callback('#<figure class="flash" data-swf="([^"]+)">(.*?)</figure>#s', function (array $m) use (&$media, $sizes) {
            $still = preg_match('/<img[^>]+src="([^"]+)"/', $m[2], $img) === 1 ? ($sizes[html_entity_decode($img[1])] ?? null) : null;
            $named = preg_match('/(\d{2,4})x(\d{2,4})/', basename($m[1]), $size) === 1 ? [(int) $size[1], (int) $size[2]] : null;
            $media[] = $named ?? $still ?? [550, 413];

            return '';
        }, $html);

        if (preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $images) > 0) {
            foreach ($images[1] as $src) {
                $media[] = $sizes[html_entity_decode($src)] ?? [600, 450];
            }
        }

        // A whole piece that carries no picture of its own shows the work's.
        if ($media === [] && in_array($block['kind'], ['plate', 'strip'], true) && ($block['image'] ?? null) !== null) {
            $media[] = $sizes[$block['image']] ?? [600, 450];
        }

        return array_map(fn ($m) => [(int) $m[0], (int) $m[1]], $media);
    }

    /**
     * A cell as the page sets it: its block, its kind, and its share of the
     * row (its solved width).
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private static function forPage(array $c): array
    {
        if ($c['type'] === 'stack') {
            return ['type' => 'stack', 'grow' => round($c['width']), 'cells' => array_map(fn ($inner) => self::forPage([...$inner, 'width' => $c['width']]), $c['cells'])];
        }

        if ($c['type'] === 'group') {
            $set = self::partsAt($c['parts'], (float) $c['width']);

            return [
                'type' => 'group',
                'block' => $c['block'],
                'lead' => false,
                'grow' => round($c['width']),
                'parts' => array_map(fn ($p, $w) => ['type' => $p['type'], 'html' => $p['html'], 'grow' => round($w), 'natural' => (int) ($p['natural'] ?? 0)], $c['parts'], $set['widths']),
            ];
        }

        return [
            'type' => $c['type'] === 'whole' ? 'whole' : 'text',
            'block' => $c['block'],
            'lead' => $c['lead'] ?? false,
            'grow' => round($c['width']),
            'natural' => (int) ($c['natural'] ?? 0),
        ];
    }
}
