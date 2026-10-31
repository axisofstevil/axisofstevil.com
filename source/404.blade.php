---
title: Not on record — The Axis of Stevil
permalink: 404.html
noindex: true
---
@extends('_layouts.master')

@section('body')
<main id="main" class="report">
    <section class="plate statement" data-snap>
        <h1 class="section-title">Not on record</h1>
        <p class="audit-lede">No work has been filed at this address. It may have moved, or it may never have been filed at all.</p>
        {{-- From the owner's own library (g.vcr.bz): the records consulted, at length, to no avail. --}}
        <figure class="not-on-record"><img src="https://g.vcr.bz/ryker.gif" alt="Commander Riker strokes his beard, thinking it over" width="696" height="528" loading="lazy"></figure>
        <p><a class="action" href="/">Return to the report</a></p>
        <p class="caption">Plate 404</p>
    </section>
</main>

@include('_partials.footer')
@endsection
