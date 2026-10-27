{{-- A stand-in for the Axis mark until it is redrawn as a vector from the original. --}}
<svg class="mark {{ $class ?? '' }}" viewBox="0 0 100 100" role="img" aria-label="The Axis of Stevil">
    <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="9"/>
    <path d="M50 16 L84 76 L16 76 Z" fill="var(--mark-fill, #111)"/>
</svg>
