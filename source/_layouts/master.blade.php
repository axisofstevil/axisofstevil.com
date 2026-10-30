<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ ($page->_meta->collection ?? null) === 'works' ? $page->title.' — The Axis of Stevil' : $page->title }}</title>
        @if ($page->description)
        <meta name="description" content="{{ $page->description }}">
        @endif
        <link rel="canonical" href="{{ $page->baseUrl }}{{ $page->link() }}">
        <meta property="og:site_name" content="The Axis of Stevil">
        <meta property="og:title" content="{{ $page->title }}">
        <meta property="og:url" content="{{ $page->baseUrl }}{{ $page->link() }}">
        @if ($page->image)
        <meta property="og:image" content="{{ $page->image }}">
        <meta name="twitter:card" content="summary_large_image">
        @endif
        <link rel="preload" href="/assets/fonts/jost.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/assets/fonts/source-serif-4.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="{{ $page->assetCss }}">
        <script>document.documentElement.classList.add('js');try{var v=localStorage.getItem('axis.divisionView');if(v==='yearbook')document.documentElement.dataset.divisionView=v}catch(e){}</script>
        <meta name="theme-color" content="#c73120">
        <link rel="icon" href="/assets/logo/axis-symbol.svg" type="image/svg+xml">
    </head>
    <body class="@yield('body_class')">
        <a class="skip" href="#main">Skip to the report</a>
        @yield('body')
        @include('_partials.player')
        <script src="{{ $page->assetJs }}" defer></script>
    </body>
</html>
