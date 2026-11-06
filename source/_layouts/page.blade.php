@extends('_layouts.master')

@php
    // The page's body, laid out as an article (App\WorkLayout): its opening picture leads.
    $parts = \App\WorkLayout::split($__env->yieldContent('content'), 'organisation', \App\WorkLayout::knownSizes());

    // A page's lead image leads it, in the picture's container, as a work's opening picture does; an opening
    // picture that isn't the lead stays in the body.
    if ($page->image && ! str_contains($parts['lead'], $page->image)) {
        $parts['body'] = $parts['lead'].$parts['body'];
        $parts['lead'] = '<p><img src="'.e($page->image).'" alt="'.e($page->title).'"></p>';
    }

    $body = trim(strip_tags($parts['body'], '<img><figure><video><iframe>')) !== '';
@endphp

@section('body')
<main id="main" class="report">
    @include('_partials.running-head')

    <div class="spread" data-snap>
        <article class="plate work work--article" aria-labelledby="work-title" data-snap data-pagefind-body>
            @include('_partials.search-facts', ['facts' => ['kind' => 'The Organisation', 'image' => $page->image]])
            @if ($parts['lead'] !== '')
            <div class="work-hero work-body">{!! $parts['lead'] !!}</div>
            @endif
            <header class="work-head">
                <h1 id="work-title" class="work-title">{{ $page->title }}</h1>
            </header>

            @if ($body)
            <div class="work-body work-body--prose @if ($parts['dropCap']) work-body--opening @endif">{!! $parts['body'] !!}</div>
            @elseif ($page->summary)
            <div class="work-body work-body--prose"><p>{{ $page->summary }}</p></div>
            @endif

            @if ($page->timeline && count($page->timeline))
            {{-- The chronology, newest first, as the yearbook runs: a row a year, its events in their own order. --}}
            <ol class="chronology" reversed>
                @foreach (collect($page->timeline)->groupBy('year')->reverse() as $events)
                <li>
                    <span class="chronology-year">{{ $events->first()['label'] }}</span>
                    <ul class="chronology-events">
                        @foreach ($events as $event)
                        <li class="chronology-event">{!! $event['html'] !!}</li>
                        @endforeach
                    </ul>
                </li>
                @endforeach
            </ol>
            @endif

            <p class="caption">{{ $page->label }} · The Organization</p>
        </article>

        <aside class="plate notes" aria-label="The Organization" data-snap>
            <div class="notes-inner">
                <h2 class="notes-title">The Organization</h2>
                <ul class="organisation-index">
                    @foreach ($page->organisation as $item)
                    <li><a href="{{ $item['address'] }}" @if ($item['address'] === $page->address) aria-current="page" @endif>{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>

            </div>
            <p class="caption">Notes · The Organization</p>
        </aside>
    </div>
</main>

@include('_partials.footer')
@endsection
