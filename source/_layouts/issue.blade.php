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

    // The front page, set by what each piece is; each place keeps the order the week ran them.
    $blocks = collect($page->blocks)->map(fn ($b) => (array) $b);
    $stories = $blocks->where('kind', 'story')->values();
    $plates = $blocks->where('kind', 'plate')->values();
    // The lead: the week's first story; a week without one leads with its first plate.
    $lead = $stories->first() ?? $plates->first();
    $stories = $stories->reject(fn ($b) => $b === $lead)->values();
    $plates = $plates->reject(fn ($b) => $b === $lead)->values();
    $rail = $blocks->whereIn('kind', ['language', 'steve', 'letter', 'notice'])->values();
    // A week with nothing for the rail sets its other stories there, beside the lead; otherwise they pair up beneath it.
    if ($rail->isEmpty() && $stories->isNotEmpty()) {
        $rail = $stories->map(fn ($b) => [...$b, 'kind' => 'story'])->values();
        $stories = collect();
    }
    $strips = $blocks->where('kind', 'strip')->values();
    $tune = $blocks->firstWhere('kind', 'tune');
    $archive = $blocks->firstWhere('kind', 'archive');

    $jump = fn ($b) => 'Continued in '.$b['division'].', Plate '.$b['place'];
@endphp

