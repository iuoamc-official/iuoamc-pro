@extends('layouts.control')
@section('title', __('legacy_certificates.record_details'))
@section('content')
@php
    $registryUrl = url('/'.app()->getLocale().'/control/legacy-certificates');
    $display = static fn ($value) => $value === null ? __('legacy_certificates.unavailable_value') : ((string) $value === '' ? __('legacy_certificates.empty_value') : (string) $value);
    $statusLabel = static function ($value) use ($display) {
        $known = __('legacy_certificates.states');
        return is_array($known) && isset($known[(string) $value]) ? $known[(string) $value] : $display($value);
    };
@endphp
<div class="lcr-module">
    <a class="lcr-back" href="{{ $registryUrl }}"><span aria-hidden="true">←</span>{{ __('legacy_certificates.back') }}</a>
    <section class="lcr-heading" aria-labelledby="lcr-record-title">
        <div class="lcr-heading-copy"><span class="lcr-eyebrow">{{ __('legacy_certificates.record_details') }}</span><h1 id="lcr-record-title"><bdi>{{ $display($record->holder_name) }}</bdi></h1><p><bdi>{{ $display($record->certificate_title) }}</bdi></p></div>
        <span class="lcr-readonly">{{ __('legacy_certificates.read_only') }}</span>
    </section>
    @if(!($integrity['passed'] ?? false))
        <div class="lcr-alert" role="alert"><strong>{{ __('legacy_certificates.integrity_problem') }}</strong><p>{{ __('legacy_certificates.integrity_problem_note') }}</p></div>
    @endif
    <div class="lcr-notice"><span class="lcr-notice-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg></span><p>{{ __('legacy_certificates.status_notice') }}</p></div>

    <div class="lcr-detail-grid">
        <section class="lcr-panel" aria-labelledby="lcr-identity-title">
            <header class="lcr-panel-heading"><div><h2 id="lcr-identity-title">{{ __('legacy_certificates.identity') }}</h2><p>{{ __('legacy_certificates.identity_note') }}</p></div></header>
            <dl class="lcr-facts">
                <div><dt>{{ __('legacy_certificates.holder') }}</dt><dd><bdi>{{ $display($record->holder_name) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.certificate') }}</dt><dd><bdi>{{ $display($record->certificate_title) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.registration_number') }}</dt><dd><bdi class="lcr-code" dir="ltr">{{ $display($record->registration_number) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.source_id') }}</dt><dd><bdi class="lcr-code" dir="ltr">{{ $display($record->source_id) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.barcode') }}</dt><dd><bdi class="lcr-code" dir="ltr">{{ $record->barcode_kind === 'null' ? __('legacy_certificates.not_recorded') : ($record->barcode_kind === 'empty' ? __('legacy_certificates.empty_value') : $display($record->barcode)) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.country') }}</dt><dd><bdi>{{ $display($record->source_country) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.certificate_type') }}</dt><dd><bdi>{{ $display($record->legacy_type) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.design') }}</dt><dd><bdi>{{ $display($record->legacy_design) }}</bdi></dd></div>
            </dl>
        </section>
        <section class="lcr-panel" aria-labelledby="lcr-status-title">
            <header class="lcr-panel-heading"><div><h2 id="lcr-status-title">{{ __('legacy_certificates.preserved_status') }}</h2><p>{{ __('legacy_certificates.as_recorded') }}</p></div></header>
            <div class="lcr-status-feature"><span class="lcr-badge">{{ $statusLabel($record->stored_status) }}</span>@if($record->stored_status !== null && (string) $record->stored_status !== '')<bdi class="lcr-code" dir="ltr">{{ $record->stored_status }}</bdi>@endif</div>
            <dl class="lcr-facts">
                <div><dt>{{ __('legacy_certificates.valid_from') }}</dt><dd><bdi dir="ltr">{{ $display($record->valid_from) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.valid_until') }}</dt><dd><bdi dir="ltr">{{ $display($record->valid_until) }}</bdi></dd></div>
                <div><dt>{{ __('legacy_certificates.snapshot_date') }}</dt><dd><bdi dir="ltr">{{ $display($release['captured_at'] ?? null) }}</bdi></dd></div>
            </dl>
            <p class="lcr-panel-note">{{ __('legacy_certificates.dates_note') }}</p>
        </section>
    </div>

    <section class="lcr-panel lcr-candidates-panel" aria-labelledby="lcr-matching-title">
        <header class="lcr-panel-heading"><div><h2 id="lcr-matching-title">{{ __('legacy_certificates.barcode_matching') }}</h2><p>{{ $record->is_ambiguous ? __('legacy_certificates.duplicate_note') : __('legacy_certificates.no_ambiguity_note') }}</p></div>@if($record->is_ambiguous)<span class="lcr-match lcr-match-warning">{{ __('legacy_certificates.shared_count', ['count' => $record->candidate_count]) }}</span>@endif</header>
        @if($record->is_ambiguous)
            <ul class="lcr-candidates">
                @foreach($candidates as $candidate)
                    <li>
                        <div><strong><bdi>{{ $display($candidate->holder_name) }}</bdi></strong><p><bdi>{{ $display($candidate->certificate_title) }}</bdi></p><small>{{ __('legacy_certificates.source_id_short') }} <bdi class="lcr-code" dir="ltr">{{ $display($candidate->source_id) }}</bdi> <span aria-hidden="true">·</span> {{ __('legacy_certificates.registration_number') }} <bdi class="lcr-code" dir="ltr">{{ $display($candidate->registration_number) }}</bdi></small></div>
                        <div class="lcr-candidate-actions"><span class="lcr-badge">{{ $statusLabel($candidate->stored_status) }}</span>@if((string) $candidate->id === (string) $record->id)<span class="lcr-current">{{ __('legacy_certificates.current_record') }}</span>@else<a class="lcr-open" href="{{ $registryUrl.'/'.$candidate->id }}">{{ __('legacy_certificates.open') }}<span class="lcr-sr-only">: {{ $display($candidate->source_id) }}</span><span aria-hidden="true">↗</span></a>@endif</div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="lcr-panel-note">{{ __('legacy_certificates.no_ambiguity_validity_note') }}</p>
        @endif
    </section>

    <details class="lcr-evidence">
        <summary><span>{{ __('legacy_certificates.evidence') }}</span><span class="lcr-evidence-state {{ ($integrity['passed'] ?? false) ? 'lcr-evidence-ok' : 'lcr-evidence-review' }}">{{ __(($integrity['passed'] ?? false) ? 'legacy_certificates.integrity_passed' : 'legacy_certificates.integrity_problem') }}</span></summary>
        <div class="lcr-evidence-body"><p>{{ __('legacy_certificates.evidence_note') }}</p><dl class="lcr-facts">
            <div><dt>{{ __('legacy_certificates.snapshot_reference') }}</dt><dd><bdi class="lcr-code" dir="ltr">{{ $display($record->snapshot_id) }}</bdi></dd></div>
            <div><dt>{{ __('legacy_certificates.imported_at') }}</dt><dd><bdi dir="ltr">{{ $display($release['imported_at'] ?? null) }}</bdi></dd></div>
            <div><dt>{{ __('legacy_certificates.source_fingerprint') }}</dt><dd><code class="lcr-hash" dir="ltr">{{ $display($integrity['source_hash'] ?? $record->source_row_sha256) }}</code></dd></div>
            <div><dt>{{ __('legacy_certificates.register_fingerprint') }}</dt><dd><code class="lcr-hash" dir="ltr">{{ $display($integrity['projection_hash'] ?? null) }}</code></dd></div>
        </dl></div>
    </details>
</div>
@endsection
