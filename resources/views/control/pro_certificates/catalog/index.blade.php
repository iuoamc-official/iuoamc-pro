@extends('layouts.control')
@section('title', __('certificate_catalog.catalog_title'))
@section('content')
<div class="pc-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificates.eyebrow') }}</span><h1>{{ __('certificate_catalog.catalog_title') }}</h1><p>{{ __('certificate_catalog.catalog_lead') }}</p></div>@if(auth()->user()->canDo('certificates.catalog'))<a class="pc-button pc-button-primary" href="{{ route('certificates.catalog.create',['locale'=>app()->getLocale()]) }}">{{ __('certificate_catalog.new_type') }} +</a>@endif</header>
@include('control.pro_certificates._tabs')
@include('control.pro_certificates._messages')
<section class="pc-card">
@if($types->count())<div class="pc-catalog-grid">@foreach($types as $type)
<article class="pc-type-card">
@if($checks[$type->id])
<header><span class="pc-type-symbol" aria-hidden="true">{{ $type->layout==='diploma'?'◇':'▤' }}</span><span class="pc-badge pc-state-{{ $type->active?'issued':'revoked' }}">{{ __('certificate_catalog.'.($type->active?'active':'inactive')) }}</span></header>
<h2>{{ $type->{'name_'.app()->getLocale()} }}</h2><p>{{ $type->organization?->display_name }}</p>
<dl class="pc-facts"><div><dt>{{ __('certificate_catalog.type_code') }}</dt><dd><bdi dir="ltr">{{ $type->code }}</bdi></dd></div><div><dt>{{ __('certificate_catalog.number_prefix') }}</dt><dd><bdi dir="ltr">{{ $type->number_prefix }}</bdi></dd></div><div><dt>{{ __('certificate_catalog.type_category') }}</dt><dd>{{ __('certificates.types.'.$type->category) }}</dd></div><div><dt>{{ __('certificate_catalog.type_version') }}</dt><dd>{{ $type->lock_version }}</dd></div></dl>
<div class="pc-button-row">@if(auth()->user()->canDo('certificates.catalog'))<a class="pc-button pc-button-secondary" href="{{ route('certificates.catalog.edit',['locale'=>app()->getLocale(),'type'=>$type->id]) }}">{{ __('certificate_catalog.edit_type') }}</a>@endif
@if($type->active && auth()->user()->canDo('certificates.manage'))<a class="pc-text-link" href="{{ route('certificates.create',['locale'=>app()->getLocale(),'type_id'=>$type->id]) }}">{{ __('certificates.new') }}</a>@endif</div>
@else<div class="pc-notice pc-notice-error">{{ __('certificates.integrity_failed') }}</div>@endif
</article>@endforeach</div>
@include('control.pro_certificates._pagination',['paginator'=>$types])
@else<div class="pc-empty"><span class="pc-empty-mark" aria-hidden="true">◇</span><h2>{{ __('certificate_catalog.empty_catalog') }}</h2><p>{{ __('certificate_catalog.empty_catalog_help') }}</p></div>@endif
</section></div>
@endsection
