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
    ],

    // A page's address, with the trailing slash the site has always used.
    'link' => fn ($page) => rtrim($page->getPath(), '/').'/',
];
