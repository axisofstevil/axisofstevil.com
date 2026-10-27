---
title: The Axis of Stevil — Annual Report
description: Random insanity at its finest. The complete works of the Axis of Stevil since 2005, reported in full to its shareholders.
---
@extends('_layouts.master')

@php
    $works = collect($page->works->toArray());
    $issues = collect($page->issues->toArray());
    $divisions = collect($page->divisions->toArray());
    $pages = $page->pages->toArray();
    $authenticity = $pages['authenticity'] ?? null;

    // The four divisions the site has always led with, in its own order.
    $lead = $divisions->whereIn('slug', ['publications', 'language', 'featured-steves', 'ask-stevil'])->values();

    // Issues per quarter, across the years they ran.
    $years = $issues->map(fn ($i) => (int) substr($i['starts_on'], 0, 4));
    $quarters = [];
    foreach (range($years->min(), $years->max()) as $year) {
        foreach ([1, 2, 3, 4] as $q) {
            $quarters["{$year} Q{$q}"] = $issues->filter(fn ($i) => (int) substr($i['starts_on'], 0, 4) === $year && (int) ceil(substr($i['starts_on'], 5, 2) / 3) === $q)->count();
        }
    }
    // The run ends with the last issue, not with its year.
    while ($quarters && end($quarters) === 0) {
        array_pop($quarters);
    }
    $peak = max($quarters) ?: 1;
    $largest = $divisions->max('count') ?: 1;

    // The exhibit shown before anyone asks for one: a work with a picture, a new one each day.
    $pictured = $works->filter(fn ($w) => $w['image'] ?? null)->values();
    $exhibit = $pictured->isNotEmpty() ? $pictured[$page->build_time->dayOfYear % $pictured->count()] : null;
    $fmt = fn ($date) => \Carbon\CarbonImmutable::parse($date)->format('M j, Y');
@endphp

@section('body_class', 'home')

