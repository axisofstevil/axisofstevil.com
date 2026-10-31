{{-- The player: the Tunes of the Week, one at a time, in a bar along the foot of the window once a tune is
     chosen (site.js). Every page carries the playlist, so previous and next work anywhere. --}}
@if ($page->tunes && count($page->tunes->toArray()) > 0)
<script type="application/json" id="playlist">{!! json_encode(array_map(fn ($t) => (array) (is_array($t) ? $t : $t->toArray()), $page->tunes->toArray()), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<aside id="player" class="player" data-player data-turbo-permanent hidden aria-label="Now playing">
    <img class="player-picture" alt="" width="48" height="48">
    <span class="player-picture player-picture--blank artist-blank" hidden></span>
    <div class="player-label">
        <p class="player-track"></p>
        <p class="player-artist" hidden><a href="/tunes/"></a></p>
    </div>
    <div class="player-controls">
        <button type="button" class="player-step" data-step="-1" aria-label="Previous tune">⇤</button>
        <button type="button" class="player-toggle" aria-label="Play"><svg viewBox="0 0 12 12" aria-hidden="true"><path class="play-icon" d="M3 2l7 4-7 4z"/><path class="pause-icon" d="M3 2h2.5v8H3zM6.5 2H9v8H6.5z"/></svg></button>
        <button type="button" class="player-step" data-step="1" aria-label="Next tune">⇥</button>
        <label class="player-auto" title="Go on to the next tune when one ends"><input type="checkbox" class="player-autoplay"> <span>Auto</span></label>
    </div>
    <div class="player-time">
        <span class="player-at">0:00</span>
        <input class="player-scrub" type="range" min="0" max="1000" value="0" aria-label="Position">
        <span class="player-length">0:00</span>
    </div>
    <button type="button" class="player-close" aria-label="Close the player">×</button>
</aside>
@endif
