@php
    $b = $cell['block'];
    $kicker = $b['kicker'] ?? match ($b['kind']) {
        'letter' => 'Ask Stevil',
        'language' => 'Language',
        'steve' => 'Featured Steve',
        'notice' => 'From the offices',
        default => $b['refeature'] ? 'Classic Stevil' : ($b['series'] ?? $b['division']),
    };
    // A re-feature says when it first ran.
    if ($b['refeature'] ?? false) {
        $kicker .= ' · from '.\Carbon\CarbonImmutable::parse($b['date'])->format('F j, Y');
    }
@endphp
@if ($cell['type'] === 'whole')
{{-- A whole piece: its pictures or movie at one shared height, never cropped or enlarged. --}}
<figure class="issue-cell issue-whole" style="--grow: {{ $cell['grow'] }}; --natural: {{ $cell['natural'] }}px">
    <div class="issue-whole-picture">{!! $b['html'] !== '' && $b['shows_picture'] ? $b['html'] : '<img src="'.e($b['image']).'" alt="'.e($b['title']).'" loading="lazy">' !!}</div>
    <figcaption><span class="issue-kicker">{{ $kicker }}</span> <a href="{{ $b['address'] }}">{{ $b['title'] }}</a></figcaption>
</figure>
@elseif ($cell['type'] === 'group')
{{-- Pictures and words in turn: its parts in order, side by side at one height, under its headline. --}}
<section class="issue-cell issue-text issue-group" style="--grow: {{ $cell['grow'] }}">
    <p class="issue-kicker">{{ $kicker }}</p>
    <h3 class="issue-box-title"><a href="{{ $b['address'] }}">{{ $b['kicker'] && ! ($b['refeature'] ?? false) ? $b['kicker'] : $b['title'] }}</a></h3>
    <div class="issue-parts">
        @foreach ($cell['parts'] as $part)
        <div class="issue-part issue-part--{{ $part['type'] }} @if ($part['type'] === 'whole') issue-whole-picture @else issue-copy @endif" style="--grow: {{ $part['grow'] }}@if ($part['type'] === 'whole'); --natural: {{ $part['natural'] }}px @endif">{!! $part['html'] !!}</div>
        @endforeach
    </div>
</section>
@elseif ($b['kind'] === 'story')
{{-- A story: its own picture, cropped to take the row's slack; its summary; the jump to the rest. --}}
<section class="issue-cell issue-text issue-story @if ($cell['lead']) issue-story--lead @endif" style="--grow: {{ $cell['grow'] }}">
    <p class="issue-kicker">{{ $kicker }}</p>
    <h2 class="issue-headline"><a href="{{ $b['address'] }}">{{ $b['title'] }}</a></h2>
    @if ($b['image'])
    <a class="issue-crop" href="{{ $b['address'] }}"><img src="{{ $b['image'] }}" alt="" loading="{{ $cell['lead'] ? 'eager' : 'lazy' }}"></a>
    @endif
    <div class="issue-copy @if ($cell['lead']) issue-copy--columns @endif">{!! $b['html'] !!}</div>
    @unless ($b['whole'])
    <a class="issue-jump" href="{{ $b['address'] }}">{{ $jump($b) }} <span aria-hidden="true">→</span></a>
    @endunless
</section>
@elseif ($b['kind'] === 'steve')
<section class="issue-cell issue-text issue-box issue-box--steve" style="--grow: {{ $cell['grow'] }}">
    <p class="issue-kicker">{{ $kicker }}</p>
    <h3 class="issue-box-title"><a href="{{ $b['address'] }}">{{ $b['title'] }}</a></h3>
    @if ($b['image'])
    <a class="issue-crop issue-crop--portrait" href="{{ $b['address'] }}"><img src="{{ $b['image'] }}" alt="" loading="lazy"></a>
    @endif
    <div class="issue-copy">{!! $b['html'] !!}</div>
    <a class="issue-jump" href="{{ $b['address'] }}">{{ $jump($b) }} <span aria-hidden="true">→</span></a>
</section>
@else
{{-- Writing shown whole: a letter to Ask Stevil, the Language entry, an Extra with its words, a notice. --}}
<section class="issue-cell issue-text issue-box issue-box--{{ $b['kind'] }}" style="--grow: {{ $cell['grow'] }}">
    <p class="issue-kicker"><a href="{{ $b['address'] }}">{{ $kicker }}</a></p>
    @unless (in_array($b['kind'], ['letter', 'language'], true))
    <h3 class="issue-box-title"><a href="{{ $b['address'] }}">{{ $b['title'] }}</a></h3>
    @endunless
    <div class="issue-copy">{!! $b['html'] !== '' ? $b['html'] : '<img src="'.e($b['image']).'" alt="'.e($b['title']).'" loading="lazy">' !!}</div>
    @unless ($b['whole'])
    <a class="issue-jump" href="{{ $b['address'] }}">{{ $jump($b) }} <span aria-hidden="true">→</span></a>
    @endunless
</section>
@endif
