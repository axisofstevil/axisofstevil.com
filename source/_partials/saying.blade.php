{{-- One of the 2005 site's sayings at a time (source/_data/sayings.json), in the running head, turning as the Meanwhile ticker does (site.js). --}}
@php($sayings = collect($page->sayings)->map(fn ($s) => (array) (is_array($s) ? $s : $s->toArray())))
@if ($sayings->isNotEmpty())
<span class="saying-lines" data-ticker>@foreach ($sayings as $i => $saying)<span @if ($i === 0)class="is-on"@endif>“{{ $saying['text'] }}”@if (!empty($saying['by'])) <cite>{{ $saying['by'] }}</cite>@endif</span>@endforeach</span>
@endif
