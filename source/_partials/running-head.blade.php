<header class="plate running-head" data-snap>
    <a class="running-lockup" href="/"><img src="/assets/logo/axis-wordmark.svg" alt="The Axis of Stevil: the report" width="456" height="76.5"></a>
    @if ($trail ?? null)
    <a class="running-trail" href="{{ $trail['href'] }}">{{ $trail['label'] }}</a>
    @endif
</header>
