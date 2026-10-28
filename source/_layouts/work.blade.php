@extends('_layouts.master')

@php
    $filed = \Carbon\CarbonImmutable::parse($page->date)->format('F j, Y');
    $short = fn ($date) => \Carbon\CarbonImmutable::parse($date)->format('M j, Y');

    // The page takes the body's shape (App\WorkLayout): plate, movie, steve, entry, letter or article.
    $parts = \App\WorkLayout::split($__env->yieldContent('content'), $page->division, \App\WorkLayout::knownSizes());
    $sameQuestion = fn (string $a, string $b) => mb_strtolower(preg_replace('/\W+/u', '', strip_tags($a))) === mb_strtolower(preg_replace('/\W+/u', '', $b));

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

    {{-- On a Random Audit (site.js): which pull this is, and the way on. --}}
    <div class="plate audit-bar" data-audit-bar hidden>
        <p class="audit-status"><b><span class="audit-long">Random </span><span class="audit-word">Audit · </span>No. <span data-audit-no></span></b> <span class="audit-from">Pulled from {{ $page->division_name }}, {{ substr($page->date, 0, 4) }}</span></p>
        <div class="audit-controls">
            <button type="button" class="audit-next" data-audit-next>Pull another <span aria-hidden="true">→</span></button>
            <button type="button" class="audit-end" data-audit-end>End</button>
        </div>
    </div>

    <div class="spread" data-snap>
        <article class="plate work work--{{ $parts['layout'] }}" aria-labelledby="work-title" data-snap>
            @switch($parts['layout'])
                @case('plate')
                @case('movie')
                    {{-- The picture (or the player) is the work; the title labels it. --}}
                    @if ($parts['slides'])
                    {{-- Several pictures: one at a time, swiped or stepped through (site.js). --}}
                    <div class="work-plate work-body" data-slideshow>
                        <div class="slides" tabindex="0" aria-label="{{ count($parts['slides']) }} pictures; scroll sideways for the next">
                            @foreach ($parts['slides'] as $slide)
                            <figure class="slide">{!! $slide['html'] !!}@if ($slide['caption'])<figcaption>{{ $slide['caption'] }}</figcaption>@endif</figure>
                            @endforeach
                        </div>
                        <button type="button" class="slide-step" data-slide="-1" aria-label="Previous picture">←</button>
                        <button type="button" class="slide-step" data-slide="1" aria-label="Next picture">→</button>
                        <span class="slide-count" aria-live="polite"><b data-slide-at>1</b> of {{ count($parts['slides']) }}</span>
                    </div>
                    @else
                    <div class="work-plate work-body">{!! $parts['lead'] !!}</div>
                    @endif
                    <header class="work-label">
                        <h1 id="work-title" class="work-title">{{ $page->title }}</h1>
                    </header>
                    @if (trim(strip_tags($parts['body'], '<img><figure><video><iframe>')) !== '')
                    <div class="work-body">{!! $parts['body'] !!}</div>
                    @endif
                    @break

                @case('steve')
                    <header class="work-head">
                        <h1 id="work-title" class="work-title">{{ $page->title }}</h1>
                    </header>
                    <div class="steve-columns">
                        <div class="work-body work-body--prose">{!! $parts['body'] !!}</div>
                        @if ($parts['aside'] !== '')
                        <aside class="film-strip work-body" aria-label="Film strip">{!! $parts['aside'] !!}</aside>
                        @endif
                    </div>
                    @break

                @case('entry')
                    {{-- A dictionary entry: the word, how it's said, what it means; its illustration beside. --}}
                    <div class="entry">
                        <div class="entry-text">
                            <h1 id="work-title" class="entry-word">{{ $page->title }}</h1>
                            <div class="work-body entry-body">{!! $parts['body'] !!}</div>
                        </div>
                        @if ($parts['aside'] !== '')
                        <div class="entry-figure work-body">{!! $parts['aside'] !!}</div>
                        @endif
                    </div>
                    @break

                @case('letter')
                    {{-- A reader writes in; Stevil replies. --}}
                    <header class="letter-head">
                        <h1 id="work-title" class="letter-question">{{ $page->title }}</h1>
                        @if (! $sameQuestion($parts['quote'], $page->title))
                        <div class="letter-quote">{!! $parts['quote'] !!}</div>
                        @endif
                        @if ($parts['asker'] !== '')
                        <p class="letter-asker">{{ $parts['asker'] }}</p>
                        @endif
                    </header>
                    <p class="work-eyebrow letter-reply-label">Stevil replies</p>
                    <div class="work-body work-body--prose letter-reply">{!! $parts['body'] !!}</div>
                    @break

                @default
                    {{-- An article: its opening picture leads, then the headline and the piece. --}}
                    @if ($parts['lead'] !== '')
                    <div class="work-hero work-body">{!! $parts['lead'] !!}</div>
                    @endif
                    <header class="work-head">
                        <h1 id="work-title" class="work-title">{{ $page->title }}</h1>
                    </header>
                    <div class="work-body work-body--prose @if ($parts['dropCap']) work-body--opening @endif">{!! $parts['body'] !!}</div>
            @endswitch
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

                <button type="button" class="action audit-go" data-audit-start data-audit-off>A random work</button>
            </div>
            <p class="caption">Notes to Plate {{ $page->place }}</p>
        </aside>
    </div>
</main>

@include('_partials.footer')
@endsection
