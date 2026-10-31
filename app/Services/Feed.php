<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use TightenCo\Jigsaw\Jigsaw;

/**
 * The feed (/feed.xml, RSS 2.0, where the old site's was): the 25 newest
 * works, each with its lead picture, its description and a link to its
 * page, dated the day it was filed in the publication's own time zone.
 */
class Feed
{
    public const int ITEMS = 25;

    public function handle(Jigsaw $jigsaw): void
    {
        $base = rtrim((string) $jigsaw->getConfig('baseUrl'), '/');
        $zone = (string) ($jigsaw->getConfig('timezone') ?: 'UTC');
        $built = $jigsaw->getConfig('build_time');
        $built = ($built instanceof CarbonImmutable ? $built : CarbonImmutable::now())->setTimezone('UTC');
        $works = iterator_to_array($jigsaw->getCollection('works') ?? []);

        usort($works, fn ($a, $b) => [(string) $b->date, (string) $b->title] <=> [(string) $a->date, (string) $a->title]);

        $items = array_map(function ($work) use ($base, $zone): string {
            $url = $base.$work->link();
            $html = ($work->image ? '<p><img src="'.htmlspecialchars((string) $work->image, ENT_QUOTES).'" alt="'.htmlspecialchars((string) $work->title, ENT_QUOTES).'"></p>' : '')
                .($work->description ? '<p>'.htmlspecialchars((string) $work->description, ENT_QUOTES).'</p>' : '')
                .'<p><a href="'.htmlspecialchars($url, ENT_QUOTES).'">'.htmlspecialchars((string) $work->title, ENT_QUOTES).'</a> on The Axis of Stevil.</p>';

            return implode("\n", [
                '    <item>',
                '      <title>'.htmlspecialchars((string) $work->title, ENT_XML1).'</title>',
                '      <link>'.htmlspecialchars($url, ENT_XML1).'</link>',
                '      <guid isPermaLink="true">'.htmlspecialchars($url, ENT_XML1).'</guid>',
                '      <pubDate>'.CarbonImmutable::parse((string) $work->date, $zone)->toRssString().'</pubDate>',
                ($work->division_name ? '      <category>'.htmlspecialchars((string) $work->division_name, ENT_XML1).'</category>' : null),
                '      <description>'.htmlspecialchars($html, ENT_XML1).'</description>',
                '    </item>',
            ]);
        }, array_slice($works, 0, self::ITEMS));

        $xml = implode("\n", array_filter([
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">',
            '  <channel>',
            '    <title>'.htmlspecialchars((string) $jigsaw->getConfig('title'), ENT_XML1).'</title>',
            '    <description>'.htmlspecialchars((string) $jigsaw->getConfig('tagline'), ENT_XML1).'</description>',
            '    <link>'.$base.'/</link>',
            '    <atom:link href="'.$base.'/feed.xml" rel="self" type="application/rss+xml"/>',
            '    <language>en</language>',
            '    <lastBuildDate>'.$built->toRssString().'</lastBuildDate>',
            ...$items,
            '  </channel>',
            '</rss>',
        ], fn ($line) => $line !== null));

        file_put_contents($jigsaw->getDestinationPath().'/feed.xml', $xml."\n");
    }
}
