@extends('layouts.control')
@section('title', __($type->exists?'certificate_catalog.edit_type':'certificate_catalog.new_type'))
@section('content')
@php($editing=$type->exists)
<div class="pc-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificates.eyebrow') }}</span><h1>{{ __($editing?'certificate_catalog.edit_type':'certificate_catalog.new_type') }}</h1><p>{{ __('certificate_catalog.catalog_lead') }}</p></div><a class="pc-button pc-button-secondary" href="{{ route('certificates.catalog.index',['locale'=>app()->getLocale()]) }}">{{ __('certificate_catalog.back_catalog') }}</a></header>
@include('control.pro_certificates._tabs')
@include('control.pro_certificates._messages')
<form method="post" action="{{ $editing?route('certificates.catalog.update',['locale'=>app()->getLocale(),'type'=>$type->id]):route('certificates.catalog.store',['locale'=>app()->getLocale()]) }}" class="pc-form">@csrf
@if($editing)@method('put')<input type="hidden" name="lock_version" value="{{ old('lock_version',$type->lock_version) }}">@endif
<section class="pc-card"><header class="pc-card-heading"><div><h2>{{ __('certificates.identity') }}</h2><p>{{ __('certificate_catalog.catalog_immutable') }}</p></div></header><div class="pc-fields">
<label class="pc-field pc-full"><span>{{ __('certificates.organization') }} *</span>@if($editing)<input value="{{ $type->organization?->display_name }}" readonly>@else<select name="organization_id" required><option value="">{{ __('certificates.choose') }}</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)old('organization_id')===(string)$organization->id)>{{ $organization->display_name }}</option>@endforeach</select>@endif</label>
<label class="pc-field"><span>{{ __('certificate_catalog.type_code') }} *</span><input @if(!$editing) name="code" @endif value="{{ old('code',$type->code) }}" required maxlength="20" pattern="[A-Z][A-Z0-9\-]{1,19}" dir="ltr" autocomplete="off" @readonly($editing)><small>{{ __('certificate_catalog.code_help') }}</small></label>
<label class="pc-field"><span>{{ __('certificate_catalog.number_prefix') }} *</span><input @if(!$editing) name="number_prefix" @endif value="{{ old('number_prefix',$type->number_prefix) }}" required maxlength="40" pattern="[A-Z][A-Z0-9\-]{1,39}" dir="ltr" autocomplete="off" @readonly($editing)><small>{{ __('certificate_catalog.prefix_help') }}</small></label>
<label class="pc-field"><span>{{ __('certificate_catalog.type_category') }} *</span><select name="category" required>@foreach(['participation','completion','appreciation','diploma','professional_master'] as $category)<option value="{{ $category }}" @selected(old('category',$type->category)===$category)>{{ __('certificates.types.'.$category) }}</option>@endforeach</select></label>
<label class="pc-field"><span>{{ __('certificate_catalog.layout') }} *</span><select name="layout" required>@foreach(['classic','diploma','master_a4_v1'] as $layout)<option value="{{ $layout }}" @selected(old('layout',$type->layout)===$layout)>{{ __('certificate_catalog.layout_'.$layout) }}</option>@endforeach</select><small>{{ __('master_certificates.layout_help') }}</small></label>
<label class="pc-field"><span>{{ __('certificate_catalog.type_status') }} *</span><select name="active" required><option value="1" @selected((string)old('active',$type->active?'1':'0')==='1')>{{ __('certificate_catalog.active') }}</option><option value="0" @selected((string)old('active',$type->active?'1':'0')==='0')>{{ __('certificate_catalog.inactive') }}</option></select></label>
</div></section>
<section class="pc-card"><header class="pc-card-heading"><div><h2>{{ __('certificate_catalog.defaults_heading') }}</h2><p>{{ __('certificate_catalog.defaults_help') }}</p></div></header>
<div class="pc-locale-grid">@foreach(['ar','en','fr'] as $language)<fieldset class="pc-locale-card"><legend>{{ __('certificate_catalog.language_'.$language) }}</legend>
<label class="pc-field"><span>{{ __('certificate_catalog.type_name') }} *</span><input name="name_{{ $language }}" required maxlength="120" dir="{{ $language==='ar'?'rtl':'ltr' }}" value="{{ old('name_'.$language,$type->{'name_'.$language}) }}"></label>
<label class="pc-field"><span>{{ __('certificates.certificate_title') }} *</span><input name="title_{{ $language }}" required maxlength="120" dir="{{ $language==='ar'?'rtl':'ltr' }}" value="{{ old('title_'.$language,$type->{'title_'.$language}) }}"></label>
<label class="pc-field"><span>{{ __('certificates.statement') }} *</span><textarea name="statement_{{ $language }}" required maxlength="1500" rows="7" dir="{{ $language==='ar'?'rtl':'ltr' }}">{{ old('statement_'.$language,$type->{'statement_'.$language}) }}</textarea></label>
</fieldset>@endforeach</div>
</section><section class="pc-card"><header class="pc-card-heading"><div><h2>{{ __('certificates.signature') }}</h2></div></header><div class="pc-fields">
<label class="pc-field"><span>{{ __('certificates.signatory_name') }} *</span><input name="signatory_name" required maxlength="120" value="{{ old('signatory_name',$type->signatory_name) }}"></label>
<label class="pc-field"><span>{{ __('certificates.signatory_title') }} *</span><input name="signatory_title" required maxlength="120" value="{{ old('signatory_title',$type->signatory_title) }}"></label>
</div></section>
<div class="pc-form-footer"><p>{{ __('certificate_catalog.catalog_immutable') }}</p><button class="pc-button pc-button-primary" type="submit">{{ __('certificate_catalog.save_type') }}</button></div>
</form></div>
@endsection
