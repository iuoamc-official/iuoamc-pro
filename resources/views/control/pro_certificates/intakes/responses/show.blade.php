@extends('layouts.control')
@section('title', __('certificate_intake.review_response'))
@section('content')
@php
    $responseData=$responseRecord->response_payload ?? [];
    $responseName=app()->getLocale()==='ar' ? (($responseData['name_ar'] ?? '') ?: ($responseData['name_en'] ?? '')) : (($responseData['name_en'] ?? '') ?: ($responseData['name_ar'] ?? ''));
    $programTitle=app()->getLocale()==='ar' ? ($program->program_name_ar ?: $program->program_name_en) : (app()->getLocale()==='fr' ? ($program->program_name_fr ?: $program->program_name_en ?: $program->program_name_ar) : ($program->program_name_en ?: $program->program_name_ar));
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-certificate-intake-1.0.0.css') }}">
<div class="pc-module ci-admin" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificate_intake.review_response') }}</span><h1><bdi>{{ $responseName }}</bdi></h1><p><bdi>{{ $programTitle }}</bdi></p></div><a class="pc-button pc-button-secondary" href="{{ route('certificates.intakes.programs.show',['locale'=>app()->getLocale(),'program'=>$program->id]) }}">{{ __('certificate_intake.back_responses') }}</a></header>
@include('control.pro_certificates._tabs')
@include('control.pro_certificates._messages')
<div class="ci-record-status"><span class="ci-status ci-status-{{ $responseRecord->status==='dismissed'?'cancelled':$responseRecord->status }}">{{ __('certificate_intake.response_states.'.$responseRecord->status) }}</span><span>{{ __('certificate_intake.submitted_at') }} <bdi dir="ltr">{{ $responseRecord->created_at?->format('Y-m-d H:i') }} UTC</bdi></span></div>
<div class="ci-admin-grid"><section class="pc-card" aria-labelledby="ci-response-title"><header class="pc-card-heading"><div><h2 id="ci-response-title">{{ __('certificate_intake.response_title') }}</h2><p>{{ __('certificate_intake.shared_response_hint') }}</p></div></header><dl class="pc-facts ci-response-facts">
@foreach(['name_ar','name_en','email','phone','country','specialization','registration_number'] as $field)<div><dt>{{ __('certificate_intake.fields.'.$field) }}</dt><dd><bdi @if(in_array($field,['name_en','email','phone','registration_number'],true)) dir="ltr" @endif>{{ ($responseData[$field] ?? '') ?: '—' }}</bdi></dd></div>@endforeach
@if($responseRecord->reviewed_at)<div><dt>{{ __('certificate_intake.reviewed_at') }}</dt><dd><bdi dir="ltr">{{ $responseRecord->reviewed_at->format('Y-m-d H:i') }} UTC</bdi></dd></div>@endif
</dl>@if(!empty($responseData['notes']))<div class="ci-response-notes"><h3>{{ __('certificate_intake.fields.notes') }}</h3><p>{{ $responseData['notes'] }}</p></div>@endif</section>
<aside class="ci-admin-aside"><section class="pc-card" aria-labelledby="ci-match-title"><header class="pc-card-heading"><div><h2 id="ci-match-title">{{ __('certificate_intake.match_title') }}</h2><p>{{ __('certificate_intake.match_hint') }}</p></div></header>
@if($responseRecord->status==='submitted' && auth()->user()->canDo('certificates.review'))
<form method="post" action="{{ route('certificates.intakes.responses.review',['locale'=>app()->getLocale(),'response'=>$responseRecord->id]) }}" class="ci-match-form">@csrf<label class="ci-field" for="ci-matched-intake"><span>{{ __('certificate_intake.match_candidate') }}</span><select id="ci-matched-intake" name="matched_intake_id" required aria-describedby="ci-match-help"><option value="">{{ __('certificate_intake.choose_candidate') }}</option>
@foreach($candidates as $candidate)
@php
    $candidateSource=$candidate->source_snapshot ?? [];
    $candidateName=app()->getLocale()==='ar' ? (($candidateSource['name_ar'] ?? '') ?: ($candidateSource['name_en'] ?? '')) : (($candidateSource['name_en'] ?? '') ?: ($candidateSource['name_ar'] ?? ''));
@endphp
<option value="{{ $candidate->id }}">{{ $candidateName ?: __('certificate_intake.name_on_record') }} — {{ $candidate->source_certificate_number ?: $candidate->source_id }}</option>
@endforeach</select></label><p class="ci-field-hint" id="ci-match-help">{{ __('certificate_intake.match_selection_hint') }}</p><button class="pc-button pc-button-primary" type="submit">{{ __('certificate_intake.confirm_match') }}</button></form>
@elseif($responseRecord->status==='reviewed')
@php
    $matchedCandidate = $candidates->firstWhere('id', $responseRecord->matched_intake_id);
@endphp
@if($matchedCandidate)
@php
    $matchedSource=$matchedCandidate->source_snapshot ?? [];
    $matchedName=app()->getLocale()==='ar' ? (($matchedSource['name_ar'] ?? '') ?: ($matchedSource['name_en'] ?? '')) : (($matchedSource['name_en'] ?? '') ?: ($matchedSource['name_ar'] ?? ''));
@endphp
<dl class="pc-facts"><div><dt>{{ __('certificate_intake.matched_record') }}</dt><dd><bdi>{{ $matchedName }}</bdi><small><bdi dir="ltr">{{ $matchedCandidate->source_certificate_number ?: $matchedCandidate->source_id }}</bdi></small></dd></div></dl>
@else<p class="ci-inline-note">{{ __('certificate_intake.match_recorded') }}</p>@endif
@else<p class="ci-inline-note">{{ $responseRecord->status==='dismissed'?__('certificate_intake.dismissed_hint'):__('certificate_intake.review_permission_hint') }}</p>@endif
</section>
@if($responseRecord->status==='submitted' && auth()->user()->canDo('certificates.review'))<section class="pc-card ci-cancel-card"><h2>{{ __('certificate_intake.dismiss_title') }}</h2><p>{{ __('certificate_intake.dismiss_hint') }}</p><form method="post" action="{{ route('certificates.intakes.responses.dismiss',['locale'=>app()->getLocale(),'response'=>$responseRecord->id]) }}">@csrf<button class="ci-button ci-button-cancel" type="submit">{{ __('certificate_intake.dismiss') }}</button></form></section>@endif
<section class="ci-admin-guidance"><h2>{{ __('certificate_intake.review_boundaries') }}</h2><p>{{ __('certificate_intake.admin_scope') }}</p></section></aside></div>
</div>
@endsection
