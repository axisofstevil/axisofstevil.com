<?php

namespace App;

/**
 * A work's body as the site renders it. The Markdown is kept as written;
 * two kinds of embedded media from 2005–2007 are translated:
 *
 * - `!#flash(...swf)`: shown as the work's thumbnail, labelled as a Flash
 *   movie, until Flash playback is decided.
 * - YouTube's old `<embed src="…youtube.com/v/ID">` (once with a stray `<`):
 *   a privacy-enhanced YouTube player.
 * - An `<embed>` of a movie file (.mov, .mp4, .m4v): a standard video player.
 */
class WorkBody
{
    public static function clean(string $body, ?string $thumbnail, string $title): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        $body = preg_replace_callback('/!#flash\(([^)\s]+)\)/', function () use ($thumbnail, $title) {
            $picture = $thumbnail !== null
                ? '<img src="'.e($thumbnail).'" alt="'.e($title).', a Flash movie" loading="lazy">'
                : '';

            return "\n\n".'<figure class="flash">'.$picture.'<figcaption>Flash movie</figcaption></figure>'."\n\n";
        }, $body);

        $body = (string) preg_replace_callback(
            '/<+embed[^>]*src="(https?:\/\/[^"]+\.(?:mov|mp4|m4v))"[^>]*>(?:\s*<\/embed>)?/i',
            fn (array $m) => "\n\n".'<figure class="video"><video src="'.e($m[1]).'" controls preload="none" title="'.e($title).'"></video></figure>'."\n\n",
            $body,
        );

        return (string) preg_replace_callback(
            '/<+embed[^>]*src="https?:\/\/(?:www\.)?youtube\.com\/v\/([\w-]{6,})[^"]*"[^>]*>(?:\s*<\/embed>)?/i',
            fn (array $m) => "\n\n".'<figure class="video"><iframe src="https://www.youtube-nocookie.com/embed/'.$m[1].'" title="'.e($title).'" loading="lazy" allowfullscreen></iframe></figure>'."\n\n",
            $body,
        );
    }
}
