@extends('_layouts.master')

@php
    $filed = \Carbon\CarbonImmutable::parse($page->date)->format('F j, Y');
    $short = fn ($date) => \Carbon\CarbonImmutable::parse($date)->format('M j, Y');

    // Credits by role, in the order the roles first appear: Art: Stevil; Writing: Stevil, Graham.
    $byRole = [];
    foreach ($page->roles ?? [] as $credit) {
        foreach ($credit['roles'] ?? [] as $role) {
            $byRole[$role][] = $credit['name'];
        }
    }
@endphp

@section('body')
<main id="main" class="report">
    @include('_partials.running-head', ['division' => $page->division, 'series' => $page->series_slug])

    <div class="spread" data-snap>
        <article class="plate work" aria-labelledby="work-title" data-snap>
            <h1 id="work-title" class="work-title">{{ $page->title }}</h1>
            <div class="work-body">
                @yield('content')
            </div>
            @if ($byRole)
            <dl class="credits">
                @foreach ($byRole as $role => $names)
                <div><dt>{{ $role }}</dt><dd>{{ implode(', ', $names) }}</dd></div>
                @endforeach
            </dl>
            @endif
            <p class="caption">Plate {{ $page->place }} · {{ $page->division_name }}</p>
        </article>

        <aside class="plate notes" aria-label="Notes" data-snap>
            <div class="notes-inner">
                <h2 class="notes-title">Notes</h2>
                <dl class="facts">
                    <div><dt>Division</dt><dd><a href="/{{ $page->division }}/">{{ $page->division_name }}</a></dd></div>
                    @if ($page->series)
                    <div><dt>Series</dt><dd><a href="/{{ $page->division }}/{{ $page->series_slug }}/">{{ $page->series }}</a></dd></div>
                    @endif
                    <div><dt>Filed</dt><dd>{{ $filed }}</dd></div>
                    <div><dt>Place</dt><dd>{{ $page->place }} of {{ $page->of }}</dd></div>
                </dl>

                @if ($page->original_url)
                <a class="first-ran" href="{{ $page->original_url }}" target="_blank" rel="noopener">As it first ran, {{ substr($page->date, 0, 4) }}<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M4.5 2.5h5v5M9.5 2.5 3 9" fill="none" stroke="currentColor" stroke-width="1.5"/></svg><span class="visually-hidden"> (opens in a new tab)</span></a>
                @endif

                @if ($page->older || $page->newer)
                <h3 class="notes-label">Also filed in {{ $page->division_name }}</h3>
                <nav class="neighbours" aria-label="Also filed in {{ $page->division_name }}">
                    @foreach (['Earlier' => $page->older, 'Later' => $page->newer] as $when => $work)
                    @if ($work)
                    <a class="neighbour" href="{{ $work['address'] }}">
                        @if ($work['image'] ?? null)<span class="neighbour-image"><img src="{{ $work['image'] }}" alt="" loading="lazy"></span>@endif
                        <span class="neighbour-when">{{ $when }} · {{ $short($work['date']) }}</span>
                        <span class="neighbour-title">{{ $work['title'] }}</span>
                    </a>
                    @endif
                    @endforeach
                </nav>
                @endif

                <button type="button" class="action audit-go" data-audit-go>Audit another work</button>
            </div>
            <p class="caption">Notes to Plate {{ $page->place }}</p>
        </aside>
    </div>
</main>

@include('_partials.footer')
@endsection
