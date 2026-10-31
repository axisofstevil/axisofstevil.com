---
title: Issues — The Axis of Stevil
description: Every weekly issue of the Axis of Stevil, May 2005 to May 2007.
---
@extends('_layouts.master')

@php
    $issues = collect($page->issues)->map(fn ($i) => (array) (is_array($i) ? $i : $i->toArray()));
    $numbered = $issues->sortBy('starts_on')->values()->map(fn ($i, $n) => [...$i, 'number' => $n + 1]);
    // Each year's issues, newest first, as the archive listed them.
    $years = $numbered->sortByDesc('starts_on')->groupBy(fn ($i) => substr($i['starts_on'], 0, 4))->sortKeysDesc();
    $short = fn ($date) => \Carbon\CarbonImmutable::parse($date)->format('M j');
@endphp

@section('body')
<main id="main" class="report">
    <div class="masthead">
        @include('_partials.running-head', ['division' => 'issues', 'here' => 'issues'])

        <div class="plate division-bar" data-snap>
            <p class="division-figure"><b>{{ $issues->count() }}</b> <span>weekly issues · {{ $years->keys()->last() }}–{{ $years->keys()->first() }}</span></p>
        </div>
    </div>

    <div class="index-sheet">
        <section class="plate index index--issues" aria-label="Issues" data-snap>
            @foreach ($years as $year => $group)
            <div class="index-group" id="year-{{ $year }}">
                <h2 class="index-letter">{{ $year }}</h2>
                <ul>
                    @foreach ($group as $issue)
                    <li><a href="{{ $issue['address'] }}">{{ $issue['title'] }}</a><span>No. {{ $issue['number'] }} · {{ $short($issue['starts_on']) }}</span></li>
                    @endforeach
                </ul>
            </div>
            @endforeach
            <p class="caption">Index · Issues</p>
        </section>
        <nav class="plate thumb-index" aria-label="Years" data-snap>
            <div class="thumb-list">
                @foreach ($years->keys() as $year)
                <a href="#year-{{ $year }}">{{ $year }}</a>
                @endforeach
            </div>
        </nav>
    </div>
</main>

@include('_partials.footer')
@endsection
