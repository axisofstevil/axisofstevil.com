@extends('_layouts.master')

@php
    $photos = collect($page->photos)->map(fn ($p) => (array) (is_array($p) ? $p : $p->toArray()));
    $note = collect($page->note);
    // A date as precise as it is known: 10.27.06, Oct 2006, or 2006.
    $when = fn (?string $date) => match (strlen((string) $date)) {
        10 => \Carbon\CarbonImmutable::parse($date)->format('m.d.y'),
        7 => \Carbon\CarbonImmutable::parse($date.'-01')->format('M Y'),
        4 => $date,
        default => null,
    };
@endphp

@section('body')
<main id="main" class="report">
    <div class="masthead">
        @include('_partials.running-head', $page->parent
            ? ['division' => 'gallery', 'photographer' => $page->slug, 'here' => 'photographer']
            : ['division' => 'gallery', 'here' => 'gallery'])

        <div class="plate division-bar" data-snap>
            <p class="division-figure"><b>{{ $page->count }}</b> <span>{{ $page->figure }}</span></p>
        </div>
    </div>

    {{-- The sheet: the gallery's banner and introduction, its photographers, then every photograph as a tile. --}}
    <section class="plate gallery-sheet" aria-label="{{ $page->parent ? $page->name : 'Gallery' }}" data-snap>
        @include('_partials.series-intro')
        @include('_partials.series-note')
        <ul class="gallery-grid">
            @foreach ($photos as $photo)
            @php($credits = collect($photo['credits'])->map(fn ($c) => (array) (is_array($c) ? $c : $c->toArray())))
            <li class="gallery-tile">
                <img src="{{ $photo['image'] }}" alt="{{ $photo['caption'] ?? ('Photograph'.($credits->isNotEmpty() ? ' by '.$credits->pluck('name')->implode(', ') : '').($when($photo['date']) ? ', '.$when($photo['date']) : '')) }}" loading="lazy" decoding="async" @if ($photo['width'] && $photo['height']) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif>
                <span class="gallery-tile-line">
                    @if ($page->parent)
                    <span>{{ $when($photo['date']) }}</span>
                    @else
                    @foreach ($credits->take(1) as $credit)<a href="{{ $credit['address'] }}">{{ $credit['name'] }}</a>@endforeach
                    <span>{{ $when($photo['date']) }}</span>
                    @endif
                </span>
            </li>
            @endforeach
        </ul>
        <p class="caption">{{ $page->parent ? $page->name : 'Gallery' }} · {{ $page->count }} {{ $page->count === 1 ? 'photograph' : 'photographs' }}</p>
    </section>
</main>
@endsection
