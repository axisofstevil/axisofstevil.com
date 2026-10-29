@php
    // A piece as this week's front page shows it: its treatment, its picture, its summary there.
    $kicker = $b['kicker'] ?? match ($b['kind']) {
        'letter' => 'Ask Stevil',
        'language' => 'Language',
        'steve' => 'Featured Steve',
        'notice' => 'From the offices',
        default => $b['refeature'] ? 'Classic Stevil' : ($b['series'] ?? $b['division']),
    };

    if ($b['refeature'] ?? false) {
        $kicker .= ' · from '.\Carbon\CarbonImmutable::parse($b['date'])->format('F j, Y');
    }

    $headline = ($b['kicker'] ?? null) && ! ($b['refeature'] ?? false) && in_array($b['kind'], ['plate', 'notice'], true) ? $b['kicker'] : $b['title'];
    // A cartoon or a movie is never cropped.
    $crop = $b['picture'] === 'crop' && ! in_array($b['kind'], ['strip', 'plate'], true);
    // What runs whole: a story's whole text; anything else, what the week shows of it.
    $whole = in_array($b['kind'], ['story', 'steve'], true) ? $b['full_html'] : ($b['html'] !== '' ? $b['html'] : $b['full_html']);
    $picture = $b['image'] ? '<img src="'.e($b['image']).'" alt="'.e($b['title']).'" loading="'.($lead ? 'eager' : 'lazy').'">' : null;
@endphp
<article class="issue-piece issue-piece--{{ $b['treatment'] }} issue-piece--{{ $b['kind'] }} @if ($lead) issue-piece--lead @endif @if (in_array($b['picture'], ['left', 'right'], true)) issue-wrap issue-wrap--{{ $b['picture'] }} @endif">
    @if (($b['part'] ?? null) !== null)
        {{-- One part of a piece split across slots: the first carries its kicker and headline. --}}
        @if ($b['part'] === 0)
        <p class="issue-kicker">{{ $kicker }}</p>
        <h2 class="issue-headline"><a href="{{ $b['address'] }}">{{ $headline }}</a></h2>
        @endif
        <div class="issue-copy">{!! $b['part_html'] !!}</div>
    @else
    @switch($b['treatment'])
        @case('picture')
        <figure class="issue-picture-only">
            <a class="@if ($crop) issue-crop @else issue-full-picture @endif" href="{{ $b['address'] }}">{!! $picture ?? (preg_match('/<img[^>]+>/', $whole, $m) ? $m[0] : '') !!}</a>
            <figcaption><span class="issue-kicker">{{ $kicker }}</span> <a href="{{ $b['address'] }}">{{ $headline }}</a></figcaption>
        </figure>
        @break

        @case('headline')
        <p class="issue-kicker">{{ $kicker }}</p>
        <h2 class="issue-headline"><a href="{{ $b['address'] }}">{{ $headline }}</a></h2>
        @break

        @case('parts')
        <p class="issue-kicker">{{ $kicker }}</p>
        <h2 class="issue-headline"><a href="{{ $b['address'] }}">{{ $headline }}</a></h2>
        <div class="issue-parts">
            @foreach ($b['parts'] as $part)
            <div class="issue-part issue-part--{{ $part['type'] }} @if ($part['type'] === 'picture') issue-whole-picture @else issue-copy @endif">{!! $part['html'] !!}</div>
            @endforeach
        </div>
        @break

        @case('summary')
        <p class="issue-kicker">{{ $kicker }}</p>
        <h2 class="issue-headline"><a href="{{ $b['address'] }}">{{ $headline }}</a></h2>
        @if ($picture)
        <a class="@if ($crop) issue-crop @else issue-full-picture @endif" href="{{ $b['address'] }}">{!! $picture !!}</a>
        @endif
        <div class="issue-copy @if ($lead) issue-copy--columns @endif">{!! $b['summary_html'] !== '' ? $b['summary_html'] : $b['html'] !!}</div>
        @if (($b['continued'] ?? null) === 'show' || (($b['continued'] ?? null) !== 'hide' && ($b['more'] ?? true)))
        <a class="issue-jump" href="{{ $b['address'] }}">{{ $jump($b) }} <span aria-hidden="true">→</span></a>
        @endif
        @break

        @default
        <p class="issue-kicker">{{ $kicker }}</p>
        @unless (in_array($b['kind'], ['strip', 'letter', 'language'], true))
        <h2 class="issue-headline"><a href="{{ $b['address'] }}">{{ $headline }}</a></h2>
        @endunless
        <div class="issue-copy">{!! $whole !!}</div>
        @if (in_array($b['kind'], ['strip', 'letter'], true))
        <p class="issue-caption"><a href="{{ $b['address'] }}">{{ $b['title'] }}</a></p>
        @endif
    @endswitch
    @endif
</article>
