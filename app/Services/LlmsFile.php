<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use TightenCo\Jigsaw\Jigsaw;

/**
 * The guide for language models (/llms.txt, per llmstxt.org): short enough
 * to read whole. It opens in the owner's own words (the channel's page at
 * llms.txt; a few generated lines when there is none), then names the
 * report's sections and the organisation's pages, one line each with what
 * it holds and how much, points to the sitemap for every address, and ends
 * with when it was written. The works themselves are the sitemap's to list.
 */
class LlmsFile
{
    public function handle(Jigsaw $jigsaw): void
    {
        $base = rtrim((string) $jigsaw->getConfig('baseUrl'), '/');
        $works = $this->rows($jigsaw->getConfig('works'));
        $issues = $this->rows($jigsaw->getConfig('issues'));
        $divisions = $this->rows($jigsaw->getConfig('divisions'));
        $divisionPages = iterator_to_array($jigsaw->getCollection('divisions') ?? []);
        $organisation = iterator_to_array($jigsaw->getCollection('organisation') ?? []);
        $sections = [...iterator_to_array($jigsaw->getCollection('gallery_pages') ?? []), ...iterator_to_array($jigsaw->getCollection('tune_pages') ?? [])];

        $link = fn (string $name, string $address, ?string $note = null) => "- [{$name}]({$base}{$address})".($note === null || $note === '' ? '' : ': '.$this->line($note));
        $month = fn (string $date) => CarbonImmutable::parse($date)->format('F Y');
        $dates = array_column($issues, 'starts_on');
        sort($dates);
        $span = $dates === [] ? null : $month($dates[0]).' to '.$month(end($dates));
        $plural = fn (int $n, string $one, string $many) => $n === 1 ? $one : $many;

        $own = trim(str_replace(["\r\n", "\r"], "\n", (string) $jigsaw->getConfig('llms')));

        $lines = $own !== '' ? [$own, ''] : [
            '# '.$jigsaw->getConfig('title'),
            '',
            '> '.$jigsaw->getConfig('tagline').' The complete works of the Axis of Stevil, a humour website founded in '.$jigsaw->getConfig('founded').', presented as an annual report to its shareholders.',
            '',
        ];

        // The report: every work by division (each division's page lists its works), the issues, the gallery, the tunes.
        $lines[] = '## The report';
        $lines[] = '';
        $lines[] = $link('All Works', '/works/', count($works).' works in '.count($divisions).' divisions, newest first; each has its own page');

        foreach ($divisionPages as $page) {
            if ($page->parent || $page->slug === 'works') {
                continue;
            }

            $series = array_values(array_filter($divisionPages, fn ($s) => $s->parent === $page->slug));
            $note = $page->count.' '.$plural((int) $page->count, 'work', 'works');

            if ($series !== []) {
                $note .= '. Series: '.implode(', ', array_map(fn ($s) => "[{$s->name}]({$base}{$s->address}) ({$s->count})", $series));
            }

            $lines[] = $link((string) $page->name, (string) $page->address, $note);
        }

        if ($issues !== []) {
            $lines[] = $link('Issues', '/issues/', count($issues).' weekly front pages'.($span ? ", {$span}" : '').', each carrying that week\'s works as it ran them');
        }

        foreach ($sections as $page) {
            if (! $page->parent) {
                $lines[] = $link((string) $page->name, (string) $page->address, $page->description);
            }
        }

        // The organisation's pages in the site's own order (mission first), each with its summary.
        $byAddress = [];

        foreach ($organisation as $page) {
            $byAddress[(string) $page->address] = $page;
        }

        if ($byAddress !== []) {
            $lines[] = '';
            $lines[] = '## The organisation';
            $lines[] = '';

            foreach ($this->rows($jigsaw->getConfig('organisation')) as $entry) {
                $page = $byAddress[$entry['address']] ?? null;
                $lines[] = $link((string) ($page->title ?? $entry['label']), (string) $entry['address'], $page?->description);
            }
        }

        $lines[] = '';
        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = $link('Sitemap', '/sitemap.xml', 'every page of the site, works and issues included, with the date each last changed');

        // When this was written: the site is rebuilt from its source of record, so the file is as current as the build.
        $built = $jigsaw->getConfig('build_time');
        $built = ($built instanceof CarbonImmutable ? $built : CarbonImmutable::now())->setTimezone('UTC');
        $lines[] = '';
        $lines[] = '## Currency of this file';
        $lines[] = '';
        $lines[] = 'Generated: '.$built->toIso8601String().' ('.$built->format('l, F j, Y \a\t H:i').' UTC).';
        $lines[] = '';
        $lines[] = 'This file was written when the site was last built, from the site\'s source of record, and is current as of that moment. The site is rebuilt whenever it changes, and each build replaces this file, so what is here is the current state of the site.';

        file_put_contents($jigsaw->getDestinationPath().'/llms.txt', implode("\n", $lines)."\n");
    }

    /**
     * A note on one line, its Markdown and any line breaks taken out.
     */
    private function line(string $text): string
    {
        $text = (string) preg_replace(['/!\[[^\]]*\]\([^)]*\)/', '/[*_`#>]+/'], ['', ''], $text);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * A config value as plain arrays, however it is held.
     *
     * @return array<mixed>
     */
    private function rows(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : (array) json_decode((string) json_encode($value), true);
    }
}
