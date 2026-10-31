@extends('_layouts.master')

@php
    $day = fn ($date) => \Carbon\CarbonImmutable::parse($date);
    // A run as the archive printed it: May 14–18, 2007; Dec 26, 2005 – Jan 6, 2006.
    $run = function ($from, $to) use ($day) {
        [$a, $b] = [$day($from), $day($to)];

        return match (true) {
            $a->year !== $b->year => $a->format('M j, Y').' – '.$b->format('M j, Y'),
            $a->month !== $b->month => $a->format('M j').' – '.$b->format('M j, Y'),
            default => $a->format('M j').'–'.$b->format('j, Y'),
        };
    };
    $volume = ['2005' => 'I', '2006' => 'II', '2007' => 'III'][substr($page->starts_on, 0, 4)] ?? '';

    $blocks = collect($page->blocks)->map(fn ($b) => (array) $b);
    $tunes = $blocks->where('kind', 'tune')->values();
    $archive = $blocks->firstWhere('kind', 'archive');
    $jump = fn ($b) => 'Continued in '.$b['division'].', Plate '.$b['place'];
@endphp

@section('body')
<main id="main" class="report">
    @include('_partials.running-head', ['division' => 'issues', 'here' => 'issue'])

    <article class="plate issue" aria-labelledby="issue-title" data-snap>
        @if ($headlines = $page->headlines[$page->starts_on] ?? [])
            {{-- The week's headlines from the world outside, one at a time (site.js), the first at rest: a small line
                 in the air over the masthead, at the left on a wide desk and centred narrower. --}}
            <p class="issue-ticker"><b>Meanwhile</b> <span class="issue-ticker-lines" data-ticker>@foreach ($headlines as $i => $line)<span @if ($i === 0)class="is-on"@endif>{{ $line['headline'] }}</span>@endforeach</span></p>
        @endif

        {{-- The masthead: the week and its run over the name; volume and number, and the count of new works, under it.
             Narrower than a wide desk, the week is one short line and the under-folio one line. --}}
        <header class="issue-masthead">
            <p class="issue-folio issue-folio--over">
                <span class="issue-week">The week of {{ $day($page->starts_on)->format('l, F j, Y') }}</span>
                <span class="issue-run">{{ $run($page->starts_on, $page->ends_on) }}</span>
                {{-- Narrower: the two as one short line. --}}
                <span class="issue-week-short">Week of {{ $day($page->starts_on)->format('D') }}, {{ $run($page->starts_on, $page->ends_on) }}</span>
            </p>
            <h1 id="issue-title" class="issue-name">{{ $page->name }}</h1>
            <p class="issue-folio issue-folio--under">
                <span>Vol. {{ $volume }} • No. {{ $page->number }}</span>
                {{-- Every work the week carried, and how many were new; "13 new works" when all were. --}}
                <span>@if ($page->pieces > $page->works){{ $page->pieces }} works, {{ $page->works }} new @else{{ $page->works }} new {{ $page->works === 1 ? 'work' : 'works' }}@endif</span>
            </p>
        </header>

        {{-- The week as laid out: rows of slots on 12 columns, a slot's pieces one under another. --}}
        @foreach ($page->rows as $row)
        <div class="issue-grid">
            @foreach ($row as $slot)
            <div class="issue-slot" style="--span: {{ $slot['span'] }}">
                @foreach ($slot['pieces'] as $piece)
                @include('_partials.issue-piece', ['b' => (array) $piece, 'jump' => $jump, 'span' => (int) $slot['span'], 'lead' => $loop->parent->parent->first && $loop->parent->first && $loop->first])
                @endforeach
            </div>
            @endforeach
        </div>
        @endforeach

        {{-- The week's works the page left out, re-runs included: a list under the spread. --}}
        @if ($page->also)
        <section class="issue-also" aria-labelledby="issue-also-label">
            <p id="issue-also-label" class="issue-box-label">Also this week</p>
            <ol class="issue-archive-list issue-also-list">
                @foreach ($page->also as $entry)
                <li>
                    <a href="{{ $entry['address'] }}">
                        <span class="issue-archive-thumb">@if ($entry['image'])<img src="{{ $entry['image'] }}" alt="" loading="lazy">@endif</span>
                        <span class="issue-archive-title">{{ $entry['title'] }}@if ($entry['refeature']) <span class="issue-also-rerun">Re-run</span>@endif</span>
                        <span class="issue-also-division">{{ $entry['division'] }}</span>
                        <span class="issue-archive-date">{{ $day($entry['date'])->format('m.d.y') }}</span>
                    </a>
                </li>
                @endforeach
            </ol>
        </section>
        @endif

        @if ($tunes->isNotEmpty() || $archive)
        <div class="issue-foot @if ($tunes->isEmpty() || ! $archive) issue-foot--one @endif">
            @if ($tunes->isNotEmpty())
            {{-- The Tune of the Week as a framed panel: one tune is a sleeve (the picture, the label beside it, the story
                 beneath); several are the Tunes pages' tiles in a row, their stories on their artists' pages. --}}
            <section class="issue-tunes @if ($tunes->count() > 1) issue-tunes--several @elseif (trim((string) $tunes->first()['html']) !== '' || trim((string) ($tunes->first()['about'] ?? '')) !== '') issue-tunes--story @endif" aria-label="{{ $tunes->count() > 1 ? 'Tunes of the Week' : 'Tune of the Week' }}">
                <p class="issue-box-label issue-tunes-label">{{ $tunes->count() > 1 ? 'Tunes of the Week' : 'Tune of the Week' }} @if ($tunes->count() > 1)<span>{{ $tunes->count() }}</span>@endif</p>
                @if ($tunes->count() === 1)
                @php($tune = $tunes->first())
                <div class="issue-sleeve">
                    @if ($tune['image'])
                    <img class="issue-sleeve-picture" src="{{ $tune['image'] }}" alt="{{ $tune['artist'] }}" loading="lazy">
                    @else
                    <span class="issue-sleeve-picture artist-blank" role="img" aria-label="{{ $tune['artist'] }}"></span>
                    @endif
                    <div class="issue-sleeve-label">
                        @if ($tune['track'])
                        <p class="issue-sleeve-track">“{{ $tune['track'] }}”</p>
                        @endif
                        <p class="issue-sleeve-artist">@if ($tune['artist_address'] ?? null)<a href="{{ $tune['artist_address'] }}">{{ $tune['artist'] }}</a>@else{{ $tune['artist'] }}@endif</p>
                        @if ($tune['from'] ?? null)
                        <p class="issue-sleeve-when">Featured {{ $run($tune['from'], $tune['until'] ?? $tune['from']) }}</p>
                        @endif
                        @if ($tune['website'] ?? null)
                        @php($site = (array) (is_array($tune['website']) ? $tune['website'] : $tune['website']->toArray()))
                        <p class="issue-sleeve-site"><a href="{{ $site['href'] }}" rel="noopener">{{ $site['text'] }}</a>@if ($site['archived']) <span>archived</span>@endif</p>
                        @endif
                        @if ($tune['play'] ?? null)
                        <button type="button" class="play play--large" data-play="{{ $tune['play'] }}" aria-label="Play “{{ $tune['track'] }}” by {{ $tune['artist'] }}"><svg viewBox="0 0 12 12" aria-hidden="true"><path class="play-icon" d="M3 2l7 4-7 4z"/><path class="pause-icon" d="M3 2h2.5v8H3zM6.5 2H9v8H6.5z"/></svg><span>Play</span></button>
                        @endif
                    </div>
                </div>
                @php($said = trim((string) $tune['html']) !== '')
                @php($about = trim((string) ($tune['about'] ?? '')) !== '')
                @if ($said || $about)
                <div class="issue-tune-words">
                    @if ($said)
                    <div class="issue-copy issue-tune-bio">{!! $tune['html'] !!}</div>
                    @endif
                    @if ($about && $said)
                    {{-- The artist's own bio, folded under what was said that week. --}}
                    <details class="issue-tune-about">
                        <summary>About {{ $tune['artist'] }}</summary>
                        <div class="issue-copy issue-tune-bio">{!! $tune['about'] !!}</div>
                    </details>
                    @elseif ($about)
                    <div class="issue-copy issue-tune-bio">{!! $tune['about'] !!}</div>
                    @endif
                </div>
                @endif
                @else
                <ul class="gallery-grid issue-tune-tiles">
                    @foreach ($tunes as $tune)
                    @include('_partials.tune-tile', ['tune' => ['id' => $tune['play'] ?? '', 'title' => $tune['track'], 'artist' => $tune['artist'], 'artist_address' => $tune['artist_address'] ?? null, 'image' => $tune['image'], 'audio' => $tune['play'] ?? null, 'runs' => [['from' => $tune['from'], 'until' => $tune['until']]]], 'run' => $run, 'withArtist' => true])
                    @endforeach
                </ul>
                @endif
            </section>
            @endif

            @if ($archive)
            <section class="issue-archive" aria-label="Back Issues">
                <p class="issue-box-label">From the archive</p>
                <ol class="issue-archive-list">
                    @foreach ($archive['entries'] as $entry)
                    <li>
                        <a href="{{ $entry['address'] }}">
                            <span class="issue-archive-thumb">@if ($entry['image'])<img src="{{ $entry['image'] }}" alt="" loading="lazy">@endif</span>
                            <span class="issue-archive-title">{{ $entry['title'] }}</span>
                            <span class="issue-archive-date">{{ $day($entry['date'])->format('m.d.y') }}</span>
                        </a>
                    </li>
                    @endforeach
                </ol>
            </section>
            @endif
        </div>
        @endif

        {{-- Earlier and later issues, as a paper's foot would send you on. --}}
        <nav class="issue-pager" aria-label="Issues">
            @if ($page->earlier)
            <a class="issue-pager-link" href="{{ $page->earlier['address'] }}" rel="prev"><span class="issue-pager-when"><span aria-hidden="true">←</span> No. {{ $page->number - 1 }} · {{ $run($page->earlier['starts_on'], $page->earlier['ends_on']) }}</span><span class="issue-pager-title">{{ $page->earlier['title'] }}</span></a>
            @else
            <span class="issue-pager-link issue-pager-link--none">The first issue</span>
            @endif
            <a class="issue-pager-all" href="/issues/#year-{{ substr($page->starts_on, 0, 4) }}">Every issue</a>
            @if ($page->later)
            <a class="issue-pager-link issue-pager-link--later" href="{{ $page->later['address'] }}" rel="next"><span class="issue-pager-when">No. {{ $page->number + 1 }} · {{ $run($page->later['starts_on'], $page->later['ends_on']) }} <span aria-hidden="true">→</span></span><span class="issue-pager-title">{{ $page->later['title'] }}</span></a>
            @else
            <span class="issue-pager-link issue-pager-link--later issue-pager-link--none">The final issue</span>
            @endif
        </nav>
        <p class="caption">Vol. {{ $volume }} · No. {{ $page->number }} · {{ $run($page->starts_on, $page->ends_on) }}</p>
    </article>
</main>

@include('_partials.footer')
@endsection
