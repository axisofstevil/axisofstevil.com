<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use TightenCo\Jigsaw\Jigsaw;

/**
 * The guide for language models (/llms.txt, per llmstxt.org): what the site
 * is, in the owner's own words (the channel's page at llms.txt, written in
 * the channel; a few generated lines when there is none), then every page of the
 * site by section, each with a line that says what it holds, so a model can
 * find the right page without crawling. Works are listed in full, oldest
 * first, with their division and their date; issues likewise, by number.
 */
class LlmsFile
{
    public function handle(Jigsaw $jigsaw): void
    {
        $base = rtrim((string) $jigsaw->getConfig('baseUrl'), '/');
        $title = (string) $jigsaw->getConfig('title');
        $works = $this->rows($jigsaw->getConfig('works'));
        $issues = $this->rows($jigsaw->getConfig('issues'));
        $divisions = $this->rows($jigsaw->getConfig('divisions'));
        $gallery = $this->rows($jigsaw->getConfig('gallery'));
        $tunes = $this->rows($jigsaw->getConfig('tunes_section'));
        $organisation = $jigsaw->getCollection('organisation') ?? [];
        $divisionPages = $jigsaw->getCollection('divisions') ?? [];
        $galleryPages = $jigsaw->getCollection('gallery_pages') ?? [];
        $tunePages = $jigsaw->getCollection('tune_pages') ?? [];

        $link = fn (string $name, string $address, ?string $note = null) => "- [{$name}]({$base}{$address})".($note === null || $note === '' ? '' : ': '.$this->line($note));
        $month = fn (string $date) => CarbonImmutable::parse($date)->format('F Y');
        $day = fn (string $date) => CarbonImmutable::parse($date)->format('F j, Y');
        $dates = array_column($issues, 'starts_on');
        sort($dates);
        $span = $dates === [] ? null : $month($dates[0]).' to '.$month(end($dates));

        $own = trim(str_replace(["\r\n", "\r"], "\n", (string) $jigsaw->getConfig('llms')));

        $lines = $own !== '' ? [$own, ''] : [
            "# {$title}",
            '',
            '> '.$jigsaw->getConfig('tagline').' The complete works of the Axis of Stevil, a humour website founded in '.$jigsaw->getConfig('founded').', presented as an annual report to its shareholders: '.count($works).' works in '.count($divisions).' divisions'.($issues === [] ? '' : ', '.count($issues).' weekly issues'.($span ? " ({$span})" : '')).($gallery === [] ? '' : ', a gallery of '.$gallery['count'].' photographs').($tunes === [] ? '' : ' and '.$tunes['count'].' tunes of the week').'.',
            '',
            'Every work has its own page, listed under its division and, where it belongs to one, its series. The weekly issues are the front pages as they ran, each carrying that week\'s works. All pages are plain HTML at the addresses below; pictures and recordings are served from an archive host and are linked from the pages.',
            '',
        ];

        $lines[] = '## The organisation';
        $lines[] = '';

        // The organisation's pages in the site's own order (mission first), each with its summary.
        $byAddress = [];

        foreach ($organisation as $page) {
            $byAddress[(string) $page->address] = $page;
        }

        foreach ($this->rows($jigsaw->getConfig('organisation')) as $entry) {
            $page = $byAddress[$entry['address']] ?? null;
            $lines[] = $link((string) ($page->title ?? $entry['label']), (string) $entry['address'], $page?->description);
        }

        $lines[] = '';
        $lines[] = '## Divisions';
        $lines[] = '';
        $lines[] = $link('All Works', '/works/', 'every work, '.count($works).' in all, newest first');

        foreach ($divisionPages as $page) {
            if ($page->parent || $page->slug === 'works') {
                continue;
            }

            $series = array_filter(iterator_to_array($divisionPages), fn ($s) => $s->parent === $page->slug);
            $note = $page->count.' '.($page->count === 1 ? 'work' : 'works');

            if ($series !== []) {
                $note .= '. Series: '.implode(', ', array_map(fn ($s) => "[{$s->name}]({$base}{$s->address}) ({$s->count})", array_values($series)));
            }

            $lines[] = $link((string) $page->name, (string) $page->address, $note);
        }

        if ($issues !== []) {
            $lines[] = '';
            $lines[] = '## Issues';
            $lines[] = '';
            $lines[] = $link('All Issues', '/issues/', count($issues).' weekly issues'.($span ? ", {$span}" : ''));

            $numbered = $issues;
            usort($numbered, fn ($a, $b) => $a['starts_on'] <=> $b['starts_on']);

            foreach ($numbered as $i => $issue) {
                $lines[] = $link((string) $issue['title'], "/issues/{$issue['slug']}/", 'Issue No. '.($i + 1).', the week of '.$day($issue['starts_on']));
            }
        }

        if ($gallery !== []) {
            $lines[] = '';
            $lines[] = '## Gallery';
            $lines[] = '';

            foreach ($galleryPages as $page) {
                $lines[] = $link($page->parent ? (string) $page->name : 'Gallery', (string) $page->address, $page->description);
            }
        }

        if ($tunes !== []) {
            $lines[] = '';
            $lines[] = '## Tunes';
            $lines[] = '';

            foreach ($tunePages as $page) {
                $lines[] = $link($page->parent ? (string) $page->name : 'Tunes', (string) $page->address, $page->description);
            }
        }

        $lines[] = '';
        $lines[] = '## Works';
        $lines[] = '';
        $lines[] = 'Every work, oldest first: its division (and series), then the date it was filed.';
        $lines[] = '';

        $oldestFirst = $works;
        usort($oldestFirst, fn ($a, $b) => [$a['date'], $a['title']] <=> [$b['date'], $b['title']]);

        foreach ($oldestFirst as $work) {
            $lines[] = $link((string) $work['title'], (string) $work['address'], ($work['division_name'] ?? 'Work').(isset($work['series']) && $work['series'] !== null ? " ({$work['series']})" : '').', '.$day($work['date']));
        }

        // When this was written: the site is rebuilt from its source of record, so the file is as current as the build.
        $built = $jigsaw->getConfig('build_time');
        $built = ($built instanceof CarbonImmutable ? $built : CarbonImmutable::now())->setTimezone('UTC');
        $lines[] = '';
        $lines[] = '## Currency of this file';
        $lines[] = '';
        $lines[] = 'Generated: '.$built->toIso8601String().' ('.$built->format('l, F j, Y \\a\\t H:i').' UTC).';
        $lines[] = '';
        $lines[] = 'This file was written when the site was last built, from the site\'s source of record, and is current as of that moment: it lists every page the site had then. The site is rebuilt whenever it changes, and each build replaces this file, so what is here is the current state of the site.';

        file_put_contents($jigsaw->getDestinationPath().'/llms.txt', implode("\n", $lines)."\n");
    }

    /**
     * A note on one line, its Markdown and any line breaks taken out.
     */
    private function line(string $text): string
    {
        $text = (string) preg_replace(['/!\[[^\]]*\]\([^)]*\)/', '/\[([^\]]+)\]\([^)]*\)/', '/[*_`#>]+/'], ['', '$1', ''], $text);

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
