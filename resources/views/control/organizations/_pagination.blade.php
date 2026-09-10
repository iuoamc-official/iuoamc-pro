@if ($paginator->hasPages())
    <nav class="simple-pagination" aria-label="Pagination">
        <span>{{ __('institutional.page', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        <div>
            @if ($paginator->onFirstPage())
                <span class="is-disabled">{{ __('institutional.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('institutional.previous') }}</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('institutional.next') }}</a>
            @else
                <span class="is-disabled">{{ __('institutional.next') }}</span>
            @endif
        </div>
    </nav>
@endif
