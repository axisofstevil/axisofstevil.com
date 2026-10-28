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
@endphp

@section('body')
<main id="main" class="report">
    @include('_partials.running-head', ['division' => 'issues', 'here' => 'issue'])

    <div class="spread" data-snap>
        <article class="plate issue" aria-labelledby="issue-title" data-snap>
            <header class="issue-masthead">
                <p class="issue-dateline"><span>Issue No. {{ $page->number }}</span> <span>The week of {{ $day($page->starts_on)->format('F j, Y') }}</span></p>
                <h1 id="issue-title" class="issue-name">{{ $page->name }}</h1>
            </header>

            {{-- The week as it ran: each block in its place, the newest piece first. --}}
            @foreach ($page->blocks as $block)
            @switch($block['kind'])
                @case('teaser')
                <section class="issue-block issue-block--teaser">
                    <h2 class="issue-heading"><a href="{{ $block['address'] }}">{{ $block['heading'] }}</a></h2>
                    @if ($block['title'])
                    <p class="issue-subhead">{{ $block['title'] }}</p>
                    @endif
                    @if ($block['image'])
                    <a class="issue-picture" href="{{ $block['address'] }}"><img src="{{ $block['image'] }}" alt="{{ $block['title'] ?? $block['heading'] }}" loading="lazy"></a>
                    @endif
                    @if ($block['html'] !== '')
                    <div class="issue-words work-body">{!! $block['html'] !!}</div>
                    @endif
                    <p class="issue-more"><a href="{{ $block['address'] }}">{{ $block['refeature'] ? 'Revisit' : 'Read more' }} <span aria-hidden="true">→</span></a><span>{{ $block['division'] }}</span></p>
                </section>
                @break

                @case('banner')
                {{-- Language and Featured Steve ran as a banner: the section, this week's piece. --}}
                <section class="issue-block issue-block--banner">
                    <a class="issue-banner" href="{{ $block['address'] }}">
                        @if ($block['image'])<span class="issue-banner-picture"><img src="{{ $block['image'] }}" alt="" loading="lazy"></span>@endif
                        <span class="issue-banner-text">
                            <span class="issue-heading">{{ $block['heading'] }}</span>
                            <span class="issue-banner-title">{{ $block['title'] }}</span>
                            <span class="issue-banner-more">See this week's {{ $block['heading'] }} <span aria-hidden="true">→</span></span>
                        </span>
                    </a>
                </section>
                @break

                @case('tune')
                <section class="issue-block issue-block--tune">
                    <h2 class="issue-heading">{{ $block['heading'] }}</h2>
                    <div class="issue-tune">
                        @if ($block['image'])
                        <img class="issue-tune-picture" src="{{ $block['image'] }}" alt="{{ $block['artist'] }}" loading="lazy">
                        @endif
                        <div class="issue-words work-body">
                            @if ($block['track'])
                            <p class="issue-tune-track">Tune of the Week: <b>“{{ $block['track'] }}”</b></p>
                            @endif
                            {!! $block['html'] !!}
                        </div>
                    </div>
                </section>
                @break

                @case('back')
                <section class="issue-block issue-block--back">
                    <h2 class="issue-heading">{{ $block['heading'] }}</h2>
                    <ul class="issue-back">
                        @foreach ($block['entries'] as $entry)
                        <li><a href="{{ $entry['address'] }}">{{ $entry['title'] }}</a><span>{{ $day($entry['date'])->format('m.d.y') }}</span></li>
                        @endforeach
                    </ul>
                </section>
                @break
            @endswitch
            @endforeach

            {{-- Earlier and later issues, in the order they ran. --}}
            <nav class="issue-pager" aria-label="Issues">
                @if ($page->earlier)
                <a class="issue-pager-link issue-pager-link--earlier" href="{{ $page->earlier['address'] }}"><span class="issue-pager-when"><span aria-hidden="true">←</span> Earlier issue · {{ $run($page->earlier['starts_on'], $page->earlier['ends_on']) }}</span><span class="issue-pager-title">{{ $page->earlier['title'] }}</span></a>
                @else
                <span class="issue-pager-link issue-pager-link--none">The first issue</span>
                @endif
                @if ($page->later)
                <a class="issue-pager-link issue-pager-link--later" href="{{ $page->later['address'] }}"><span class="issue-pager-when">Later issue · {{ $run($page->later['starts_on'], $page->later['ends_on']) }} <span aria-hidden="true">→</span></span><span class="issue-pager-title">{{ $page->later['title'] }}</span></a>
                @else
                <span class="issue-pager-link issue-pager-link--none">The final issue</span>
                @endif
            </nav>
            <p class="caption">Issue No. {{ $page->number }} · {{ $run($page->starts_on, $page->ends_on) }}</p>
        </article>

        <aside class="plate notes" aria-label="Notes" data-snap>
            <div class="notes-inner">
                <h2 class="notes-title">Notes</h2>
                <dl class="facts">
                    <div><dt>Issue</dt><dd>No. {{ $page->number }} of {{ $page->of }}</dd></div>
                    <div><dt>Ran</dt><dd>{{ $run($page->starts_on, $page->ends_on) }}</dd></div>
                    <div><dt>New this week</dt><dd>{{ $page->works }} {{ $page->works === 1 ? 'work' : 'works' }}</dd></div>
                </dl>
                <a class="action" href="/issues/#year-{{ substr($page->starts_on, 0, 4) }}">Every issue of {{ substr($page->starts_on, 0, 4) }}</a>
            </div>
            <p class="caption">Notes to Issue No. {{ $page->number }}</p>
        </aside>
    </div>
</main>

@include('_partials.footer')
@endsection