@section('body')
<main id="main" class="report">
    <section class="fold" aria-label="The report">
        <div class="fold-top" data-snap data-fill>
        <header class="plate cover" data-snap>
            <div class="cover-chart cover-bars" aria-hidden="true">
                @foreach ($quarters as $label => $count)
                <i style="--h: {{ max(2, round($count / $peak * 100)) }}%"></i>
                @endforeach
            </div>
            <h1 class="cover-title">Annual<br>Report</h1>
            <div class="cover-band">
                <img class="cover-lockup" src="/assets/logo/axis-wordmark.svg" alt="The Axis of Stevil" width="456" height="76.5">
                <p class="cover-imprint"><span>Circa {{ $page->build_time->year }}</span><span class="caption">Plate 1 · The Cover</span></p>
            </div>
        </header>

        <article class="plate letter" aria-labelledby="letter-title" data-snap>
            <h2 id="letter-title" class="letter-title">To Our Shareholders</h2>
            <div class="letter-body">
                @if ($authenticity['summary'] ?? null)<p>{{ $authenticity['summary'] }}</p>@endif
                <p>We are proud to report that random insanity has remained at the finest status, and that every work on record remains in active service.</p>
                <p class="signed">Stevil</p>
            </div>
            <dl class="figures">
                <div><dt>Founded</dt><dd>{{ $page->founded }}</dd></div>
                <div><dt>Weekly issues</dt><dd>{{ $issues->count() }}</dd></div>
                <div><dt>Works on record</dt><dd>{{ number_format($works->count()) }}</dd></div>
            </dl>
            <p class="caption">Plate 2 · Letter to Shareholders</p>
        </article>
        </div>

        <nav class="divisions" aria-label="Divisions" data-snap>
            @foreach ($lead as $i => $division)
            <a class="plate unit" data-snap href="/{{ $division['slug'] }}/" style="--i: {{ $i }}">
                <span class="unit-name">{{ $division['name'] }}</span>
                <span class="unit-count">{{ $division['count'] }} works</span>
                <span class="caption">Plate {{ $i + 3 }}</span>
            </a>
            @endforeach
            <a class="plate unit" data-snap href="#results" style="--i: 4">
                <span class="unit-name">Quarterly Results</span>
                <span class="unit-count">{{ $issues->count() }} issues</span>
                <span class="caption">Plate 7</span>
            </a>
            <a class="plate unit unit-audit" data-snap href="#audit" data-audit style="--i: 5">
                <span class="unit-name">Random Audit</span>
                <span class="unit-count">Pull any work</span>
                <span class="caption">Plate 8</span>
            </a>
        </nav>
    </section>

    <section class="plate statement" aria-labelledby="divisions-title" data-snap>
        <h2 id="divisions-title" class="section-title">Report of the Divisions</h2>
        <table class="ledger">
            <colgroup><col class="c-division"><col class="c-works"><col class="c-share"><col class="c-filed"></colgroup>
            <thead><tr><th scope="col">Division</th><th scope="col" class="num">Works</th><th scope="col" class="share-col">Share of output</th><th scope="col" class="filed-col">Most recently filed</th></tr></thead>
            <tbody>
                @foreach ($divisions as $division)
                @php($filed = $works->where('division', $division['slug'])->take(3))
                <tr>
                    <th scope="row"><a href="/{{ $division['slug'] }}/">{{ $division['name'] }}</a></th>
                    <td class="num">{{ $division['count'] }}</td>
                    <td class="share-col"><span class="share" style="--w: {{ round($division['count'] / $largest * 100) }}%"></span></td>
                    <td class="filed">
                        @foreach ($filed as $work)<a href="{{ $work['address'] }}">{{ $work['title'] }}</a>@endforeach
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr><th scope="row">Total</th><td class="num">{{ $divisions->sum('count') }}</td><td colspan="2"></td></tr></tfoot>
        </table>
        <p class="caption">Plate 9 · Report of the Divisions</p>
    </section>

    <section id="results" class="plate results" aria-labelledby="results-title" data-snap>
        <h2 id="results-title" class="section-title">Quarterly Results</h2>
        <figure class="chart">
            <div class="bars">
                @foreach ($quarters as $label => $count)
                <div @class(['bar', 'year-start' => str_ends_with($label, 'Q1')]) style="--h: {{ round($count / $peak * 100) }}%"><span class="bar-count">{{ $count }}</span><span class="bar-label">{{ $label }}</span></div>
                @endforeach
            </div>
            <figcaption>Weekly issues released each quarter, on the Monday, Wednesday and Friday cadence. Each year opens in red.</figcaption>
        </figure>
        <ol class="issues">
            @foreach ($issues->take(6) as $issue)
            <li><span class="issue-title">{{ $issue['title'] }}</span><span class="issue-dates">{{ $fmt($issue['starts_on']) }}</span></li>
            @endforeach
        </ol>
        <p class="caption">Plate 10 · Quarterly Results</p>
    </section>

    <section id="audit" class="plate audit" aria-labelledby="audit-title" data-snap>
        <h2 id="audit-title" class="section-title">Random Audit</h2>
        <p class="audit-lede">Any work on record may be pulled for inspection at any time.</p>
        @if ($exhibit)
        <figure class="exhibit" data-exhibit aria-live="polite">
            <a class="exhibit-image" href="{{ $exhibit['address'] }}"><img src="{{ $exhibit['image'] }}" alt="" loading="lazy"></a>
            <figcaption>
                <a class="exhibit-title" href="{{ $exhibit['address'] }}">{{ $exhibit['title'] }}</a>
                <dl class="exhibit-facts">
                    <div><dt>Division</dt><dd data-division>{{ $exhibit['division_name'] ?? '' }}</dd></div>
                    <div><dt>Filed</dt><dd data-filed>{{ $fmt($exhibit['date']) }}</dd></div>
                </dl>
                <span class="stamp" aria-hidden="true">Audited</span>
            </figcaption>
        </figure>
        @endif
        <button type="button" class="action" data-audit>Audit another work</button>
        <p class="caption">Plate 11 · Random Audit</p>
    </section>
</main>

@include('_partials.footer')
@endsection
