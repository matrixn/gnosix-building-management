@if($items->hasPages())
    <nav class="campaign-pagination" aria-label="Navigare între pagini">
        @if($items->previousPageUrl())<a class="button ghost" href="{{ $items->previousPageUrl() }}">← Anterioară</a>@endif
        <span>Pagina {{ $items->currentPage() }} din {{ $items->lastPage() }}</span>
        @if($items->nextPageUrl())<a class="button ghost" href="{{ $items->nextPageUrl() }}">Următoarea →</a>@endif
    </nav>
@endif
