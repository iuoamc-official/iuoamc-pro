@extends('layouts.control')
@section('title', __('master_certificates.title'))
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-master-1.2.0.css') }}">
<div class="pc-module pm-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<header class="pc-heading"><div><span class="pc-eyebrow">{{ __('master_certificates.eyebrow') }}</span><h1>{{ __('master_certificates.title') }}</h1><p>{{ __('master_certificates.lead') }}</p></div><a class="pc-button pc-button-secondary" href="{{ route('certificates.index',['locale'=>app()->getLocale()]) }}">{{ __('certificate_catalog.register_tab') }}</a></header>
@include('control.pro_certificates._tabs')
@include('control.pro_certificates._messages')
<section class="pm-issuer" aria-labelledby="pm-issuer-title">
    <img class="pm-primary-mark" src="{{ asset('assets/brand/master-v1/icga-original.jpg') }}" alt="ICGA" width="104" height="104">
    <div><span class="pc-eyebrow">{{ __('master_certificates.issuer') }}</span><h2 id="pm-issuer-title">{{ $issuer->display_name }}</h2><p dir="ltr">{{ $issuer->legal_name }}</p><div class="pm-registration"><span>{{ __('master_certificates.company_number') }} <bdi>16846998</bdi></span><span>UKPRN <bdi>10101250</bdi></span><span>{{ __('master_certificates.trademark') }} <bdi>UK00004350642</bdi></span></div></div>
</section>
<div class="pm-template-grid">
@foreach(['tasting','judging'] as $model)
<article class="pm-template-card">
    <div class="pm-card-kicker"><span>{{ __('master_certificates.portrait') }}</span><span>{{ __('master_certificates.not_issued') }}</span></div>
    <div class="pm-paper" aria-hidden="true"><img src="{{ asset('assets/brand/master-v1/icga-original.jpg') }}" alt="" width="70" height="70"><span>ICGA</span><strong>{{ __('master_certificates.'.$model.'_title') }}</strong><i></i><span>{{ __('master_certificates.name_pending') }}</span><i></i><small>{{ __('master_certificates.not_issued') }}</small></div>
    <h2>{{ __('master_certificates.'.$model.'_title') }}</h2>
    <p>{{ __('master_certificates.'.$model.'_description') }}</p>
    <dl class="pm-model-details"><div><dt>{{ __('master_certificates.program_code') }}</dt><dd>@if($model==='tasting')<bdi>PMGT-2026</bdi>@else{{ __('master_certificates.code_pending') }}@endif</dd></div><div><dt>{{ __('master_certificates.document_format') }}</dt><dd>{{ __('master_certificates.portrait') }}</dd></div></dl>
    <a class="pc-button pc-button-primary" href="{{ route('certificates.master.preview',['locale'=>app()->getLocale(),'model'=>$model]) }}" target="_blank" rel="noopener noreferrer">{{ __('master_certificates.preview_pdf') }}<span class="pm-sr-only"> — {{ __('master_certificates.'.$model.'_title') }}</span></a>
</article>
@endforeach
</div>
<section class="pc-card pm-preparation" aria-labelledby="pm-next-title"><header class="pc-card-heading"><div><h2 id="pm-next-title">{{ __('master_certificates.prepare_title') }}</h2><p>{{ __('master_certificates.prepare_help') }}</p></div></header><ol><li>{{ __('master_certificates.step_type') }}</li><li>{{ __('master_certificates.step_recipient') }}</li><li>{{ __('master_certificates.step_issue') }}</li></ol><p class="pm-note">{{ __('master_certificates.scope_note') }}</p><div class="pm-actions">
@if(auth()->user()->canDo('certificates.catalog'))
<a class="pc-button pc-button-primary" href="{{ route('certificates.catalog.create',['locale'=>app()->getLocale()]) }}">{{ __('certificate_catalog.configure_type') }}</a>
@endif
@if(auth()->user()->canDo('certificates.manage'))
<a class="pc-button pc-button-secondary" href="{{ route('certificates.create',['locale'=>app()->getLocale()]) }}">{{ __('master_certificates.prepare_draft') }}</a>
@endif
@if(auth()->user()->canDo('wicp.view'))
<a class="pc-button pc-button-secondary" href="{{ route('wicp.index',['locale'=>app()->getLocale()]) }}">{{ __('master_certificates.wicp_registry') }}</a>
@endif
</div></section>
<section class="pc-card" aria-labelledby="pm-brands-title"><header class="pc-card-heading"><div><h2 id="pm-brands-title">{{ __('master_certificates.brand_title') }}</h2><p>{{ __('master_certificates.brand_help') }}</p></div></header><div class="pm-brand-grid">
<figure><div class="pm-brand-asset pm-brand-union"><img src="{{ asset('assets/brand/master-v1/iuoamc-original.png') }}" alt="International Union of Arab Master Chefs" width="282" height="114" loading="lazy"></div><figcaption>IUOAMC<span>{{ __('master_certificates.union_role') }}</span></figcaption></figure>
<figure><div class="pm-brand-asset"><img class="pm-round" src="{{ asset('assets/brand/master-v1/icga-original.jpg') }}" alt="International Culinary and Gastronomy Arbitration" width="140" height="140" loading="lazy"></div><figcaption>ICGA<span>{{ __('master_certificates.issuer') }}</span></figcaption></figure>
<figure><div class="pm-brand-asset"><img class="pm-round" src="{{ asset('assets/brand/master-v1/wsaca-authority-seal-v3.webp') }}" alt="World Supreme Authority for Culinary Arbitration" width="140" height="140" loading="lazy"></div><figcaption>WSA-CA<span>{{ __('master_certificates.authority_role') }}</span></figcaption></figure>
<figure><div class="pm-brand-asset"><img class="pm-round" src="{{ asset('assets/brand/master-v1/wsact-titles-authority-seal-v1.webp') }}" alt="World Supreme Authority for Culinary Titles" width="140" height="140" loading="lazy"></div><figcaption>WSACT<span>{{ __('master_certificates.titles_authority_role') }}</span></figcaption></figure>
<figure><div class="pm-brand-asset"><img src="{{ asset('assets/brand/master-v1/wicp-original.webp') }}" alt="World Centre for Intellectual Protection" width="140" height="140" loading="lazy"></div><figcaption>WICP<span>{{ __('master_certificates.registry_role') }}</span></figcaption></figure>
</div></section>
</div>
@endsection
