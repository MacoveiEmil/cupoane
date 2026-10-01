@if ($p->hasPages())
<nav class="pager" aria-label="Paginare">
    @if ($p->onFirstPage())<span class="pager__off">← Înapoi</span>@else<a href="{{ $p->previousPageUrl() }}" rel="prev">← Înapoi</a>@endif
    <span class="pager__now">Pagina {{ $p->currentPage() }} din {{ $p->lastPage() }}</span>
    @if ($p->hasMorePages())<a href="{{ $p->nextPageUrl() }}" rel="next">Înainte →</a>@else<span class="pager__off">Înainte →</span>@endif
</nav>
@endif
