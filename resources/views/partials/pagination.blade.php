@if ($paginator->hasPages())
<nav class="pagination">
  @if (! $paginator->onFirstPage())<a href="{{ $paginator->previousPageUrl() }}">‹ Précédent</a>@endif
  <span class="current">Page {{ $paginator->currentPage() }}@if(method_exists($paginator, 'lastPage')) / {{ $paginator->lastPage() }}@endif</span>
  @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Suivant ›</a>@endif
</nav>
@endif
