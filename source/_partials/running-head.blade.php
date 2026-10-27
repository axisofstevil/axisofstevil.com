<header class="plate running-head" data-snap>
    <div class="running-left">
        <a class="running-lockup" href="/"><img class="running-wordmark" src="/assets/logo/axis-wordmark.svg" alt="The Axis of Stevil: the report" width="456" height="76.5"><img class="running-symbol" src="/assets/logo/axis-symbol.svg" alt="The Axis of Stevil: the report" width="61" height="77"></a>
        @if ($switcher ?? null)
        <details class="switcher">
            <summary aria-label="{{ $switcher['current'] }}: choose another division">
                <h1 class="running-title">{{ $switcher['current'] }}</h1>
                <svg class="switcher-chevron" viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.75"/></svg>
            </summary>
            <ul class="switcher-menu">
                @foreach ($switcher['options'] as $option)
                <li><a href="{{ $option['href'] }}" @if ($option['label'] === $switcher['current']) aria-current="page" @endif>{{ $option['label'] }}<span>{{ $option['count'] }}</span></a></li>
                @endforeach
            </ul>
        </details>
        @endif
    </div>
    @if ($trail ?? null)
    <a class="running-trail" href="{{ $trail['href'] }}">@if ($trail['short'] ?? null)<span class="running-long">{{ $trail['label'] }}</span><span class="running-short" aria-hidden="true">{{ $trail['short'] }}</span>@else{{ $trail['label'] }}@endif</a>
    @endif
</header>
