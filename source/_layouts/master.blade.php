<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            // The head's facts: the page's address, its words, its picture (its own, else the site's card) and
            // what kind of thing it is, for search engines and link previews; its structured data (App\StructuredData).
            $collection = $page->_meta->collection ?? null;
            $url = $page->baseUrl.$page->link();
            $card = $page->social_image ?? $page->image ?? $page->baseUrl.'/assets/social/card.png';
            $article = in_array($collection, ['works', 'issue_pages'], true);
            // The owner's own words for this address (config 'meta'), over the page's.
            $override = json_decode(json_encode($page->meta), true)[$page->link()] ?? [];
            $title = $override['title'] ?? ($collection === 'works' ? ($page->page_title ?? $page->title).' — The Axis of Stevil' : $page->title);
            $description = $override['description'] ?? $page->description;
            $structured = \App\StructuredData::for($page, $description);
        @endphp
        <title>{{ $title }}</title>
        @if ($description)
        <meta name="description" content="{{ $description }}">
        @endif
        @if ($page->noindex)
        <meta name="robots" content="noindex">
        @else
        <link rel="canonical" href="{{ $url }}">
        @endif
        <meta property="og:site_name" content="The Axis of Stevil">
        <meta property="og:locale" content="en_US">
        <meta property="og:type" content="{{ $article ? 'article' : 'website' }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:url" content="{{ $url }}">
        @if ($description)
        <meta property="og:description" content="{{ $description }}">
        @endif
        <meta property="og:image" content="{{ $card }}">
        <meta property="og:image:alt" content="{{ $page->image || $page->social_image ? $page->title : 'The Axis of Stevil' }}">
        @if ($collection === 'works')
        <meta property="article:published_time" content="{{ $page->date }}">
        @if ($page->updated)
        <meta property="article:modified_time" content="{{ $page->updated }}">
        @endif
        @if ($page->division_name)
        <meta property="article:section" content="{{ $page->division_name }}">
        @endif
        @foreach ($page->keywords ?? [] as $keyword)
        <meta property="article:tag" content="{{ $keyword }}">
        @endforeach
        @elseif ($collection === 'issue_pages')
        <meta property="article:published_time" content="{{ $page->starts_on }}">
        @endif
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        @if ($description)
        <meta name="twitter:description" content="{{ $description }}">
        @endif
        <link rel="alternate" type="application/rss+xml" title="The Axis of Stevil" href="{{ $page->baseUrl }}/feed.xml">
        <meta name="twitter:image" content="{{ $card }}">
        <link rel="preload" href="/assets/fonts/jost.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/assets/fonts/source-serif-4.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="{{ $page->assetCss }}">
        <script>document.documentElement.classList.add('js');try{var v=localStorage.getItem('axis.divisionView');if(v==='yearbook')document.documentElement.dataset.divisionView=v}catch(e){}</script>
        <meta name="theme-color" content="#c73120">
        {{-- Turbo: a link fetches the next page and swaps the body in place of a full load, so the player
             (kept across the swap) plays on; the page's setup runs again after each visit (site.js). Both
             scripts sit in the head, which Turbo keeps, so neither runs twice. --}}
        <script src="/assets/turbo.8.0.13.js" defer></script>
        <script src="{{ $page->assetJs }}" defer></script>
        {{-- The icon: the symbol as SVG where that is understood, a PNG and the .ico elsewhere, the home-screen icon on
             its own stock, and the manifest for the rest. --}}
        <link rel="icon" href="/favicon.ico" sizes="48x48 32x32 16x16">
        <link rel="icon" href="/assets/logo/axis-symbol.svg" type="image/svg+xml">
        <link rel="icon" href="/assets/logo/icon-192.png" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" href="/assets/logo/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
    </head>
    <body class="@yield('body_class')">
        <a class="skip" href="#main">Skip to the report</a>
        @yield('body')
        {{-- The grid set and snapped now, before the first paint, so nothing moves when the site's script arrives. --}}
        <script>{!! $page->snapInline !!}</script>
        {{-- In the body, which Turbo swaps whole, so a visit never keeps the last page's data. --}}
        @if ($structured !== [])
        <script type="application/ld+json">{!! json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @include('_partials.player')
    </body>
</html>
