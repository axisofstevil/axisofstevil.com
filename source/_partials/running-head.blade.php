<header class="plate running-head" data-snap>
    <nav class="running-left" aria-label="Breadcrumb">
        <a class="running-lockup" href="/"><img class="running-wordmark" src="/assets/logo/axis-wordmark.svg" alt="The Axis of Stevil" width="456" height="76.5"><img class="running-symbol" src="/assets/logo/axis-symbol.svg" alt="The Axis of Stevil" width="61" height="77"></a>
        @if ($switcher ?? null)
        @php($tag = $switcher['heading'] ?? false ? 'h1' : 'p')
        @php($options = [['label' => 'All Works', 'href' => '/works/', 'count' => collect($page->divisions)->sum('count')], ...collect($page->divisions)->map(fn ($d) => ['label' => $d['name'], 'href' => '/'.$d['slug'].'/', 'count' => $d['count']])->all()])
        <div class="crumb">
            <{{ $tag }} class="running-title"><a href="{{ $switcher['href'] }}" @if ($switcher['heading'] ?? false) aria-current="page" @endif>{{ $switcher['current'] }}</a></{{ $tag }}>
            <details class="switcher">
                <summary aria-label="Choose another division">
                    <svg class="switcher-chevron" viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.75"/></svg>
                </summary>
                <ul class="switcher-menu">
                    @foreach ($options as $option)
                    <li><a href="{{ $option['href'] }}" @if ($option['label'] === $switcher['current']) aria-current="true" @endif>{{ $option['label'] }}<span>{{ $option['count'] }}</span></a></li>
                    @endforeach
                </ul>
            </details>
        </div>
        @endif
    </nav>
    @if ($trail ?? null)
    <a class="running-trail" href="{{ $trail['href'] }}">@if ($trail['short'] ?? null)<span class="running-long">{{ $trail['label'] }}</span><span class="running-short" aria-hidden="true">{{ $trail['short'] }}</span>@else{{ $trail['label'] }}@endif</a>
    @endif
</header>
