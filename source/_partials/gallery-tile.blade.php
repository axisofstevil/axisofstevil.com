{{-- A photograph as a tile: square, its date and (unless the page is the photographer's own) the photographer under it. --}}
@php($credits = collect($photo['credits'])->map(fn ($c) => (array) (is_array($c) ? $c : $c->toArray())))
<li class="gallery-tile">
    <img src="{{ $photo['image'] }}" alt="{{ $photo['caption'] ?? ('Photograph'.($credits->isNotEmpty() ? ' by '.$credits->pluck('name')->implode(', ') : '').($when($photo['date']) ? ', '.$when($photo['date']) : '')) }}" loading="lazy" decoding="async" @if ($photo['width'] && $photo['height']) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif>
    <span class="gallery-tile-line">
        @if (! $withName)
        <span>{{ $when($photo['date']) }}</span>
        @else
        @foreach ($credits->take(1) as $credit)<a href="{{ $credit['address'] }}">{{ $credit['name'] }}</a>@endforeach
        <span>{{ $when($photo['date']) }}</span>
        @endif
    </span>
</li>
