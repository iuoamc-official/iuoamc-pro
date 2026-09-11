@extends('layouts.control')
@section('title', $integrity ? $certificate->certificate_title : __('certificates.title'))
@section('content')
@php($programIpCode=(int)$certificate->schema_version>=2?\App\Services\ProMasterCertificatePdf::programIpCodeFromStatement($certificate->statement):null)
<div class="pc-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
    <header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificates.eyebrow') }}</span><h1>{{ $integrity ? $certificate->certificate_title : __('certificates.title') }}</h1>@if($integrity)<p><bdi dir="ltr" class="pc-number">{{ $certificate->certificate_number ?: $certificate->record_uuid }}</bdi></p>@endif</div><a class="pc-button pc-button-secondary" href="{{ route('certificates.index',['locale'=>app()->getLocale()]) }}">{{ __('certificates.back') }}</a></header>
    @include('control.pro_certificates._tabs')
    @include('control.pro_certificates._messages')
    @if(!$integrity)
        <div class="pc-notice pc-notice-error" role="alert"><strong>{{ __('certificates.integrity_failed') }}</strong><p>{{ __('certificates.errors.integrity') }}</p></div>
    @else
    <div class="pc-record-status"><span class="pc-badge pc-state-{{ $status }}">{{ __('certificates.states.'.$status) }}</span><span class="pc-integrity">{{ __('certificates.verified') }}</span><span>{{ __('certificates.revision') }} {{ $certificate->lock_version }}</span></div>
    <div class="pc-detail-grid">
        <section class="pc-card"><header class="pc-card-heading"><div><h2>{{ __('certificates.identity') }}</h2><p><bdi>{{ $certificate->organization?->display_name }}</bdi></p></div></header>
            <dl class="pc-facts">
                <div><dt>{{ __('certificates.recipient_name') }}</dt><dd><bdi>{{ $certificate->recipient_name }}</bdi></dd></div>
                <div><dt>{{ __('certificates.public_name') }}</dt><dd><bdi>{{ $certificate->public_name }}</bdi></dd></div>
                @if($certificate->recipient_email)<div><dt>{{ __('certificates.recipient_email') }}</dt><dd><bdi dir="ltr">{{ $certificate->recipient_email }}</bdi></dd></div>@endif
                <div><dt>{{ __('certificates.program_title') }}</dt><dd><bdi>{{ $certificate->program_title }}</bdi></dd></div>
                <div><dt>{{ __('certificates.certificate_type') }}</dt><dd>{{ __('certificates.types.'.$certificate->certificate_type) }}</dd></div>
                @if($certificate->credential_basis)<div><dt>{{ __('certificates.credential_basis') }}</dt><dd>{{ __('certificates.credential_bases.'.$certificate->credential_basis) }}</dd></div>@endif
                @if($certificate->accreditation_reference)<div><dt>{{ __('certificates.accreditation_reference') }}</dt><dd><bdi dir="ltr">{{ $certificate->accreditation_reference }}</bdi><small><bdi dir="ltr">{{ $certificate->accreditation_date?->format('Y-m-d') }}</bdi></small></dd></div>@endif
                @if((int)$certificate->schema_version>=2)<div><dt>{{ __('certificate_catalog.type_code') }}</dt><dd><bdi dir="ltr">{{ $certificate->catalog_snapshot['code']??'—' }}</bdi></dd></div><div><dt>{{ __('certificate_catalog.specialization') }}</dt><dd><bdi>{{ ($certificate->specialization!==null && $certificate->specialization!=='') ? $certificate->specialization : '—' }}</bdi></dd></div>@if($programIpCode)<div><dt>{{ __('certificate_catalog.program_ip_code') }}</dt><dd><bdi dir="ltr" class="pc-number">{{ $programIpCode }}</bdi></dd></div>@endif @endif
                <div><dt>{{ __('certificates.language') }}</dt><dd>{{ ['ar'=>'العربية','en'=>'English','fr'=>'Français'][$certificate->language] ?? $certificate->language }}</dd></div>
                <div><dt>{{ __('certificates.achievement_date') }}</dt><dd><bdi dir="ltr">{{ $certificate->achievement_date?->format('Y-m-d') }}</bdi></dd></div>
                <div><dt>{{ __('certificates.expires_on') }}</dt><dd><bdi dir="ltr">{{ $certificate->expires_on?->format('Y-m-d') ?: __('certificates.no_expiry') }}</bdi></dd></div>
                <div><dt>{{ __('certificates.signatory_name') }}</dt><dd><bdi>{{ $certificate->signatory_name }}</bdi><small><bdi>{{ $certificate->signatory_title }}</bdi></small></dd></div>
            </dl>
            <div class="pc-statement"><h3>{{ __('certificates.statement') }}</h3><p>{{ $certificate->statement }}</p></div>
            <div class="pc-button-row">
                @if($certificate->status==='draft' && auth()->user()->canDo('certificates.manage'))<a class="pc-button pc-button-secondary" href="{{ route('certificates.edit',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}">{{ __('certificates.edit') }}</a>@endif
                @if(in_array($certificate->status,['draft','review','approved'],true) && auth()->user()->canDo('certificates.manage'))<a class="pc-button pc-button-secondary" href="{{ route('certificates.preview',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}" target="_blank" rel="noopener noreferrer">{{ __('certificates.preview') }}</a>@endif
                @if(in_array($certificate->status,['issued','revoked'],true))<a class="pc-button pc-button-primary" href="{{ route('certificates.download',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}">{{ __('certificates.download') }}</a><a class="pc-button pc-button-secondary" href="{{ route('certificates.image',['locale'=>app()->getLocale(),'certificate'=>$certificate->id,'variant'=>'print']) }}">{{ __('certificates.download_png') }}</a><a class="pc-button pc-button-secondary" href="{{ route('certificates.image',['locale'=>app()->getLocale(),'certificate'=>$certificate->id,'variant'=>'share']) }}">{{ __('certificates.download_share') }}</a><a class="pc-button pc-button-secondary" href="{{ route('pro-certificates.verify',['token'=>$certificate->public_token,'lang'=>app()->getLocale()]) }}" target="_blank" rel="noopener noreferrer">{{ __('certificates.verify_link') }}</a>@endif
            </div>
            <p class="pc-help">{{ __(in_array($certificate->status,['issued','revoked'],true)?'certificates.private_copy_notice':'certificates.preview_notice') }}</p>
        </section>
        <aside class="pc-card pc-workflow"><header class="pc-card-heading"><div><h2>{{ __('certificates.workflow') }}</h2><p>{{ __('certificates.workflow_notice') }}</p></div></header>
            <ol class="pc-workflow-steps">@foreach(['draft','review','approved','issued'] as $step)<li @class(['pc-workflow-current'=>$certificate->status===$step])>{{ __('certificates.states.'.$step) }}</li>@endforeach</ol>
            <p class="pc-help">{{ __('certificates.workflow_'.$certificate->status) }}</p>
            @if($readiness)
            <section @class(['pc-readiness','pc-readiness-ready'=>$readiness['ready']]) aria-labelledby="pc-readiness-title">
                <header><div><span class="pc-eyebrow">{{ __('certificates.readiness.eyebrow') }}</span><h3 id="pc-readiness-title">{{ __('certificates.readiness.'.($readiness['ready']?'ready':'blocked')) }}</h3></div><span class="pc-readiness-score" aria-label="{{ __('certificates.readiness.score') }}">{{ collect($readiness['checks'])->filter(fn($check)=>$check===true)->count() }}/{{ collect($readiness['checks'])->reject(fn($check)=>$check===null)->count() }}</span></header>
                <p>{{ __('certificates.readiness.'.($readiness['ready']?'ready_help':'blocked_help')) }}</p>
                <ul>
                    @foreach($readiness['checks'] as $check=>$passed)
                    <li @class(['pc-check-passed'=>$passed===true,'pc-check-failed'=>$passed===false,'pc-check-na'=>$passed===null])><span aria-hidden="true">{{ $passed===true?'✓':($passed===false?'!':'—') }}</span><div><strong>{{ __('certificates.readiness.checks.'.$check) }}</strong><small>{{ __('certificates.readiness.states.'.($passed===null?'na':($passed?'passed':'failed'))) }}</small></div></li>
                    @endforeach
                </ul>
            </section>
            @endif
            @if($certificate->status==='draft' && auth()->user()->canDo('certificates.manage'))@include('control.pro_certificates._action',['action'=>'submit'])@endif
            @if($certificate->status==='review' && auth()->user()->canDo('certificates.review'))@include('control.pro_certificates._action',['action'=>'approve'])@include('control.pro_certificates._action',['action'=>'return'])@endif
            @if($certificate->status==='approved' && auth()->user()->canDo('certificates.issue') && ($readiness['ready']??false))@include('control.pro_certificates._action',['action'=>'issue'])@endif
            @if($certificate->status==='approved' && auth()->user()->canDo('certificates.review'))@include('control.pro_certificates._action',['action'=>'return'])@endif
            @if($certificate->status==='issued' && auth()->user()->canDo('certificates.revoke'))@include('control.pro_certificates._action',['action'=>'revoke'])@endif
            @if($certificate->status==='revoked' && $certificate->last_reason)<div class="pc-notice pc-notice-warning"><strong>{{ __('certificates.reason') }}</strong><p>{{ $certificate->last_reason }}</p></div>@endif
            <dl class="pc-facts pc-audit-facts">
                <div><dt>{{ __('certificates.creator') }}</dt><dd>{{ $certificate->creator?->name }}<small><bdi dir="ltr">{{ $certificate->created_at?->format('Y-m-d H:i') }} UTC</bdi></small></dd></div>
                @if($certificate->approved_at)<div><dt>{{ __('certificates.approver') }}</dt><dd>{{ $certificate->approver?->name }}<small><bdi dir="ltr">{{ $certificate->approved_at->format('Y-m-d H:i') }} UTC</bdi></small></dd></div>@endif
                @if($certificate->issued_at)<div><dt>{{ __('certificates.issuer') }}</dt><dd>{{ $certificate->issuer?->name }}<small><bdi dir="ltr">{{ $certificate->issued_at->format('Y-m-d H:i') }} UTC</bdi></small></dd></div>@endif
                @if($certificate->revoked_at)<div><dt>{{ __('certificates.revoked_at') }}</dt><dd><bdi dir="ltr">{{ $certificate->revoked_at->format('Y-m-d H:i') }} UTC</bdi></dd></div>@endif
            </dl>
        </aside>
    </div>
    @if($certificate->pdf_sha256)
    <details class="pc-card pc-evidence"><summary>{{ __('certificates.evidence') }}</summary><p class="pc-help">{{ __('certificates.evidence_notice') }}</p><dl class="pc-facts"><div><dt>{{ __('certificates.pdf_hash') }}</dt><dd><code dir="ltr">{{ $certificate->pdf_sha256 }}</code></dd></div>@if($certificate->pdf_signature_status)<div><dt>{{ __('certificates.pades_status') }}</dt><dd><bdi dir="ltr">{{ $certificate->pdf_signature_profile }} / {{ $certificate->pdf_signature_status }}</bdi><small><bdi dir="ltr">{{ $certificate->pdf_signed_at?->format('Y-m-d H:i:s') }} UTC</bdi></small></dd></div><div><dt>{{ __('certificates.pades_certificate_fingerprint') }}</dt><dd><code dir="ltr">{{ $certificate->pdf_signing_certificate_sha256 }}</code></dd></div>@endif<div><dt>{{ __('certificates.payload_hash') }}</dt><dd><code dir="ltr">{{ $certificate->payload_sha256 }}</code></dd></div><div><dt>{{ __('certificates.signature_key') }}</dt><dd><code dir="ltr">{{ $certificate->signing_key_id }}</code></dd></div></dl></details>
    @endif
    <section class="pc-card"><header class="pc-card-heading"><div><h2>{{ __('certificates.history') }}</h2><p>{{ __('certificates.history_notice') }}</p></div></header>
        <ol class="pc-timeline">@forelse($history as $event)
            @php($eventKey = str_replace('pro_certificate.','',$event->event))
            <li><strong>{{ in_array($eventKey,['created','updated','submit','return','approve','issue','revoke'],true) ? __('certificates.events.'.$eventKey) : __('certificates.events_unknown') }}</strong><div><span>{{ $event->actor?->name }}</span> · <time dir="ltr">{{ $event->occurred_at?->format('Y-m-d H:i') }} UTC</time></div>@if($reasons[$event->id])<p>{{ $reasons[$event->id] }}</p>@endif</li>
        @empty<li>{{ __('certificates.no_history') }}</li>@endforelse</ol>
        @include('control.pro_certificates._pagination',['paginator'=>$history])
    </section>
    @endif
</div>
@endsection
