---
title: Something went wrong — The Axis of Stevil
permalink: error.html
noindex: true
---
@php
    // The channel's page at error (config 'error'): its lead picture in a work's picture container, its title, its
    // body and its summary; the site's own words without it. The title names the tab too.
    $error = $page->error ? json_decode(json_encode($page->error), true) : null;

    if ($error) {
        $page->set('title', $error['title'].' — The Axis of Stevil');
    }
@endphp
@extends('_layouts.master')

@section('body')
<main id="main" class="report">
    <section class="plate statement" aria-labelledby="error-title" data-snap>
        @if ($error && $error['image'])
        <div class="work-hero work-body"><img src="{{ $error['image'] }}" alt="{{ $error['title'] }}"></div>
        @endif
        <h1 id="error-title" class="section-title">{{ $error['title'] ?? 'Something went wrong' }}</h1>
        @if ($error && trim($error['content']) !== '')
        <div class="work-body work-body--prose">{!! $error['content'] !!}</div>
        @endif
        <p class="audit-lede">{{ $error['summary'] ?? 'The report could not be served just now. Try again in a moment.' }}</p>
        <p><a class="action" href="/">Return to the report</a></p>
        <p class="caption">Plate 500</p>
    </section>
</main>

@include('_partials.footer')
@endsection
