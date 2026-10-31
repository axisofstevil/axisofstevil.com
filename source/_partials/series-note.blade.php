@if ($note->isNotEmpty())
@php($links = $page->note_links ?? [])
<p class="series-note"><span>{{ $page->note_label }}</span> {!! $note->map(fn ($n, $name) => isset($links[$name]) ? '<a href="'.e($links[$name]).'">'.e($name).' '.$n.'</a>' : e($name).' '.$n)->implode(' · ') !!}</p>
@endif
