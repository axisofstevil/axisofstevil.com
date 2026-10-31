<?php

/*
 * The works, divisions, issues, pages and redirects are read at build time
 * from the channel at CHANNEL_URL (app/Services/RemoteData.php).
 */
return [
    'production' => false,
    // Absolute links (canonical, social cards) always name the real site.
    'baseUrl' => 'https://www.axisofstevil.com',
    'title' => 'The Axis of Stevil',
    'tagline' => 'Random insanity at its finest.',
    'founded' => 2005,
    'build_time' => null,

    // Set by App\Services\Assets: the content-named stylesheet and script.
    'assetCss' => null,
    'assetJs' => null,

    // The channel, without a trailing /entries.
    'channel_url' => env('CHANNEL_URL'),

    // Dates are the publication's own: midnight in New York.
    'timezone' => 'America/New_York',

    // The week's headlines from the world outside, kept by hand in source/_data/headlines.json, keyed by the issue's first day.
    'headlines' => json_decode(file_get_contents(__DIR__.'/source/_data/headlines.json'), true)['weeks'] ?? [],

    // Filled by App\Services\RemoteData.
    'divisions' => [],
    'issues' => [],
    'works' => [],
    'pages' => [],
    'organisation' => [],
    'redirects' => [],
    // The Gallery (the channel's first gallery): its name, count and photographers, for the running head (set by RemoteData; null without one).
    'gallery' => null,
    // The Tunes of the Week with audio, oldest first: the site's playlist, carried by every page for the player (set by RemoteData).
    'tunes' => [],
    // The Tunes section: its count and artists, for the running head (set by RemoteData; null without tunes).
    'tunes_section' => null,
    // The owner's words that open /llms.txt: the channel's page at llms.txt, as written (set by RemoteData; null without one).
    'llms' => null,

    // A division's footnote, on every one of its works, in the owner's words: why the division does what it does
    // (the 2005 site said as much on each page). By the division's slug; a division without one has no footnote.
    'explainers' => [
        'featured-steves' => 'Steves and Stevens are everywhere. Seeing so many makes it very difficult to truly recognize the great Steves — and great Steves are what hold this organization together. So every so often The Axis of Stevil shines a little light on one of them — famous, local, fictional, feline or honorary — who deserves a little credit for what they do. They may not be an official member yet, but we salute them all the same.',
        'language' => 'The Axis of Stevil has many goals, one of which is to educate its followers. One of the first impressions people gain about other people is a speech impression. A person\'s memory will retain a lot of information about certain people\'s speech. Among those, vocabulary rises to the top. Possessing a good vocabulary is as important as learning how to ride a bike. With or without it you will get to your destination, but with it you will get there much faster. To that end, The Axis of Stevil curates and publishes these vocabulary words that will prove useful and practical in your everyday life.',
    ],

    // A page's title and description for search listings, link previews and the browser tab, where the owner wants
    // other words than the page's own (the 2005 site's, which rank): by the page's address, either key optional.
    // The page itself (its heading, its breadcrumb) keeps its name.
    'meta' => [
        '/publications/' => [
            'title' => 'Words and Pictures — The Axis of Stevil',
            'description' => 'Read the most amazing stories the universe has to offer, brought to you every Monday, Wednesday, and Friday by The Axis of Stevil.',
        ],
        '/language/' => [
            'title' => 'Vocabulary — The Axis of Stevil',
            'description' => 'Learn these new words. The Axis of Stevil aims to educate its followers and aid in development of a good vocabulary.',
        ],
        '/featured-steves/' => [
            'description' => 'The Axis of Stevil takes pleasure in shining the spotlight on Steves who shows excellence in their field.',
        ],
        '/ask-stevil/' => [
            'description' => "Ask us a question; we've got an answer. The Axis of Stevil is here to help with any of life's unsolved mysteries.",
        ],
    ],

    // Every work at its address since the old site: /p/{slug}/ (items set by RemoteData).
    'collections' => [
        'works' => [
            'extends' => '_layouts.work',
            'path' => fn ($page) => trim($page->address, '/'),
            'sort' => '-date',
        ],
        // The organisation's own pages at their paths: /mission/, /history/… (items set by RemoteData).
        'organisation' => [
            'extends' => '_layouts.page',
            'path' => fn ($page) => trim($page->address, '/'),
        ],
        // Each division at /{slug}/, each series beneath it at /{division}/{series}/,
        // and All Works at /works/ (items set by RemoteData).
        'divisions' => [
            'extends' => '_layouts.division',
            'path' => fn ($page) => trim($page->address, '/'),
        ],
        // Each weekly issue (front page) at /issues/{slug}/ (items set by RemoteData).
        'issue_pages' => [
            'extends' => '_layouts.issue',
            'path' => fn ($page) => trim($page->address, '/'),
            'sort' => 'number',
        ],
        // The Gallery at /gallery/ and each photographer's listing at /gallery/{photographer}/ (items set by RemoteData).
        'gallery_pages' => [
            'extends' => '_layouts.gallery',
            'path' => fn ($page) => trim($page->address, '/'),
        ],
        // The Tunes at /tunes/ and each artist's page at /tunes/{artist}/ (items set by RemoteData).
        'tune_pages' => [
            'extends' => '_layouts.tunes',
            'path' => fn ($page) => trim($page->address, '/'),
        ],
    ],

    // A page's address, with the trailing slash the site has always used.
    'link' => fn ($page) => rtrim($page->getPath(), '/').'/',
];
