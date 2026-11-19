@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pages">
        @if ($paginator->onFirstPage())<span class="btn btn-sm" aria-disabled="true">&larr; Newer</span>@else<a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}">&larr; Newer</a>@endif
        <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())<a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}">Older &rarr;</a>@else<span class="btn btn-sm" aria-disabled="true">Older &rarr;</span>@endif
    </nav>
@endif
