<footer class="colophon">
    <div class="colophon-mark">
        @include('_partials.mark', ['class' => 'colophon-svg'])
        <p>{{ $page->tagline }}</p>
    </div>
    <nav aria-label="The organisation">
        <a href="/mission/">Mission</a>
        <a href="/history/">History</a>
        <a href="/authenticity/">Authenticity</a>
        <a href="/shirts/">Shirts</a>
        <a href="/store/">Store</a>
        <a href="/contact/">Contact</a>
    </nav>
    <p class="colophon-line">The Axis of Stevil · Est. {{ $page->founded }}</p>
</footer>
