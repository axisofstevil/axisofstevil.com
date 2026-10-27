@extends('_layouts.master')

@php
    $works = collect($page->works)->map(fn ($w) => (array) (is_array($w) ? $w : $w->toArray()));

    // The index: by title, ignoring leading quotes and punctuation; anything not starting with a letter files under #.
    $sortKey = fn ($w) => mb_strtolower(preg_replace('/^[^\pL\pN]+/u', '', $w['title']));
    $letters = $works->sortBy($sortKey)->groupBy(function ($w) use ($sortKey) {
        $first = mb_strtoupper(mb_substr($sortKey($w), 0, 1));
        return preg_match('/\pL/u', $first) ? $first : '#';
    })->sortKeys();

    // The yearbook: newest year first, each year's works in filing order.
    $years = $works->groupBy(fn ($w) => substr($w['date'], 0, 4))->sortKeysDesc();
    $busiest = $years->map->count()->max() ?: 1;
    $short = fn ($date) => \Carbon\CarbonImmutable::parse($date)->format('M j');
@endphp

@section('body')
<main id="main" class="report">
    @include('_partials.running-head', ['trail' => ['href' => '/#divisions', 'label' => 'Report of the Divisions']])

    <header class="plate division-head" data-snap>
        <h1 class="division-title">{{ $page->name }}</h1>
        <p class="division-figure"><b>{{ $page->count }}</b> <span>works · {{ $page->share }}% of output</span></p>
        @if (count($page->series) > 0)
        <p class="division-series">@foreach ($page->series as $name => $n){{ $name }} {{ $n }}@if (! $loop->last) · @endif @endforeach</p>
        @endif
        <div class="view-switch" role="group" aria-label="View">
            <button type="button" data-view="index" aria-pressed="true">Index</button>
            <button type="button" data-view="yearbook" aria-pressed="false">Yearbook</button>
        </div>
    </header>

    <div class="view view-index">
        <div class="index-sheet">
            <section class="plate index" aria-label="Index" data-snap>
                @foreach ($letters as $letter => $group)
                <div class="index-group" id="letter-{{ $letter === '#' ? 'num' : $letter }}">
                    <h2 class="index-letter">{{ $letter }}</h2>
                    <ul>
                        @foreach ($group as $work)
                        <li><a href="{{ $work['address'] }}">{{ $work['title'] }}</a><span>Plate {{ $work['place'] }} · {{ substr($work['date'], 0, 4) }}</span></li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
                <p class="caption">Index · {{ $page->name }}</p>
            </section>
            <nav class="plate thumb-index" aria-label="Letters" data-snap>
                @foreach ($letters->keys() as $letter)
                <a href="#letter-{{ $letter === '#' ? 'num' : $letter }}">{{ $letter }}</a>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="view view-yearbook">
        @foreach ($years as $year => $group)
        <section class="plate yearbook" aria-labelledby="year-{{ $year }}" data-snap>
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
                        <span class="tile-meta">Plate {{ $work['place'] }} · {{ $short($work['date']) }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
            <p class="caption">Yearbook {{ $year }} · {{ $page->name }}</p>
        </section>
        @endforeach
    </div>
</main>

@include('_partials.footer')
@endsection
