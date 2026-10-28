@if ($note->isNotEmpty())
<p class="series-note"><span>{{ $page->note_label }}</span> @foreach ($note as $name => $n){{ $name }} {{ $n }}@if (! $loop->last) · @endif @endforeach</p>
@endif
