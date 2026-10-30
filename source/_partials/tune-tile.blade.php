{{-- A tune as a tile: the artist's picture (not on the artist's own page, which opens with it), the title, the artist
     (a link, unless the page is theirs), the week it ran, and Play when it has audio. --}}
<li class="tune-tile">
    @if ($withArtist)
    @if ($tune['image'])
    <img src="{{ $tune['image'] }}" alt="{{ $tune['artist'] }}" loading="lazy" decoding="async">
    @else
    <span class="tune-blank"></span>
    @endif
    @endif
    <p class="tune-title">“{{ $tune['title'] }}”</p>
    <p class="tune-artist">@if ($withArtist && $tune['artist_address'])<a href="{{ $tune['artist_address'] }}">{{ $tune['artist'] }}</a>@else{{ $tune['artist'] }}@endif</p>
    @foreach ($tune['runs'] as $when)
    @php($when = (array) (is_array($when) ? $when : $when->toArray()))
    @if ($when['from'] ?? null)
    <p class="tune-when">{{ $run($when['from'], $when['until'] ?? null) }}@if (count($tune['runs']) > 1 && $loop->index > 0) <span>again</span>@endif</p>
    @endif
    @endforeach
    @if ($tune['audio'])
    <button type="button" class="play" data-play="{{ $tune['id'] }}" aria-label="Play “{{ $tune['title'] }}” by {{ $tune['artist'] }}"><svg viewBox="0 0 12 12" aria-hidden="true"><path class="play-icon" d="M3 2l7 4-7 4z"/><path class="pause-icon" d="M3 2h2.5v8H3zM6.5 2H9v8H6.5z"/></svg><span>Play</span></button>
    @endif
</li>
