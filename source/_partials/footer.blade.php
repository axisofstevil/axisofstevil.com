<footer class="plate colophon" data-snap>
    <a class="colophon-lockup" href="/"><img src="/assets/logo/axis-symbol.svg" alt="The Axis of Stevil" width="61" height="77"></a>
    <p class="colophon-line"><span>Random insanity at its Finest · Est. 2005</span> <span>&copy; 2005–{{ $page->build_time->year }} The Axis of Stevil, all rights reserved, all wrongs reversed. Please don't steal. <a href="/authenticity/">Beware of imitators</a>.</span></p>
    <nav aria-label="The organisation">
        <a href="/mission/">Mission</a>
        <a href="/history/">History</a>
        <a href="/shirts/">Shirts</a>
        <a href="/contact/">Contact</a>
    </nav>
    {{-- Every division by name, and the other sections: the site's map in one line, on every page. --}}
    <nav class="colophon-sections" aria-label="Sections">
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
    </nav>
</footer>