@section('body')
<main id="main" class="report">
    @include('_partials.running-head', ['division' => 'issues', 'here' => 'issue'])

    <article class="plate issue" aria-labelledby="issue-title" data-snap>
        {{-- The masthead: volume and number, the 2005 dateline, the week. --}}
        <header class="issue-masthead">
            <p class="issue-folio">
                <span>Vol. {{ $volume }} · No. {{ $page->number }}</span>
                <span class="issue-satellite"><b>LIVE!</b> from the Stevil Satellite</span>
                <span>{{ $run($page->starts_on, $page->ends_on) }}</span>
            </p>
            <h1 id="issue-title" class="issue-name">{{ $page->name }}</h1>
            <p class="issue-folio issue-folio--under">
                <span>The week of {{ $day($page->starts_on)->format('l, F j, Y') }}</span>
                <span>{{ $page->works }} new {{ $page->works === 1 ? 'work' : 'works' }}</span>
            </p>
        </header>

        <div class="issue-front @if ($rail->isEmpty()) issue-front--full @endif">
            <div class="issue-main">
                @if ($lead)
                @if ($lead['kind'] === 'story')
                <section class="issue-lead" aria-label="The lead">
                    <p class="issue-kicker">{{ $lead['kicker'] ?? ($lead['refeature'] ? 'Classic Stevil' : $lead['division']) }}</p>
                    <h2 class="issue-headline"><a href="{{ $lead['address'] }}">{{ $lead['title'] }}</a></h2>
                    {{-- The picture beside the words where there's room, the words in columns. --}}
                    <div class="issue-lead-body">
                        @if ($lead['image'] && ! $lead['shows_picture'])
                        <a class="issue-lead-picture" href="{{ $lead['address'] }}"><img src="{{ $lead['image'] }}" alt="{{ $lead['title'] }}"></a>
                        @endif
                        <div class="issue-copy issue-copy--columns">{!! $lead['html'] !!}</div>
                    </div>
                    @unless ($lead['whole'])
                    <a class="issue-jump" href="{{ $lead['address'] }}">{{ $jump($lead) }} <span aria-hidden="true">→</span></a>
                    @endunless
                </section>
                @else
                {{-- No story this week: its first picture leads, whole. --}}
                <figure class="issue-lead issue-lead--plate">
                    <div class="issue-plate-picture">{!! $lead['html'] !== '' ? $lead['html'] : '<img src="'.e($lead['image']).'" alt="'.e($lead['title']).'">' !!}</div>
                    <figcaption><a href="{{ $lead['address'] }}">{{ $lead['kicker'] ?? $lead['title'] }}</a></figcaption>
                </figure>
                @endif
                @endif

                @if ($stories->isNotEmpty())
                <div class="issue-stories @if ($stories->count() === 1) issue-stories--one @endif">
                @foreach ($stories as $story)
                <section class="issue-story">
                    <p class="issue-kicker">{{ $story['kicker'] ?? ($story['refeature'] ? 'Classic Stevil' : ($story['series'] ?? $story['division'])) }}</p>
                    <h2 class="issue-story-headline"><a href="{{ $story['address'] }}">{{ $story['title'] }}</a></h2>
                    <div class="issue-story-body">
                        @if ($story['image'] && ! $story['shows_picture'])
                        <a class="issue-story-picture" href="{{ $story['address'] }}"><img src="{{ $story['image'] }}" alt="" loading="lazy"></a>
                        @endif
                        <div class="issue-copy">{!! $story['html'] !!}</div>
                    </div>
                    @unless ($story['whole'])
                    <a class="issue-jump" href="{{ $story['address'] }}">{{ $jump($story) }} <span aria-hidden="true">→</span></a>
                    @endunless
                </section>
                @endforeach
                </div>
                @endif
            </div>

            @if ($rail->isNotEmpty())
            <aside class="issue-rail" aria-label="Also in this issue">
                @foreach ($rail as $box)
                @switch($box['kind'])
                    @case('language')
                    <section class="issue-box issue-box--language">
                        <p class="issue-box-label"><a href="{{ $box['address'] }}">Language</a></p>
                        <div class="issue-copy">{!! $box['html'] !!}</div>
                    </section>
                    @break

                    @case('steve')
                    <section class="issue-box issue-box--steve">
                        <p class="issue-box-label">Featured Steve</p>
                        @if ($box['image'])
                        <a class="issue-steve-portrait" href="{{ $box['address'] }}"><img src="{{ $box['image'] }}" alt="{{ $box['title'] }}" loading="lazy"></a>
                        @endif
                        <h3 class="issue-box-title"><a href="{{ $box['address'] }}">{{ $box['title'] }}</a></h3>
                        <div class="issue-copy">{!! $box['html'] !!}</div>
                        <a class="issue-jump" href="{{ $box['address'] }}">{{ $jump($box) }} <span aria-hidden="true">→</span></a>
                    </section>
                    @break

                    @case('letter')
                    <section class="issue-box issue-box--letter">
                        <p class="issue-box-label"><a href="{{ $box['address'] }}">Ask Stevil</a></p>
                        <div class="issue-copy">{!! $box['html'] !!}</div>
                        @unless ($box['whole'])
                        <a class="issue-jump" href="{{ $box['address'] }}">{{ $jump($box) }} <span aria-hidden="true">→</span></a>
                        @endunless
                    </section>
                    @break

                    @case('story')
                    <section class="issue-box issue-box--story">
                        <p class="issue-box-label">{{ $box['kicker'] ?? ($box['refeature'] ? 'Classic Stevil' : ($box['series'] ?? $box['division'])) }}</p>
                        <h3 class="issue-box-title"><a href="{{ $box['address'] }}">{{ $box['title'] }}</a></h3>
                        @if ($box['image'] && ! $box['shows_picture'])
                        <a class="issue-box-picture" href="{{ $box['address'] }}"><img src="{{ $box['image'] }}" alt="" loading="lazy"></a>
                        @endif
                        <div class="issue-copy">{!! $box['html'] !!}</div>
                        @unless ($box['whole'])
                        <a class="issue-jump" href="{{ $box['address'] }}">{{ $jump($box) }} <span aria-hidden="true">→</span></a>
                        @endunless
                    </section>
                    @break

                    @case('notice')
                    <section class="issue-box issue-box--notice">
                        <p class="issue-box-label">From the offices</p>
                        <h3 class="issue-box-title"><a href="{{ $box['address'] }}">{{ $box['kicker'] ?? $box['title'] }}</a></h3>
                        <div class="issue-copy">{!! $box['html'] !!}</div>
                    </section>
                    @break
                @endswitch
                @endforeach
            </aside>
            @endif
        </div>

        @if ($strips->isNotEmpty())
        <section class="issue-band issue-funnies" aria-labelledby="funnies-{{ $page->number }}">
            <h2 class="issue-band-title" id="funnies-{{ $page->number }}">The Funnies</h2>
            @foreach ($strips as $strip)
            <figure class="issue-strip">
                <div class="issue-strip-picture">{!! $strip['shows_picture'] ? $strip['html'] : ($strip['image'] ? '<img src="'.e($strip['image']).'" alt="'.e($strip['title']).'" loading="lazy">' : $strip['html']) !!}</div>
                <figcaption><a href="{{ $strip['address'] }}"><b>{{ $strip['series'] ?? $strip['division'] }}</b> {{ $strip['title'] }}</a></figcaption>
            </figure>
            @endforeach
        </section>
        @endif

        @if ($plates->isNotEmpty())
        <section class="issue-band issue-plates-band" aria-labelledby="plates-{{ $page->number }}">
            <h2 class="issue-band-title" id="plates-{{ $page->number }}">Also this week</h2>
            <div class="issue-plates @if ($plates->count() === 1) issue-plates--one @endif">
                @foreach ($plates as $plate)
                <figure class="issue-plate">
                    @if ($plate['kicker'])
                    <p class="issue-kicker">{{ $plate['kicker'] }}</p>
                    @endif
                    <div class="issue-plate-picture issue-copy">{!! $plate['html'] !== '' ? $plate['html'] : '<img src="'.e($plate['image']).'" alt="'.e($plate['title']).'" loading="lazy">' !!}</div>
                    <figcaption><a href="{{ $plate['address'] }}">{{ $plate['title'] }}</a><span>{{ $plate['division'] }}</span></figcaption>
                </figure>
                @endforeach
            </div>
        </section>
        @endif

        @if ($tune || $archive)
        <div class="issue-foot @if (! $tune || ! $archive) issue-foot--one @endif">
            @if ($tune)
            <section class="issue-tune" aria-label="Tune of the Week">
                <p class="issue-box-label">Tune of the Week</p>
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
