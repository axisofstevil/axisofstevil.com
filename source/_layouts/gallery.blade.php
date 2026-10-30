@extends('_layouts.master')

@php
    $photos = collect($page->photos)->map(fn ($p) => (array) (is_array($p) ? $p : $p->toArray()));
    $note = collect($page->note);
    $own = (bool) $page->parent;
    // A date as precise as it is known: 10.27.06, Oct 2006, or 2006.
    $when = fn (?string $date) => match (strlen((string) $date)) {
        10 => \Carbon\CarbonImmutable::parse($date)->format('m.d.y'),
        7 => \Carbon\CarbonImmutable::parse($date.'-01')->format('M Y'),
        4 => $date,
        default => null,
    };

    // The yearbook: newest first throughout, the years and the photographs within each.
    $years = $photos->sortByDesc('date')->groupBy(fn ($p) => substr((string) $p['date'], 0, 4) ?: 'Undated')->sortKeysDesc();
    $busiest = $years->map->count()->max() ?: 1;
    $label = $own ? $page->name : 'Gallery';
@endphp

@section('body')
<main id="main" class="report">
    <div class="masthead">
        @include('_partials.running-head', $own
            ? ['division' => 'gallery', 'photographer' => $page->slug, 'here' => 'photographer']
            : ['division' => 'gallery', 'here' => 'gallery'])

        <div class="plate division-bar" data-snap>
            <p class="division-figure"><b>{{ $page->count }}</b> <span>{{ $page->figure }}</span></p>
        </div>
    </div>

    {{-- The yearbook: a band per year, newest first, the gallery's banner, introduction and photographers on the first;
         each photograph a tile, the years down the slate sidebar. --}}
    <div class="index-sheet yearbook-sheet">
        <div class="yearbook-bands">
            @foreach ($years as $year => $group)
            <section class="plate yearbook" aria-labelledby="year-{{ $year }}" data-snap>
                @if ($loop->first)
                @include('_partials.series-intro')
                @include('_partials.series-note')
                @endif
                <div class="year-figure">
                    <h2 id="year-{{ $year }}" class="year-title">{{ $year }}</h2>
                    <p class="year-count">{{ $group->count() }} {{ $group->count() === 1 ? 'photograph' : 'photographs' }}</p>
                    <span class="year-bar" style="--w: {{ round($group->count() / $busiest * 100) }}%"></span>
                </div>
                <ul class="gallery-grid">
                    @foreach ($group as $photo)
                    @include('_partials.gallery-tile', ['photo' => $photo, 'when' => $when, 'withName' => ! $own])
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
