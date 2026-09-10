@if($paginator->hasPages())
<nav class="pc-pagination" aria-label="{{ __('certificates.pagination_label') }}">
    @if($paginator->onFirstPage())<span aria-disabled="true">{{ __('certificates.previous') }}</span>@else<a class="pc-button pc-button-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('certificates.previous') }}</a>@endif
    <span>{{ __('certificates.page') }} {{ $paginator->currentPage() }} {{ __('certificates.of') }} {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())<a class="pc-button pc-button-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('certificates.next') }}</a>@else<span aria-disabled="true">{{ __('certificates.next') }}</span>@endif
</nav>
@endif
