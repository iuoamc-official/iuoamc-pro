@extends('layouts.control')
@section('title', __('certificate_intake.manage_responses'))
@section('content')
@php
    $programTitle = app()->getLocale()==='ar' ? ($program->program_name_ar ?: $program->program_name_en) : (app()->getLocale()==='fr' ? ($program->program_name_fr ?: $program->program_name_en ?: $program->program_name_ar) : ($program->program_name_en ?: $program->program_name_ar));
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-certificate-intake-1.0.0.css') }}">
<div class="pc-module ci-admin" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificate_intake.shared_admin_title') }}</span><h1><bdi>{{ $programTitle }}</bdi></h1><p><bdi dir="ltr">{{ $program->code }}</bdi></p></div><a class="pc-button pc-button-secondary" href="{{ route('certificates.intakes.programs.index',['locale'=>app()->getLocale()]) }}">{{ __('certificate_intake.back_programs') }}</a></header>
@include('control.pro_certificates._tabs')
@include('control.pro_certificates._messages')
<section class="pc-card ci-shared-link-card" aria-labelledby="ci-shared-link-title"><div><span class="ci-status ci-status-{{ $program->status==='open'?'reviewed':'cancelled' }}">{{ __('certificate_intake.program_states.'.$program->status) }}</span><h2 id="ci-shared-link-title">{{ __('certificate_intake.shared_link') }}</h2><p>{{ __('certificate_intake.shared_link_help') }}</p><label class="ci-field" for="ci-program-url"><span>{{ __('certificate_intake.program_url') }}</span><input id="ci-program-url" type="url" readonly value="{{ $sharedUrl }}" dir="ltr" autocomplete="off" aria-describedby="ci-program-copy-hint"></label><p class="ci-field-hint" id="ci-program-copy-hint">{{ __('certificate_intake.shared_copy_hint') }}</p></div><div class="ci-shared-link-actions"><a class="pc-button pc-button-primary" href="{{ $sharedUrl }}" target="_blank" rel="noopener noreferrer">{{ __('certificate_intake.open_shared_form') }}</a>
@if(auth()->user()->canDo('certificates.manage'))<form method="post" action="{{ route('certificates.intakes.programs.toggle',['locale'=>app()->getLocale(),'program'=>$program->id]) }}">@csrf<input type="hidden" name="status" value="{{ $program->status==='open'?'closed':'open' }}"><button class="pc-button pc-button-secondary" type="submit">{{ $program->status==='open'?__('certificate_intake.close_collection'):__('certificate_intake.open_collection') }}</button></form>@endif<p>{{ __('certificate_intake.toggle_hint') }}</p></div></section>
<section class="pc-card" aria-labelledby="ci-responses-title"><header class="pc-card-heading"><div><h2 id="ci-responses-title">{{ __('certificate_intake.responses_title') }} <span class="ci-count">{{ number_format($responses->total()) }}</span></h2><p>{{ __('certificate_intake.responses_hint') }}</p></div></header>
@if($responses->count())<div class="pc-table-scroll"><table class="pc-table ci-table"><thead><tr><th scope="col">{{ __('certificate_intake.submitted_name') }}</th><th scope="col">{{ __('certificate_intake.fields.specialization') }}</th><th scope="col">{{ __('certificate_intake.submitted_at') }}</th><th scope="col">{{ __('certificate_intake.status') }}</th><th scope="col">{{ __('certificate_intake.actions') }}</th></tr></thead><tbody>
@foreach($responses as $responseRecord)
@php
    $responseData=$responseRecord->response_payload ?? [];
    $responseName=app()->getLocale()==='ar' ? (($responseData['name_ar'] ?? '') ?: ($responseData['name_en'] ?? '')) : (($responseData['name_en'] ?? '') ?: ($responseData['name_ar'] ?? ''));
@endphp
<tr><td><strong><bdi>{{ $responseName }}</bdi></strong><small><bdi dir="ltr">{{ $responseData['registration_number'] ?? '' }}</bdi></small></td><td><bdi>{{ $responseData['specialization'] ?? '—' }}</bdi></td><td><bdi dir="ltr">{{ $responseRecord->created_at?->format('Y-m-d H:i') }} UTC</bdi></td><td><span class="ci-status ci-status-{{ $responseRecord->status==='dismissed'?'cancelled':$responseRecord->status }}">{{ __('certificate_intake.response_states.'.$responseRecord->status) }}</span></td><td><a class="pc-text-link" href="{{ route('certificates.intakes.responses.show',['locale'=>app()->getLocale(),'response'=>$responseRecord->id]) }}">{{ __('certificate_intake.review_response') }}<span class="ci-sr-only"> — {{ $responseName }}</span></a></td></tr>
@endforeach</tbody></table></div>@include('control.pro_certificates._pagination',['paginator'=>$responses])
@else<div class="pc-empty"><span class="pc-empty-mark" aria-hidden="true">◇</span><h3>{{ __('certificate_intake.no_responses') }}</h3><p>{{ __('certificate_intake.no_responses_hint') }}</p></div>@endif
</section>
<section class="pc-card" aria-labelledby="ci-original-roster-title"><header class="pc-card-heading"><div><h2 id="ci-original-roster-title">{{ __('certificate_intake.private_roster') }} <span class="ci-count">{{ number_format($intakes->count()) }}</span></h2><p>{{ __('certificate_intake.private_roster_hint') }}</p></div></header><div class="pc-table-scroll"><table class="pc-table ci-table"><thead><tr><th scope="col">{{ __('certificate_intake.source_recipient') }}</th><th scope="col">{{ __('certificate_intake.source_id') }}</th><th scope="col">{{ __('certificate_intake.source_number') }}</th></tr></thead><tbody>
@forelse($intakes as $intake)
@php
    $source=$intake->source_snapshot ?? [];
    $sourceName=app()->getLocale()==='ar' ? (($source['name_ar'] ?? '') ?: ($source['name_en'] ?? '')) : (($source['name_en'] ?? '') ?: ($source['name_ar'] ?? ''));
@endphp
<tr><td><strong><bdi>{{ $sourceName ?: __('certificate_intake.name_on_record') }}</bdi></strong></td><td><bdi dir="ltr">{{ $intake->source_id }}</bdi></td><td><bdi dir="ltr">{{ $intake->source_certificate_number ?: '—' }}</bdi></td></tr>
@empty<tr><td colspan="3">{{ __('certificate_intake.no_source_roster') }}</td></tr>@endforelse
</tbody></table></div></section><p class="ci-admin-note">{{ __('certificate_intake.admin_scope') }}</p>
</div>
@endsection
