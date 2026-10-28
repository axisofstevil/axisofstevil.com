@php
    // The breadcrumb beneath the lockup: the division (or All Works), then the series
    // when there is one. Each crumb is its name, a link, and a chevron that opens its
    // siblings; the page's own crumb is its heading.
    $divisions = collect($page->divisions)->map(fn ($d) => (array) (is_array($d) ? $d : $d->toArray()));
    $crumbs = [];
    $parent = ($division ?? null) === 'works'
        ? ['name' => 'All Works', 'slug' => 'works', 'series' => []]
        : $divisions->firstWhere('slug', $division ?? null);

    if ($parent) {
        $crumbs[] = [
            'name' => $parent['name'],
            'href' => '/'.$parent['slug'].'/',
            'here' => ($here ?? null) === 'division',
            'label' => 'Choose another division',
            'menu' => [
                ['name' => 'All Works', 'href' => '/works/', 'count' => $divisions->sum('count')],
                ...$divisions->map(fn ($d) => ['name' => $d['name'], 'href' => '/'.$d['slug'].'/', 'count' => $d['count']])->all(),
            ],
        ];

        $siblings = collect($parent['series'] ?? [])->map(fn ($s) => (array) (is_array($s) ? $s : $s->toArray()));
        $current = ($series ?? null) ? $siblings->firstWhere('slug', $series) : null;

        if ($current) {
            $crumbs[] = [
                'name' => $current['name'],
                'href' => $current['address'],
                'here' => ($here ?? null) === 'series',
                'label' => 'Choose another series',
                'menu' => [
                    ['name' => 'All '.$parent['name'], 'href' => '/'.$parent['slug'].'/', 'count' => $parent['count']],
                    ...$siblings->map(fn ($s) => ['name' => $s['name'], 'href' => $s['address'], 'count' => $s['count']])->all(),
                ],
            ];
        }
    }
@endphp
<header class="plate running-head" data-snap>
    <nav class="running-left" aria-label="Breadcrumb">
        <a class="running-lockup" href="/"><img class="running-wordmark" src="/assets/logo/axis-wordmark.svg" alt="The Axis of Stevil" width="456" height="76.5"><img class="running-symbol" src="/assets/logo/axis-symbol.svg" alt="The Axis of Stevil" width="61" height="77"></a>
        @foreach ($crumbs as $crumb)
        @php($tag = $crumb['here'] ? 'h1' : 'p')
        <div class="crumb">
            <{{ $tag }} class="running-title"><a href="{{ $crumb['href'] }}" @if ($crumb['here']) aria-current="page" @endif>{{ $crumb['name'] }}</a></{{ $tag }}>
            <details class="switcher">
                <summary aria-label="{{ $crumb['label'] }}">
                    <svg class="switcher-chevron" viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.75"/></svg>
                </summary>
                <ul class="switcher-menu">
                    @foreach ($crumb['menu'] as $option)
                    <li><a href="{{ $option['href'] }}" @if ($option['href'] === $crumb['href']) aria-current="true" @endif>{{ $option['name'] }}<span>{{ $option['count'] }}</span></a></li>
                    @endforeach
                </ul>
            </details>
        </div>
        @endforeach
    </nav>
    @if ($trail ?? null)
    <a class="running-trail" href="{{ $trail['href'] }}">@if ($trail['short'] ?? null)<span class="running-long">{{ $trail['label'] }}</span><span class="running-short" aria-hidden="true">{{ $trail['short'] }}</span>@else{{ $trail['label'] }}@endif</a>
    @endif
</header>
