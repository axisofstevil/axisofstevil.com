<?php

namespace App;

/**
 * A work's body as the site renders it. The Markdown is kept as written;
 * two kinds of embedded media from 2005–2007 are translated:
 *
 * - `!#flash(...swf)`: the work's thumbnail, with a button that plays it in
 *   Ruffle (site.js) where scripts run.
 * - YouTube's old `<embed src="…youtube.com/v/ID">` (once with a stray `<`):
 *   a privacy-enhanced YouTube player.
 * - An `<embed>` of a movie file (.mov, .mp4, .m4v): a standard video player.
 *
 * And a YouTube address alone on its line (watch, youtu.be, embed, shorts or
 * live) is shown as its privacy-enhanced player.
 */
class WorkBody
{
    public static function clean(string $body, ?string $thumbnail, string $title): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        $body = preg_replace_callback('/!#flash\(([^)\s]+)\)/', function (array $m) use ($thumbnail, $title) {
            $picture = $thumbnail !== null
                ? '<img src="'.e($thumbnail).'" alt="'.e($title).', a Flash movie" loading="lazy">'
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
}
