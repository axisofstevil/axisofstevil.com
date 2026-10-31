---
title: Not on record — The Axis of Stevil
permalink: 404.html
noindex: true
---
@extends('_layouts.master')

@section('body')
<main id="main" class="report">
    <section class="plate statement" data-snap>
        {{-- Leads like a work's picture (the same dotted container), from the owner's own library (g.vcr.bz). --}}
        <div class="work-hero work-body"><img src="https://g.vcr.bz/compenguin.jpg" alt="A penguin holds the mouse of a desktop computer whose screen shows a green heart" width="727" height="325"></div>
        <h1 class="section-title">Not on record</h1>
        <p class="audit-lede">No work has been filed at this address. It may have moved, or it may never have been filed at all.</p>
        <p><a class="action" href="/">Return to the report</a></p>
        <p class="caption">Plate 404</p>
    </section>
</main>

@include('_partials.footer')
@endsection
