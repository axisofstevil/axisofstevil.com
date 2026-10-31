@if (($page->banner ?? null) || ($page->introduction ?? null))
<div class="series-intro">
    @if ($page->banner ?? null)
    <img class="series-banner" src="{{ $page->banner }}" alt="{{ $page->name }}">
    @endif
    @if ($page->introduction ?? null)
    <div class="series-text">{!! $page->introduction !!}</div>
    @endif
</div>
@endif
