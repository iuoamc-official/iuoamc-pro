@extends('layouts.control')
@section('title', __('certificate_intake.record_title'))
@section('content')
@php
    $snapshot = $intake->source_snapshot ?? [];
    $response = $intake->response_payload ?? [];
    $sourceName = app()->getLocale()==='ar' ? (($snapshot['name_ar'] ?? '') ?: ($snapshot['name_en'] ?? '')) : (($snapshot['name_en'] ?? '') ?: ($snapshot['name_ar'] ?? ''));
    $program = app()->getLocale()==='ar' ? (($snapshot['program_name_ar'] ?? '') ?: ($snapshot['program_name_en'] ?? '')) : (($snapshot['program_name_en'] ?? '') ?: ($snapshot['program_name_ar'] ?? ''));
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-certificate-intake-1.0.0.css') }}">
<div class="pc-module ci-admin" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
    <header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificate_intake.record_title') }}</span><h1><bdi>{{ $sourceName ?: __('certificate_intake.name_on_record') }}</bdi></h1><p><bdi>{{ $program ?: __('certificate_intake.program_on_record') }}</bdi></p></div><a class="pc-button pc-button-secondary" href="{{ route('certificates.intakes.index',['locale'=>app()->getLocale()]) }}">{{ __('certificate_intake.back') }}</a></header>
    @include('control.pro_certificates._tabs')
    @include('control.pro_certificates._messages')
    <div class="ci-record-status"><span class="ci-status ci-status-{{ $intake->status }}">{{ __('certificate_intake.states.'.$intake->status) }}</span><span>{{ __('certificate_intake.source_id') }} <bdi dir="ltr">{{ $intake->source_id }}</bdi></span></div>
    <div class="ci-admin-grid">
        <div class="ci-admin-main">
            @if(in_array($intake->status,['submitted','reviewed'],true))
            <section class="pc-card" aria-labelledby="ci-response-title"><header class="pc-card-heading"><div><h2 id="ci-response-title">{{ __('certificate_intake.response_title') }}</h2><p>{{ __('certificate_intake.response_hint') }}</p></div></header><dl class="pc-facts ci-response-facts">
                @foreach(['name_ar','name_en','email','phone','country','specialization'] as $field)<div><dt>{{ __('certificate_intake.fields.'.$field) }}</dt><dd><bdi @if(in_array($field,['name_en','email','phone'],true)) dir="ltr" @endif>{{ $response[$field] ?? '—' }}</bdi></dd></div>@endforeach
                <div><dt>{{ __('certificate_intake.submitted_at') }}</dt><dd><bdi dir="ltr">{{ $intake->submitted_at?->format('Y-m-d H:i') }} UTC</bdi></dd></div>
                @if($intake->reviewed_at)<div><dt>{{ __('certificate_intake.reviewed_at') }}</dt><dd><bdi dir="ltr">{{ $intake->reviewed_at->format('Y-m-d H:i') }} UTC</bdi></dd></div>@endif
            </dl>@if(!empty($response['notes']))<div class="ci-response-notes"><h3>{{ __('certificate_intake.fields.notes') }}</h3><p>{{ $response['notes'] }}</p></div>@endif
            @if($intake->status==='submitted' && auth()->user()->canDo('certificates.review'))<div class="ci-review-action"><p>{{ __('certificate_intake.review_hint') }}</p><form method="post" action="{{ route('certificates.intakes.review',['locale'=>app()->getLocale(),'intake'=>$intake->id]) }}">@csrf<button class="pc-button pc-button-primary" type="submit">{{ __('certificate_intake.mark_reviewed') }}</button></form></div>@endif
            </section>
            @else
            <section class="pc-card ci-awaiting-card" aria-labelledby="ci-awaiting-title"><span class="ci-step-number" aria-hidden="true">02</span><h2 id="ci-awaiting-title">{{ __('certificate_intake.awaiting_title') }}</h2><p>{{ __('certificate_intake.awaiting_help') }}</p></section>
            @endif
            <section class="pc-card" aria-labelledby="ci-source-title"><header class="pc-card-heading"><div><h2 id="ci-source-title">{{ __('certificate_intake.source_title') }}</h2><p>{{ __('certificate_intake.source_hint') }}</p></div></header><dl class="pc-facts">
                <div><dt>{{ __('certificate_intake.fields.name_ar') }}</dt><dd><bdi>{{ ($snapshot['name_ar'] ?? '') ?: '—' }}</bdi></dd></div>
                <div><dt>{{ __('certificate_intake.fields.name_en') }}</dt><dd><bdi dir="ltr">{{ ($snapshot['name_en'] ?? '') ?: '—' }}</bdi></dd></div>
                <div><dt>{{ __('certificate_intake.program') }}</dt><dd><bdi>{{ $program ?: '—' }}</bdi></dd></div>
                <div><dt>{{ __('certificate_intake.program_code') }}</dt><dd><bdi dir="ltr">{{ ($snapshot['program_code'] ?? '') ?: '—' }}</bdi></dd></div>
                <div><dt>{{ __('certificate_intake.source_number') }}</dt><dd><bdi dir="ltr">{{ $intake->source_certificate_number ?: '—' }}</bdi></dd></div>
                <div><dt>{{ __('certificate_intake.source_id') }}</dt><dd><bdi dir="ltr">{{ $intake->source_id }}</bdi></dd></div>
            </dl></section>
        </div>
        <aside class="ci-admin-aside">
            <section class="pc-card ci-invitation-card" aria-labelledby="ci-invitation-title"><header class="pc-card-heading"><div><h2 id="ci-invitation-title">{{ __('certificate_intake.invitation_title') }}</h2><p>{{ __('certificate_intake.invitation_help') }}</p></div></header>
                @if($invitationUrl && $intake->status==='open')
                <label class="ci-field" for="ci-invitation-url"><span>{{ __('certificate_intake.private_link') }}</span><input id="ci-invitation-url" type="url" readonly dir="ltr" value="{{ $invitationUrl }}" aria-describedby="ci-copy-hint" autocomplete="off"></label><p class="ci-field-hint" id="ci-copy-hint">{{ __('certificate_intake.copy_hint') }}</p><p class="ci-expiry">{{ __('certificate_intake.expires_at') }} <bdi dir="ltr">{{ $intake->expires_at?->format('Y-m-d H:i') }} UTC</bdi></p><a class="pc-text-link" href="{{ $invitationUrl }}" target="_blank" rel="noopener noreferrer">{{ __('certificate_intake.open_form') }}</a>
                @elseif($intake->status==='open')
                <p class="ci-inline-note">{{ __('certificate_intake.invitation_expired') }}</p>
                @elseif($intake->status==='draft')
                <p class="ci-inline-note">{{ __('certificate_intake.invitation_not_created') }}</p>
                @else
                <p class="ci-inline-note">{{ __('certificate_intake.invitation_closed') }}</p>
                @endif
                @if(in_array($intake->status,['draft','open'],true) && auth()->user()->canDo('certificates.manage'))
                <form method="post" action="{{ route('certificates.intakes.invite',['locale'=>app()->getLocale(),'intake'=>$intake->id]) }}" class="ci-invite-action">@csrf<button class="pc-button pc-button-primary" type="submit">{{ $intake->status==='open' ? __('certificate_intake.rotate_link') : __('certificate_intake.create_link') }}</button><p class="ci-field-hint">{{ $intake->status==='open' ? __('certificate_intake.rotate_hint') : __('certificate_intake.create_hint') }}</p></form>
                @endif
            </section>
            <section class="ci-admin-guidance"><h2>{{ __('certificate_intake.staff_steps') }}</h2><ol><li>{{ __('certificate_intake.staff_step_one') }}</li><li>{{ __('certificate_intake.staff_step_two') }}</li><li>{{ __('certificate_intake.staff_step_three') }}</li></ol><p>{{ __('certificate_intake.admin_scope') }}</p></section>
            @if(in_array($intake->status,['draft','open'],true) && auth()->user()->canDo('certificates.manage'))<section class="pc-card ci-cancel-card"><h2>{{ __('certificate_intake.cancel_title') }}</h2><p>{{ __('certificate_intake.cancel_hint') }}</p><form method="post" action="{{ route('certificates.intakes.cancel',['locale'=>app()->getLocale(),'intake'=>$intake->id]) }}">@csrf<button class="ci-button ci-button-cancel" type="submit">{{ __('certificate_intake.cancel') }}</button></form></section>@endif
        </aside>
    </div>
</div>
@endsection
