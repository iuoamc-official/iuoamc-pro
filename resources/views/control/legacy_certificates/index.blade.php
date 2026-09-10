@extends('layouts.control')
@section('title', __('legacy_certificates.title'))
@section('content')
@php
    $registryUrl = url('/'.app()->getLocale().'/control/legacy-certificates');
    $currentScope = $filters['scope'] ?? 'active';
    $resetUrl = $registryUrl;
    $display = static fn ($value) => $value === null ? __('legacy_certificates.unavailable_value') : ((string) $value === '' ? __('legacy_certificates.empty_value') : (string) $value);
    $statusLabel = static function ($value) use ($display) {
        $known = __('legacy_certificates.states');
        return is_array($known) && isset($known[(string) $value]) ? $known[(string) $value] : $display($value);
    };
@endphp
<div class="lcr-module">
    <section class="lcr-heading" aria-labelledby="lcr-title">
        <div class="lcr-heading-copy">
            <span class="lcr-eyebrow">{{ __('legacy_certificates.eyebrow') }}</span>
            <h1 id="lcr-title">{{ __('legacy_certificates.title') }}</h1>
            <p>{{ __('legacy_certificates.lead') }}</p>
        </div>
        <div class="lcr-snapshot">
            <span class="lcr-snapshot-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="5" width="16" height="16" rx="3"/><path d="M8 3v4m8-4v4M4 11h16m-10 4h4"/></svg></span>
            <div><span>{{ __('legacy_certificates.snapshot_date') }}</span><strong><bdi dir="ltr">{{ $display($release['captured_at'] ?? null) }}</bdi></strong></div>
        </div>
    </section>

    @if($purgeApplied)
        <div class="lcr-notice"><p>{{ __('legacy_certificates.purge_notice', ['count' => number_format($purgedCount)]) }}</p></div>
    @endif
    @if($currentScope === 'trash' && $purgeApplied)
        <div class="lcr-alert" role="note"><p>{{ __('legacy_certificates.closed_trash_notice') }}</p><a class="lcr-button" href="{{ $registryUrl }}">{{ __('legacy_certificates.back') }}</a></div>
    @endif

    <section class="lcr-kpis" aria-label="{{ __('legacy_certificates.overview') }}">
        @foreach(['total', 'active', 'nonactive', 'ambiguous'] as $key)
            <article class="lcr-kpi lcr-kpi-{{ $key }}">
                <span>{{ __('legacy_certificates.kpis.'.$key) }}</span>
                <strong><bdi dir="ltr">{{ number_format((int) ($stats[$key] ?? 0)) }}</bdi></strong>
                <small>{{ $key === 'ambiguous' ? __('legacy_certificates.ambiguity_groups', ['count' => number_format((int) ($stats['ambiguous_groups'] ?? 0))]) : __('legacy_certificates.kpi_notes.'.$key) }}</small>
            </article>
        @endforeach
    </section>

    <div class="lcr-notice">
        <span class="lcr-notice-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg></span>
        <p>{{ __('legacy_certificates.status_notice') }}</p>
    </div>

    <section class="lcr-panel" aria-labelledby="lcr-records-title">
        <header class="lcr-panel-heading">
            <div><h2 id="lcr-records-title">{{ __('legacy_certificates.records') }}</h2><p>{{ __('legacy_certificates.records_lead') }}</p></div>
            <span class="lcr-readonly">{{ __('legacy_certificates.read_only') }}</span>
        </header>
        @if($errors->any())<div class="lcr-alert" role="alert"><strong>{{ __('legacy_certificates.filters_invalid') }}</strong><p>{{ $errors->first() }}</p></div>@endif
        <form class="lcr-filters" method="get" action="{{ $registryUrl }}" role="search" aria-label="{{ __('legacy_certificates.search_records') }}">
            <input type="hidden" name="scope" value="{{ $currentScope }}">
            <label class="lcr-search" for="lcr-search">
                <span>{{ __('legacy_certificates.search') }}</span>
                <input id="lcr-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('legacy_certificates.search_hint') }}" maxlength="120" autocomplete="off" dir="auto">
            </label>
            <label for="lcr-status">
                <span>{{ __('legacy_certificates.stored_status') }}</span>
                <select id="lcr-status" name="status">
                    <option value="">{{ __('legacy_certificates.all_statuses') }}</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected((string) ($filters['status'] ?? '') === (string) $status)>{{ $statusLabel($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label for="lcr-ambiguity">
                <span>{{ __('legacy_certificates.barcode_matching') }}</span>
                <select id="lcr-ambiguity" name="ambiguity">
                    <option value="all">{{ __('legacy_certificates.all_records') }}</option>
                    <option value="ambiguous" @selected(($filters['ambiguity'] ?? 'all') === 'ambiguous')>{{ __('legacy_certificates.ambiguous_only') }}</option>
                    <option value="unique" @selected(($filters['ambiguity'] ?? 'all') === 'unique')>{{ __('legacy_certificates.nonambiguous_only') }}</option>
                </select>
            </label>
            <div class="lcr-filter-actions"><button class="lcr-button lcr-button-primary" type="submit">{{ __('legacy_certificates.apply') }}</button><a class="lcr-reset" href="{{ $resetUrl }}">{{ __('legacy_certificates.reset') }}</a></div>
        </form>
        <div class="lcr-results-line"><span>{{ __('legacy_certificates.results_count', ['count' => number_format($records->total())]) }}</span><span>{{ __('legacy_certificates.original_numbers_note') }}</span></div>
        @if($records->count())
            <div class="lcr-table-wrap">
                <table class="lcr-table">
                    <caption class="lcr-sr-only">{{ __('legacy_certificates.records') }}</caption>
                    <thead><tr><th scope="col">{{ __('legacy_certificates.holder') }}</th><th scope="col">{{ __('legacy_certificates.certificate') }}</th><th scope="col">{{ __('legacy_certificates.registration_number') }}</th><th scope="col">{{ __('legacy_certificates.barcode') }}</th><th scope="col">{{ __('legacy_certificates.stored_status') }}</th><th scope="col">{{ __('legacy_certificates.matching') }}</th><th scope="col"><span class="lcr-sr-only">{{ __('legacy_certificates.open') }}</span></th></tr></thead>
                    <tbody>
                        @foreach($records as $record)
                            <tr>
                                <td class="lcr-primary-cell" data-label="{{ __('legacy_certificates.holder') }}"><div><strong><bdi>{{ $display($record->holder_name) }}</bdi></strong><small>{{ __('legacy_certificates.source_id_short') }} <bdi class="lcr-code" dir="ltr">{{ $display($record->source_id) }}</bdi></small></div></td>
                                <td data-label="{{ __('legacy_certificates.certificate') }}"><bdi>{{ $display($record->certificate_title) }}</bdi></td>
                                <td data-label="{{ __('legacy_certificates.registration_number') }}"><bdi class="lcr-code" dir="ltr">{{ $display($record->registration_number) }}</bdi></td>
                                <td data-label="{{ __('legacy_certificates.barcode') }}"><bdi class="lcr-code" dir="ltr">{{ $record->barcode_kind === 'null' ? __('legacy_certificates.not_recorded') : ($record->barcode_kind === 'empty' ? __('legacy_certificates.empty_value') : $display($record->barcode)) }}</bdi></td>
                                <td data-label="{{ __('legacy_certificates.stored_status') }}"><div class="lcr-status"><span class="lcr-badge">{{ $statusLabel($record->stored_status) }}</span>@if($record->stored_status !== null && (string) $record->stored_status !== '')<small><bdi dir="ltr">{{ $record->stored_status }}</bdi></small>@endif</div></td>
                                <td data-label="{{ __('legacy_certificates.matching') }}">@if($record->is_ambiguous)<span class="lcr-match lcr-match-warning">{{ __('legacy_certificates.shared_count', ['count' => $record->candidate_count]) }}</span>@else<span class="lcr-match">{{ __('legacy_certificates.no_ambiguity') }}</span>@endif</td>
                                <td class="lcr-action-cell"><a class="lcr-open" href="{{ $registryUrl.'/'.$record->id }}">{{ __('legacy_certificates.open') }}<span class="lcr-sr-only">: {{ $display($record->holder_name) }} — {{ $display($record->source_id) }}</span><span aria-hidden="true">↗</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="lcr-empty"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 4h14v16H5zM8 8h8m-8 4h8m-8 4h4"/></svg></span><h3>{{ __('legacy_certificates.empty_title') }}</h3><p>{{ __('legacy_certificates.empty_text') }}</p><a class="lcr-button" href="{{ $resetUrl }}">{{ __('legacy_certificates.reset') }}</a></div>
        @endif
        @if($records->hasPages())
            <nav class="lcr-pagination" aria-label="{{ __('legacy_certificates.pagination') }}">
                @if($records->onFirstPage())<span class="lcr-page-disabled">{{ __('legacy_certificates.previous') }}</span>@else<a class="lcr-button" rel="prev" href="{{ $records->previousPageUrl() }}">{{ __('legacy_certificates.previous') }}</a>@endif
                <span>{{ __('legacy_certificates.page_of', ['current' => $records->currentPage(), 'last' => $records->lastPage()]) }}</span>
                @if($records->hasMorePages())<a class="lcr-button" rel="next" href="{{ $records->nextPageUrl() }}">{{ __('legacy_certificates.next') }}</a>@else<span class="lcr-page-disabled">{{ __('legacy_certificates.next') }}</span>@endif
            </nav>
        @endif
    </section>
</div>
@endsection
