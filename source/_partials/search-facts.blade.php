{{-- What the search index says of a page (Pagefind reads one fact per element): its kind, division, date, picture. A page
     without a picture of its own (an issue, a listing without a banner) gives a blank tile of the sheet, which should not
     draw the eye; Pagefind would otherwise take the first picture anywhere on the page, often one from the pagination
     beside it. --}}
@php $facts['image'] = ($facts['image'] ?? null) ?: '/assets/search/no-picture.svg'; @endphp
@foreach (array_filter($facts) as $key => $value)
<span hidden data-pagefind-meta="{{ $key }}:{{ $value }}"></span>
@endforeach
