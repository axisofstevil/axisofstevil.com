@if ($series->isNotEmpty())
<p class="series-note"><span>Series</span> @foreach ($series as $name => $n){{ $name }} {{ $n }}@if (! $loop->last) · @endif @endforeach</p>
@endif
