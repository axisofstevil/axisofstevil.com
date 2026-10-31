@php
    // The breadcrumb beneath the lockup: the division (or All Works), then the series
    // when there is one. Each crumb is its name, a link, and a chevron that opens its
    // siblings; the page's own crumb is its heading.
    $divisions = collect($page->divisions)->map(fn ($d) => (array) (is_array($d) ? $d : $d->toArray()));
    $crumbs = [];
    $parent = ($division ?? null) === 'works'
        ? ['name' => 'All Works', 'slug' => 'works', 'series' => []]
        : $divisions->firstWhere('slug', $division ?? null);

    // The weekly issues: Issues, its menu the years; on an issue, that issue's year beneath.
    if (($division ?? null) === 'issues') {
        $issues = collect($page->issues)->map(fn ($i) => (array) (is_array($i) ? $i : $i->toArray()));
        $years = $issues->groupBy(fn ($i) => substr($i['starts_on'], 0, 4))->sortKeys();
        $crumbs[] = [
            'name' => 'Issues',
            'href' => '/issues/',
            'here' => ($here ?? null) === 'issues',
            'label' => 'Choose a year',
            'menu' => [
                ['name' => 'All Issues', 'href' => '/issues/', 'count' => $issues->count()],
                ...$years->map(fn ($group, $year) => ['name' => (string) $year, 'href' => "/issues/#year-{$year}", 'count' => $group->count()])->values()->all(),
            ],
        ];
    }

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

    // The Gallery: Gallery, its menu the photographers; on a photographer's listing, their name beneath.
    $gallery = $page->gallery ? json_decode(json_encode($page->gallery), true) : null;

    if (($division ?? null) === 'gallery' && $gallery) {
        $photographers = [
            ['name' => 'All Photographs', 'href' => '/gallery/', 'count' => $gallery['count']],
            ...array_map(fn ($p) => ['name' => $p['name'], 'href' => $p['address'], 'count' => $p['count']], $gallery['photographers']),
        ];
        // Gallery is a plain link back to the sheet; the photographer's crumb carries the menu of photographers.
        $crumbs[] = [
            'name' => 'Gallery',
            'href' => '/gallery/',
            'here' => ($here ?? null) === 'gallery',
            'label' => null,
            'menu' => [],
        ];
        $current = ($photographer ?? null) ? collect($gallery['photographers'])->firstWhere('slug', $photographer) : null;

        if ($current) {
            $crumbs[] = [
                'name' => $current['name'],
                'href' => $current['address'],
                'here' => ($here ?? null) === 'photographer',
                'label' => 'Choose another photographer',
                'menu' => $photographers,
            ];
        }
    }

    // The Tunes: Tunes, its menu the artists; on an artist's page, their name beneath.
    $tunes = $page->tunes_section ? json_decode(json_encode($page->tunes_section), true) : null;

    if (($division ?? null) === 'tunes' && $tunes) {
        $artists = [
            ['name' => 'All Tunes', 'href' => '/tunes/', 'count' => $tunes['count']],
            ...array_map(fn ($a) => ['name' => $a['name'], 'href' => $a['address'], 'count' => $a['count']], $tunes['artists']),
        ];
        // Tunes is a plain link back to the listing; the artist's crumb carries the menu of artists.
        $crumbs[] = [
            'name' => 'Tunes',
            'href' => '/tunes/',
            'here' => ($here ?? null) === 'tunes',
            'label' => null,
            'menu' => [],
        ];
        $current = ($artist ?? null) ? collect($tunes['artists'])->firstWhere('slug', $artist) : null;

        if ($current) {
            $crumbs[] = [
                'name' => $current['name'],
                'href' => $current['address'],
                'here' => ($here ?? null) === 'artist',
                'label' => 'Choose another artist',
                'menu' => $artists,
            ];
        }
    }

    // The areas, the highest level of the site, behind one grid button after the lockup: Issues,
    // Works, the Gallery and the Tunes, each with its count; the area this page is in is marked. One line each to add more.
    $areas = [
        ['name' => 'Issues', 'href' => '/issues/', 'count' => collect($page->issues)->count(), 'here' => ($division ?? null) === 'issues'],
        ['name' => 'Works', 'href' => '/works/', 'count' => $divisions->sum('count'), 'here' => (bool) $parent],
        ...($gallery ? [['name' => 'Gallery', 'href' => '/gallery/', 'count' => $gallery['count'], 'here' => ($division ?? null) === 'gallery']] : []),
        ...($tunes ? [['name' => 'Tunes', 'href' => '/tunes/', 'count' => $tunes['count'], 'here' => ($division ?? null) === 'tunes']] : []),
    ];
@endphp
<header class="plate running-head" data-snap>
    <nav class="running-left" aria-label="Breadcrumb">
        <a class="running-lockup" href="/"><img class="running-wordmark" src="/assets/logo/axis-wordmark.svg" alt="The Axis of Stevil" width="456" height="76.5"><img class="running-symbol" src="/assets/logo/axis-symbol.svg" alt="The Axis of Stevil" width="61" height="77"></a>
        <details class="switcher areas">
            <summary aria-label="Choose an area">
                <svg class="switcher-grid" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 2h5v5H2zM9 2h5v5H9zM2 9h5v5H2zM9 9h5v5H9z" fill="currentColor"/></svg>
            </summary>
            <ul class="switcher-menu">
                @foreach ($areas as $area)
                <li><a href="{{ $area['href'] }}" @if ($area['here']) aria-current="true" @endif>{{ $area['name'] }}<span>{{ $area['count'] }}</span></a></li>
                @endforeach
            </ul>
        </details>
        @foreach ($crumbs as $crumb)
        @php($tag = $crumb['here'] ? 'h1' : 'p')
        <div class="crumb">
            <{{ $tag }} class="running-title"><a href="{{ $crumb['href'] }}" @if ($crumb['here']) aria-current="page" @endif>{{ $crumb['name'] }}</a></{{ $tag }}>
            @if ($crumb['menu'] !== [])
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
            @endif
        </div>
        @endforeach
    </nav>
    <p class="saying running-saying">@include('_partials.saying')</p>
    @if ($trail ?? null)
    <a class="running-trail" href="{{ $trail['href'] }}">@if ($trail['short'] ?? null)<span class="running-long">{{ $trail['label'] }}</span><span class="running-short" aria-hidden="true">{{ $trail['short'] }}</span>@else{{ $trail['label'] }}@endif</a>
    @endif
</header>
