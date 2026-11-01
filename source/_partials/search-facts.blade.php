{{-- What the search index says of a page (Pagefind reads one fact per element): its kind, division, date, picture. --}}
@foreach (array_filter($facts) as $key => $value)
<span hidden data-pagefind-meta="{{ $key }}:{{ $value }}"></span>
@endforeach
