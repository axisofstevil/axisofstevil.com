@extends('_layouts.master')

@php
    $day = fn ($date) => \Carbon\CarbonImmutable::parse($date);
    // A run as the archive printed it: May 14–18, 2007; Dec 26, 2005 – Jan 6, 2006.
    $run = function ($from, $to) use ($day) {
        [$a, $b] = [$day($from), $day($to)];

        return match (true) {
            $a->year !== $b->year => $a->format('M j, Y').' – '.$b->format('M j, Y'),
            $a->month !== $b->month => $a->format('M j').' – '.$b->format('M j, Y'),
            default => $a->format('M j').'–'.$b->format('j, Y'),
        };
    };
    $volume = ['2005' => 'I', '2006' => 'II', '2007' => 'III'][substr($page->starts_on, 0, 4)] ?? '';

    $blocks = collect($page->blocks)->map(fn ($b) => (array) $b);
    $tunes = $blocks->where('kind', 'tune')->values();
    $archive = $blocks->firstWhere('kind', 'archive');
    $jump = fn ($b) => 'Continued in '.$b['division'].', Plate '.$b['place'];
@endphp

@section('body')
<main id="main" class="report">
    @include('_partials.running-head', ['division' => 'issues', 'here' => 'issue'])

    <article class="plate issue" aria-labelledby="issue-title" data-snap>
        {{-- The masthead: the week and its run over the name; volume and number, and the count of new works, under it. --}}
        <header class="issue-masthead">
            <p class="issue-folio">
                <span>The week of {{ $day($page->starts_on)->format('l, F j, Y') }}</span>
                <span class="issue-satellite"><b>LIVE!</b> from the Stevil Satellite</span>
                <span>{{ $run($page->starts_on, $page->ends_on) }}</span>
            </p>
            <h1 id="issue-title" class="issue-name">{{ $page->name }}</h1>
            <p class="issue-folio issue-folio--under">
                <span>Vol. {{ $volume }} · No. {{ $page->number }}</span>
                <span>{{ $page->works }} new {{ $page->works === 1 ? 'work' : 'works' }}</span>
            </p>
        </header>

        {{-- The week as laid out: rows of slots on 12 columns, a slot's pieces one under another. --}}
        @foreach ($page->rows as $row)
        <div class="issue-grid">
            @foreach ($row as $slot)
            <div class="issue-slot" style="--span: {{ $slot['span'] }}">
                @foreach ($slot['pieces'] as $piece)
                @include('_partials.issue-piece', ['b' => (array) $piece, 'jump' => $jump, 'span' => (int) $slot['span'], 'lead' => $loop->parent->parent->first && $loop->parent->first && $loop->first])
                @endforeach
            </div>
            @endforeach
        </div>
        @endforeach

        @if ($tunes->isNotEmpty() || $archive)
        <div class="issue-foot @if ($tunes->isEmpty() || ! $archive) issue-foot--one @endif">
            @if ($tunes->isNotEmpty())
            <div class="issue-tunes">
                @foreach ($tunes as $tune)
                <section class="issue-tune" aria-label="Tune of the Week">
                    <p class="issue-box-label">Tune of the Week @if ($tunes->count() > 1)<span>{{ $loop->iteration }} of {{ $tunes->count() }}</span>@endif</p>
                    <div class="issue-sleeve">
                        @if ($tune['image'])
                        <img class="issue-sleeve-picture" src="{{ $tune['image'] }}" alt="{{ $tune['artist'] }}" loading="lazy">
                        @endif
                        <div class="issue-sleeve-label">
                            @if ($tune['track'])
                            <p class="issue-sleeve-track">“{{ $tune['track'] }}”</p>
                            @endif
                            <p class="issue-sleeve-artist">{{ $tune['artist'] }}</p>
                        </div>
                    </div>
                    <div class="issue-copy issue-tune-bio">{!! $tune['html'] !!}</div>
                </section>
                @endforeach
            </div>
            @endif

            @if ($archive)
            <section class="issue-archive" aria-label="Back Issues">
                <p class="issue-box-label">From the archive</p>
                <ol class="issue-archive-list">
                    @foreach ($archive['entries'] as $entry)
                    <li>
                        <a href="{{ $entry['address'] }}">
                            <span class="issue-archive-thumb">@if ($entry['image'])<img src="{{ $entry['image'] }}" alt="" loading="lazy">@endif</span>
                            <span class="issue-archive-title">{{ $entry['title'] }}</span>
                            <span class="issue-archive-date">{{ $day($entry['date'])->format('m.d.y') }}</span>
                        </a>
                    </li>
                    @endforeach
                </ol>
            </section>
            @endif
        </div>
        @endif

        {{-- Earlier and later issues, as a paper's foot would send you on. --}}
        <nav class="issue-pager" aria-label="Issues">
            @if ($page->earlier)
            <a class="issue-pager-link" href="{{ $page->earlier['address'] }}" rel="prev"><span class="issue-pager-when"><span aria-hidden="true">←</span> No. {{ $page->number - 1 }} · {{ $run($page->earlier['starts_on'], $page->earlier['ends_on']) }}</span><span class="issue-pager-title">{{ $page->earlier['title'] }}</span></a>
            @else
            <span class="issue-pager-link issue-pager-link--none">The first issue</span>
            @endif
            <a class="issue-pager-all" href="/issues/#year-{{ substr($page->starts_on, 0, 4) }}">Every issue</a>
            @if ($page->later)
            <a class="issue-pager-link issue-pager-link--later" href="{{ $page->later['address'] }}" rel="next"><span class="issue-pager-when">No. {{ $page->number + 1 }} · {{ $run($page->later['starts_on'], $page->later['ends_on']) }} <span aria-hidden="true">→</span></span><span class="issue-pager-title">{{ $page->later['title'] }}</span></a>
            @else
            <span class="issue-pager-link issue-pager-link--later issue-pager-link--none">The final issue</span>
            @endif
        </nav>
        <p class="caption">Vol. {{ $volume }} · No. {{ $page->number }} · {{ $run($page->starts_on, $page->ends_on) }}</p>
    </article>
</main>

@include('_partials.footer')
@endsection
