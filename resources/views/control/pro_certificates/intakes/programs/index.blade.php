@extends('layouts.control')
@section('title', __('certificate_intake.shared_admin_title'))
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-certificate-intake-1.0.0.css') }}">
<div class="pc-module ci-admin" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificate_intake.admin_eyebrow') }}</span><h1>{{ __('certificate_intake.shared_admin_title') }}</h1><p>{{ __('certificate_intake.shared_admin_lead') }}</p></div><a class="pc-button pc-button-secondary" href="{{ route('certificates.master.index',['locale'=>app()->getLocale()]) }}">{{ __('master_certificates.tab') }}</a></header>
@include('control.pro_certificates._tabs')
@include('control.pro_certificates._messages')
<div class="ci-admin-overview"><div><span>{{ __('certificate_intake.programs_total') }}</span><strong>{{ number_format($programs->total()) }}</strong></div>@if(isset($counts['submitted']))<div><span>{{ __('certificate_intake.response_states.submitted') }}</span><strong>{{ number_format($counts['submitted']) }}</strong></div>@endif @if(isset($counts['reviewed']))<div><span>{{ __('certificate_intake.response_states.reviewed') }}</span><strong>{{ number_format($counts['reviewed']) }}</strong></div>@endif</div>
<section class="pc-card"><header class="pc-card-heading"><div><h2>{{ __('certificate_intake.program_links') }}</h2><p>{{ __('certificate_intake.one_link_hint') }}</p></div></header>
@if($programs->count())<div class="pc-table-scroll"><table class="pc-table ci-table"><thead><tr><th scope="col">{{ __('certificate_intake.program') }}</th><th scope="col">{{ __('certificate_intake.program_code') }}</th><th scope="col">{{ __('certificate_intake.response_collection') }}</th><th scope="col">{{ __('certificate_intake.actions') }}</th></tr></thead><tbody>
@foreach($programs as $program)
@php
    $programTitle = app()->getLocale()==='ar' ? ($program->program_name_ar ?: $program->program_name_en) : (app()->getLocale()==='fr' ? ($program->program_name_fr ?: $program->program_name_en ?: $program->program_name_ar) : ($program->program_name_en ?: $program->program_name_ar));
@endphp
<tr><td><strong><bdi>{{ $programTitle }}</bdi></strong></td><td><bdi dir="ltr">{{ $program->code }}</bdi></td><td><span class="ci-status ci-status-{{ $program->status==='open'?'reviewed':'cancelled' }}">{{ __('certificate_intake.program_states.'.$program->status) }}</span></td><td><a class="pc-text-link" href="{{ route('certificates.intakes.programs.show',['locale'=>app()->getLocale(),'program'=>$program->id]) }}">{{ __('certificate_intake.manage_responses') }}</a></td></tr>
@endforeach</tbody></table></div>@include('control.pro_certificates._pagination',['paginator'=>$programs])
@else<div class="pc-empty"><span class="pc-empty-mark" aria-hidden="true">◇</span><h2>{{ __('certificate_intake.no_programs') }}</h2><p>{{ __('certificate_intake.no_programs_hint') }}</p></div>@endif
</section><p class="ci-admin-note">{{ __('certificate_intake.admin_scope') }}</p>
</div>
@endsection
