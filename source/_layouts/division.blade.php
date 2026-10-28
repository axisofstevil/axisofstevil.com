@extends('_layouts.master')

@php
    $works = collect($page->works)->map(fn ($w) => (array) (is_array($w) ? $w : $w->toArray()));

    // The index: by title, ignoring leading quotes, punctuation and articles (A Declaration Of War files under D);
    // anything not starting with a letter files under #.
    $sortKey = fn ($w) => mb_strtolower(preg_replace('/^(?:(?:a|an|the)\s+)?/iu', '', preg_replace('/^[^\pL\pN]+/u', '', $w['title'])));
    $letters = $works->sortBy($sortKey)->groupBy(function ($w) use ($sortKey) {
        $first = mb_strtoupper(mb_substr($sortKey($w), 0, 1));
        return preg_match('/\pL/u', $first) ? $first : '#';
    })->sortKeys();

    // The yearbook: newest first throughout, the years and the works within each year.
    $years = $works->sortBy([['date', 'desc'], ['place', 'desc']])->groupBy(fn ($w) => substr($w['date'], 0, 4))->sortKeysDesc();
    $busiest = $years->map->count()->max() ?: 1;
    $short = fn ($date) => \Carbon\CarbonImmutable::parse($date)->format('M j');
    $note = collect($page->note);
@endphp

@section('body')
<main id="main" class="report">
    <div class="masthead">
        @include('_partials.running-head', $page->parent
            ? ['division' => $page->parent, 'series' => $page->slug, 'here' => 'series']
            : ['division' => $page->slug, 'here' => 'division'])

        <div class="plate division-bar" data-snap>
            <p class="division-figure"><b>{{ $page->count }}</b> <span>{{ $page->figure }}</span></p>
            <div class="view-switch" role="group" aria-label="View">
                <button type="button" data-view="index" aria-pressed="true">Index</button>
                <button type="button" data-view="yearbook" aria-pressed="false">Yearbook</button>
            </div>
        </div>
    </div>

    <div class="view view-index">
        <div class="index-sheet">
            <section class="plate index" aria-label="Index" data-snap>
                @include('_partials.series-note')
                @foreach ($letters as $letter => $group)
                <div class="index-group" id="letter-{{ $letter === '#' ? 'num' : $letter }}">
                    <h2 class="index-letter">{{ $letter }}</h2>
                    <ul>
                        @foreach ($group as $work)
                        <li><a href="{{ $work['address'] }}">{{ $work['title'] }}</a><span>{{ $work['label'] }} · {{ substr($work['date'], 0, 4) }}</span></li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
                <p class="caption">Index · {{ $page->name }}</p>
            </section>
            <nav class="plate thumb-index" aria-label="Letters" data-snap>
            <div class="thumb-list">
                @foreach ($letters->keys() as $letter)
                <a href="#letter-{{ $letter === '#' ? 'num' : $letter }}">{{ $letter }}</a>
                @endforeach
            </div>
            </nav>
        </div>
    </div>

    <div class="view view-yearbook">
        <div class="index-sheet yearbook-sheet">
        <div class="yearbook-bands">
        @foreach ($years as $year => $group)
        <section class="plate yearbook" aria-labelledby="year-{{ $year }}" data-snap>
            @if ($loop->first)
            @include('_partials.series-note')
            @endif
            <div class="year-figure">
                <h2 id="year-{{ $year }}" class="year-title">{{ $year }}</h2>
                <p class="year-count">{{ $group->count() }} {{ $group->count() === 1 ? 'work' : 'works' }} filed</p>
                <span class="year-bar" style="--w: {{ round($group->count() / $busiest * 100) }}%"></span>
            </div>
            <ul class="year-works">
                @foreach ($group as $work)
                <li>
                    <a class="tile" href="{{ $work['address'] }}">
                        @if ($work['image'] ?? null)
                        <span class="tile-image"><img src="{{ $work['image'] }}" alt="" loading="lazy"></span>
                        @else
                        <span class="tile-image tile-blank"></span>
                        @endif
                        <span class="tile-title">{{ $work['title'] }}</span>
                        <span class="tile-meta">{{ $work['label'] }} · {{ $short($work['date']) }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
            <p class="caption">Yearbook {{ $year }} · {{ $page->name }}</p>
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
    </div>
</main>

@include('_partials.footer')
@endsection
