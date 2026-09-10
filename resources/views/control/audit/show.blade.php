@extends('layouts.control')

@section('title', __('institutional.audit_details'))

@section('content')
    <section class="page-heading compact-heading">
        <div><span class="eyebrow">AUDIT / #{{ $auditLog->id }}</span><h1>{{ __('institutional.audit_details') }}</h1><p><code>{{ $auditLog->event }}</code></p></div>
        <a class="secondary-action" href="{{ route('audit.index', ['locale' => app()->getLocale()]) }}">{{ __('institutional.back') }}</a>
    </section>

    <section class="audit-summary-grid">
        <article><span>{{ __('institutional.actor') }}</span><strong>{{ $auditLog->actor?->name ?? __('institutional.unknown_actor') }}</strong><small>{{ $auditLog->actor?->email }}</small></article>
        <article><span>{{ __('institutional.occurred_at') }}</span><strong>{{ $auditLog->occurred_at?->format('Y-m-d H:i:s') }}</strong></article>
        <article><span>{{ __('institutional.ip_address') }}</span><strong><bdi dir="ltr">{{ $auditLog->ip_address ?? '—' }}</bdi></strong></article>
        <article><span>{{ __('institutional.subject') }}</span><strong>{{ class_basename((string) $auditLog->auditable_type) }}{{ $auditLog->auditable_id ? ' #'.$auditLog->auditable_id : '' }}</strong></article>
    </section>

    <section class="integrity-card {{ $integrity['valid'] ? 'is-verified' : 'is-failed' }}" role="status">
        <header>
            <span class="integrity-icon" aria-hidden="true">{{ $integrity['valid'] ? '✓' : '!' }}</span>
            <div>
                <span>{{ __('institutional.integrity_status') }}</span>
                <strong>{{ $integrity['valid'] ? __('institutional.integrity_verified') : __('institutional.integrity_failed') }}</strong>
            </div>
            <code>ED25519 · SHA-256</code>
        </header>
        <dl>
            <div><dt>{{ __('institutional.sequence_number') }}</dt><dd><bdi dir="ltr">#{{ $auditLog->sequence_number }}</bdi></dd></div>
            <div><dt>{{ __('institutional.payload_hash') }}</dt><dd><code>{{ $auditLog->payload_hash ?? '—' }}</code></dd></div>
            <div><dt>{{ __('institutional.record_hash') }}</dt><dd><code>{{ $auditLog->record_hash ?? '—' }}</code></dd></div>
            <div><dt>{{ __('institutional.signing_key_id') }}</dt><dd><code>{{ $auditLog->signing_key_id ?? '—' }}</code></dd></div>
        </dl>
    </section>

    <section class="audit-payload-grid">
        @foreach (['old_values', 'new_values', 'metadata'] as $field)
            <article class="payload-card">
                <h2>{{ __('institutional.'.$field) }}</h2>
                <pre dir="ltr">{{ json_encode($auditLog->{$field} ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </article>
        @endforeach
    </section>
@endsection
