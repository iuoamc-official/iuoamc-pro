@extends('layouts.control')
@section('title', __('certificate_intake.admin_title'))
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-certificate-intake-1.0.0.css') }}">
<div class="pc-module ci-admin" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
    <header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificate_intake.admin_eyebrow') }}</span><h1>{{ __('certificate_intake.admin_title') }}</h1><p>{{ __('certificate_intake.admin_lead') }}</p></div><a class="pc-button pc-button-secondary" href="{{ route('certificates.master.index',['locale'=>app()->getLocale()]) }}">{{ __('master_certificates.tab') }}</a></header>
    @include('control.pro_certificates._tabs')
    @include('control.pro_certificates._messages')
    <div class="ci-admin-overview"><div><span>{{ __('certificate_intake.total') }}</span><strong>{{ number_format($intakes->total()) }}</strong></div>@if(isset($counts['open']))<div><span>{{ __('certificate_intake.states.open') }}</span><strong>{{ number_format($counts['open']) }}</strong></div>@endif @if(isset($counts['submitted']))<div><span>{{ __('certificate_intake.states.submitted') }}</span><strong>{{ number_format($counts['submitted']) }}</strong></div>@endif @if(isset($counts['reviewed']))<div><span>{{ __('certificate_intake.states.reviewed') }}</span><strong>{{ number_format($counts['reviewed']) }}</strong></div>@endif</div>
    <section class="pc-card ci-registry-card" aria-labelledby="ci-roster-title"><header class="pc-card-heading"><div><h2 id="ci-roster-title">{{ __('certificate_intake.roster_title') }}</h2><p>{{ __('certificate_intake.roster_hint') }}</p></div></header>
    @if($intakes->count())
        <div class="pc-table-scroll"><table class="pc-table ci-table"><thead><tr><th scope="col">{{ __('certificate_intake.source_recipient') }}</th><th scope="col">{{ __('certificate_intake.program') }}</th><th scope="col">{{ __('certificate_intake.source_number') }}</th><th scope="col">{{ __('certificate_intake.status') }}</th><th scope="col">{{ __('certificate_intake.actions') }}</th></tr></thead><tbody>
        @foreach($intakes as $intake)
            @php
                $snapshot = $intake->source_snapshot ?? [];
                $sourceName = app()->getLocale()==='ar' ? (($snapshot['name_ar'] ?? '') ?: ($snapshot['name_en'] ?? '')) : (($snapshot['name_en'] ?? '') ?: ($snapshot['name_ar'] ?? ''));
                $program = app()->getLocale()==='ar' ? (($snapshot['program_name_ar'] ?? '') ?: ($snapshot['program_name_en'] ?? '')) : (($snapshot['program_name_en'] ?? '') ?: ($snapshot['program_name_ar'] ?? ''));
            @endphp
            <tr><td><strong><bdi>{{ $sourceName ?: __('certificate_intake.name_on_record') }}</bdi></strong><small>{{ __('certificate_intake.source_id') }} <bdi dir="ltr">{{ $intake->source_id }}</bdi></small></td><td><bdi>{{ $program ?: __('certificate_intake.program_on_record') }}</bdi>@if(!empty($snapshot['program_code']))<small><bdi dir="ltr">{{ $snapshot['program_code'] }}</bdi></small>@endif</td><td><bdi dir="ltr">{{ $intake->source_certificate_number ?: '—' }}</bdi></td><td><span class="ci-status ci-status-{{ $intake->status }}">{{ __('certificate_intake.states.'.$intake->status) }}</span></td><td><a class="pc-text-link" href="{{ route('certificates.intakes.show',['locale'=>app()->getLocale(),'intake'=>$intake->id]) }}">{{ __('certificate_intake.open_record') }}<span class="ci-sr-only"> — {{ $sourceName ?: $intake->source_id }}</span></a></td></tr>
        @endforeach
        </tbody></table></div>
        @include('control.pro_certificates._pagination',['paginator'=>$intakes])
    @else
        <div class="pc-empty"><span class="pc-empty-mark" aria-hidden="true">◇</span><h2>{{ __('certificate_intake.empty_title') }}</h2><p>{{ __('certificate_intake.empty_help') }}</p></div>
    @endif
    </section>
    <p class="ci-admin-note">{{ __('certificate_intake.admin_scope') }}</p>
</div>
@endsection
