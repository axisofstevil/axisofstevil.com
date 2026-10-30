@extends('_layouts.master')

@php
    $tunes = collect($page->listed)->map(fn ($t) => (array) (is_array($t) ? $t : $t->toArray()));
    $note = collect($page->note);
    $own = (bool) $page->parent;
    $day = fn ($date) => \Carbon\CarbonImmutable::parse($date);
    // The week a tune ran, as the archive printed it: May 14–18, 2007; Dec 26, 2005 – Jan 6, 2006; or from a day on.
    $run = function ($from, $to) use ($day) {
        if ($to === null) {
            return 'From '.$day($from)->format('M j, Y');
        }

        [$a, $b] = [$day($from), $day($to)];

        return match (true) {
            $a->year !== $b->year => $a->format('M j, Y').' – '.$b->format('M j, Y'),
            $a->month !== $b->month => $a->format('M j').' – '.$b->format('M j, Y'),
            $a->day === $b->day => $a->format('M j, Y'),
            default => $a->format('M j').'–'.$b->format('j, Y'),
        };
    };

    // The yearbook: newest first throughout, the years and the tunes within each.
    $years = $tunes->sortByDesc('from')->groupBy(fn ($t) => substr((string) $t['from'], 0, 4))->sortKeysDesc();
    $busiest = $years->map->count()->max() ?: 1;
    $label = $own ? $page->name : 'Tunes';
@endphp

@section('body')
<main id="main" class="report" @if ($own) data-player-scope="{{ $page->slug }}" @endif>
    <div class="masthead">
        @include('_partials.running-head', $own
            ? ['division' => 'tunes', 'artist' => $page->slug, 'here' => 'artist']
            : ['division' => 'tunes', 'here' => 'tunes'])

        <div class="plate division-bar" data-snap>
            <p class="division-figure"><b>{{ $page->count }}</b> <span>{{ $page->figure }}</span></p>
        </div>
    </div>

    {{-- The yearbook: a band per year, newest first; on the first, the artists (the Tunes) or the artist's picture
         and story (their page); each tune a tile, the years down the slate sidebar. --}}
    <div class="index-sheet yearbook-sheet">
        <div class="yearbook-bands">
            @foreach ($years as $year => $group)
            <section class="plate yearbook" aria-labelledby="year-{{ $year }}" data-snap>
                @if ($loop->first)
                @if ($own)
                {{-- The artist, above their tunes: their picture (or the placeholder) at the left with their website under it,
                     their bio beside, in two newspaper columns when it runs long. --}}
                @php($words = str_word_count(strip_tags((string) ($page->introduction ?? ''))))
                {{-- Two columns when the bio runs long, or has more than one block (a paragraph and the line-up): the blocks share the columns. --}}
                @php($blocks = preg_match_all('/<(p|ul|ol|blockquote|h[1-6])\b/i', (string) ($page->introduction ?? '')))
                @if ($page->banner || $words > 0 || ($page->website ?? null))
                <div class="artist-about">
                    <div class="artist-side">
                        @if ($page->banner)
                        <img class="artist-portrait" src="{{ $page->banner }}" alt="{{ $page->name }}">
                        @else
                        <span class="artist-portrait artist-blank" role="img" aria-label="{{ $page->name }}"></span>
                        @endif
                        @if ($page->website ?? null)
                        @php($site = (array) (is_array($page->website) ? $page->website : $page->website->toArray()))
                        <a class="artist-site" href="{{ $site['href'] }}" rel="noopener" title="{{ $site['text'] }}"><svg viewBox="0 0 12 12" aria-hidden="true"><path d="M4.5 2.5h5v5M9.5 2.5 3 9" fill="none" stroke="currentColor" stroke-width="1.5"/></svg><span>{{ $site['archived'] ? 'Visit archived website' : 'Visit website' }}</span></a>
                        @endif
                    </div>
                    @if ($words > 0)
                    <div class="issue-copy artist-words @if ($words >= 120 || $blocks >= 2) artist-words--columns @endif">{!! $page->introduction !!}</div>
                    @endif
                </div>
                @endif
                @else
                @include('_partials.series-intro')
                @endif
                @include('_partials.series-note')
                @endif
                <div class="year-figure">
                    <h2 id="year-{{ $year }}" class="year-title">{{ $year }}</h2>
                    <p class="year-count">{{ $group->count() }} {{ $group->count() === 1 ? 'tune' : 'tunes' }}</p>
                    <span class="year-bar" style="--w: {{ round($group->count() / $busiest * 100) }}%"></span>
                </div>
                <ul class="gallery-grid">
                    @foreach ($group as $tune)
                    @include('_partials.tune-tile', ['tune' => $tune, 'run' => $run, 'withArtist' => ! $own])
                    @endforeach
                </ul>
                <p class="caption">Yearbook {{ $year }} · {{ $label }}</p>
            </section>
            @endforeach
        </div>
        <nav class="plate thumb-index" aria-label="Years" data-snap>
            <div class="thumb-list">
                @foreach ($years->keys() as $year)
                <a href="#year-{{ $year }}">{{ $year }}</a>
                @endforeach
            </div>
        </nav>
    </div>
</main>

@include('_partials.footer')
@endsection
