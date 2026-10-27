<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $page->title }}</title>
        @if ($page->description)
        <meta name="description" content="{{ $page->description }}">
        @endif
        <link rel="canonical" href="{{ $page->baseUrl }}{{ $page->link() }}">
        <link rel="preload" href="/assets/fonts/jost.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/assets/fonts/source-serif-4.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="{{ $page->assetCss }}">
        <script>document.documentElement.classList.add('js')</script>
        <meta name="theme-color" content="#ff1a00">
        <link rel="icon" href="/assets/logo/axis-symbol.svg" type="image/svg+xml">
    </head>
    <body class="@yield('body_class')">
        <a class="skip" href="#main">Skip to the report</a>
        @yield('body')
        <script src="{{ $page->assetJs }}" defer></script>
    </body>
</html>
