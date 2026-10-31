<?php

namespace App;

/**
 * A work's body as the site renders it. The Markdown is kept as written;
 * two kinds of embedded media from 2005–2007 are translated:
 *
 * - `!#flash(...swf)`: a picture of the movie with a button that plays it in
 *   Ruffle (site.js) where scripts run. The picture is the movie's own still
 *   (from a Flash work that is just that movie), else the work's lead image if
 *   the body doesn't already show it, else a placeholder in the movie's
 *   proportions.
 * - YouTube's old `<embed src="…youtube.com/v/ID">` (once with a stray `<`):
 *   a privacy-enhanced YouTube player.
 * - An `<embed>` of a movie file (.mov, .mp4, .m4v): a standard video player.
 *
 * And a YouTube address alone on its line (watch, youtu.be, embed, shorts or
 * live) is shown as its privacy-enhanced player.
 */
class WorkBody
{
    /**
     * @param  array<string, string>  $stills  each movie's own still, by its address
     */
    public static function clean(string $body, ?string $thumbnail, string $title, array $stills = []): string
    {
        // The lead image stands in for a movie only where the body doesn't show it already.
        $lead = $thumbnail !== null && ! str_contains($body, $thumbnail) ? $thumbnail : null;

        $body = str_replace(["\r\n", "\r"], "\n", $body);

        $body = preg_replace_callback('/!#flash\(([^)\s]+)\)/', function (array $m) use ($lead, $title, $stills) {
            $still = $stills[$m[1]] ?? $lead;
            $picture = $still !== null
                ? '<img src="'.e($still).'" alt="'.e($title).', a Flash movie" loading="lazy">'
                : '';

            // No picture to stand in for it: a placeholder in the movie's proportions (from its name, "400x120"), else 4:3.
            $ratio = preg_match('/(\d{2,4})x(\d{2,4})/', basename($m[1]), $size) === 1 ? $size[1].' / '.$size[2] : '4 / 3';
            $stage = $picture === '' ? '<div class="flash-stage flash-stage--blank" style="aspect-ratio: '.$ratio.'" data-ratio="'.e($ratio).'">' : '<div class="flash-stage">';

            return "\n\n".'<figure class="flash" data-swf="'.e($m[1]).'">'.$stage.$picture.'<button type="button" class="flash-play">Play the Flash movie</button></div></figure>'."\n\n";
        }, $body);

        $body = (string) preg_replace_callback(
            '/<+embed[^>]*src="(https?:\/\/[^"]+\.(?:mov|mp4|m4v))"[^>]*>(?:\s*<\/embed>)?/i',
            fn (array $m) => "\n\n".'<figure class="video"><video src="'.e($m[1]).'" controls preload="none" title="'.e($title).'"></video></figure>'."\n\n",
            $body,
        );

        $player = fn (array $m) => "\n\n".'<figure class="video"><iframe src="https://www.youtube-nocookie.com/embed/'.$m[1].'" title="'.e($title).'" loading="lazy" allowfullscreen></iframe></figure>'."\n\n";

        $body = (string) preg_replace_callback(
            '/<+embed[^>]*src="https?:\/\/(?:www\.)?youtube\.com\/v\/([\w-]{6,})[^"]*"[^>]*>(?:\s*<\/embed>)?/i',
            $player,
            $body,
        );

        return (string) preg_replace_callback(
            '/^[ \t]*<?(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:[^\s>]*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([\w-]{11})[^\s>]*>?[ \t]*$/im',
            $player,
            $body,
        );
    }

    /**
     * Each picture shown once: where a piece shows a picture and another size
     * of it too (the archive's sidecars: name.thumb.jpg, name.large.jpg,
     * name.1280x960.jpg beside name.jpg), only the picture itself is kept, with
     * whatever paragraph or link held only the other. Different pictures
     * (name.alt.jpg) and links to other sizes stay.
     */
    public static function oneSize(string $html): string
    {
        $sidecar = '/\.(?:thumb|large|\d{2,4}x\d{2,4})(?=\.[a-z0-9]+$)/i';

        if (preg_match_all('/<img\b[^>]*\bsrc="([^"]+)"/i', $html, $m) < 2) {
            return $html;
        }

        $shown = array_flip($m[1]);
        $drop = array_values(array_filter($m[1], fn (string $src) => preg_match($sidecar, $src) === 1 && isset($shown[(string) preg_replace($sidecar, '', $src)])));

        foreach ($drop as $src) {
            $q = preg_quote($src, '#');
            // The picture, with a link or paragraph that held nothing else.
            $html = (string) preg_replace([
                '#<p>\s*(?:<a\b[^>]*>\s*)?<img\b[^>]*\bsrc="'.$q.'"[^>]*>\s*(?:</a>\s*)?</p>#i',
                '#(?:<a\b[^>]*>\s*)?<img\b[^>]*\bsrc="'.$q.'"[^>]*>(?:\s*</a>)?#i',
            ], '', $html, 1);
        }

        return $html;
    }
}
