<footer class="plate colophon" data-snap>
    <a class="colophon-lockup" href="/"><img crossorigin="anonymous" src="https://archive.axisofstevil.com/brand/axis-symbol.svg" alt="The Axis of Stevil" width="61" height="77"></a>
    <p class="colophon-line"><span>Random insanity at its Finest · Est. 2005</span> <span>&copy; 2005–{{ $page->build_time->year }} The Axis of Stevil, all rights reserved, all wrongs reversed. Please don't steal. <a href="/authenticity/">Beware of imitators</a>.</span></p>
    <nav aria-label="The organisation">
        <span class="colophon-label" aria-hidden="true">The organisation</span>
        <a href="/mission/">Mission</a>
        <a href="/history/">History</a>
        <a href="/shirts/">Shirts</a>
        <a href="/contact/">Contact</a>
    </nav>
    {{-- Every division by name, and the other sections: the site's map in one line, on every page. --}}
    <nav class="colophon-sections" aria-label="Sections">
        <span class="colophon-label" aria-hidden="true">Sections</span>
        @foreach ($page->divisions as $division)
        <a href="/{{ $division->slug }}/">{{ $division->name }}</a>
        @endforeach
        <a href="/works/">All Works</a>
        <a href="/issues/">Issues</a>
        @if ($page->gallery)
        <a href="/gallery/">Gallery</a>
        @endif
        @if ($page->tunes_section)
        <a href="/tunes/">Tunes</a>
        @endif
        @if ($page->show)
        {{-- The show, in its slate: the broadcast arm, at its own address. --}}
        <a class="colophon-show" href="{{ $page->show->url }}" aria-label="Listen to {{ $page->show->name }}"><span class="on-air-play" aria-hidden="true"><svg viewBox="0 0 16 16"><path d="M3 8h9.5M8.5 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>{{ $page->show->name }}</a>
        @endif
    </nav>
</footer>
