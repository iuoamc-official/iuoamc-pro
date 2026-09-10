@extends('layouts.control')
@section('title', __($certificate->exists ? 'certificates.edit' : 'certificates.new'))
@section('content')
@php($editing = $certificate->exists)
<div class="pc-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
    <header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificates.eyebrow') }}</span><h1>{{ __($editing?'certificates.edit':'certificates.new') }}</h1><p>{{ __('certificates.form_lead') }}</p></div><a class="pc-button pc-button-secondary" href="{{ $editing ? route('certificates.show',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) : route('certificates.index',['locale'=>app()->getLocale()]) }}">{{ __('certificates.back') }}</a></header>
    @include('control.pro_certificates._tabs')
    @include('control.pro_certificates._messages')
    @if(!$editing)@include('control.pro_certificates._type_picker',['pickerAction'=>route('certificates.create',['locale'=>app()->getLocale()])])@endif
    @if($editing || $selectedType)
    @if(!$editing && $organizations->isEmpty())<div class="pc-notice pc-notice-error" role="alert">{{ __('certificates.no_organizations') }}</div>@endif
    <form class="pc-form" method="post" action="{{ $editing ? route('certificates.update',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) : route('certificates.store',['locale'=>app()->getLocale()]) }}">
        @csrf
        @if(!$editing)<input type="hidden" name="catalog_type_id" value="{{ $selectedType->id }}"><input type="hidden" name="organization_id" value="{{ $selectedType->organization_id }}">@endif
        @if($editing)@method('put')<input type="hidden" name="lock_version" value="{{ old('lock_version',$certificate->lock_version) }}">@endif
        <section class="pc-card"><header class="pc-card-heading"><span class="pc-step" aria-hidden="true">01</span><div><h2>{{ __('certificates.identity') }}</h2><p>{{ __('certificates.required_fields') }}</p></div></header>
            <div class="pc-fields">
                <label class="pc-field pc-full"><span>{{ __('certificates.organization') }} *</span>
                    @if(!$editing)<input value="{{ $selectedType->organization?->display_name }}" readonly>
                    @elseif($editing)<input value="{{ $certificate->organization?->display_name }}" readonly><small>{{ __('certificates.organization_locked') }}</small>
                    @else<select name="organization_id" required><option value="">{{ __('certificates.choose') }}</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)old('organization_id')===(string)$organization->id)>{{ $organization->display_name }}</option>@endforeach</select>@endif
                </label>
                <label class="pc-field"><span>{{ __('certificates.recipient_name') }} *</span><input name="recipient_name" required maxlength="180" autocomplete="off" value="{{ old('recipient_name',$certificate->recipient_name) }}" aria-describedby="pc-recipient-help"><small id="pc-recipient-help">{{ __('certificates.recipient_help') }}</small></label>
                <label class="pc-field"><span>{{ __('certificates.public_name') }} *</span><input name="public_name" required maxlength="120" autocomplete="off" value="{{ old('public_name',$certificate->public_name) }}" aria-describedby="pc-public-help"><small id="pc-public-help">{{ __('certificates.public_name_help') }}</small></label>
            </div>
        </section>
        <section class="pc-card"><header class="pc-card-heading"><span class="pc-step" aria-hidden="true">02</span><div><h2>{{ __('certificates.content') }}</h2><p>{{ __('certificates.statement_help') }}</p></div></header>
            <div class="pc-fields">
                <label class="pc-field"><span>{{ __('certificates.certificate_title') }} *</span><input name="certificate_title" required maxlength="120" value="{{ old('certificate_title',$certificate->certificate_title) }}"></label>
                <label class="pc-field"><span>{{ __('certificates.program_title') }} *</span><input name="program_title" required maxlength="200" value="{{ old('program_title',$certificate->program_title) }}"></label>
                <label class="pc-field"><span>{{ __('certificates.certificate_type') }} *</span>
                    @if(!$editing || (int)$certificate->schema_version===2)
                    <input value="{{ !$editing?$selectedType->{'name_'.app()->getLocale()}:($certificate->catalog_snapshot['names'][app()->getLocale()]??__('certificates.types.'.$certificate->certificate_type)) }}" readonly><small>{{ __('certificate_catalog.type_locked') }}</small>
                    @else<select name="certificate_type" required>@foreach(['participation','completion','appreciation'] as $type)<option value="{{ $type }}" @selected(old('certificate_type',$certificate->certificate_type)===$type)>{{ __('certificates.types.'.$type) }}</option>@endforeach</select>@endif
                </label>
                @if(!$editing || (int)$certificate->schema_version===2)<label class="pc-field"><span>{{ __('certificate_catalog.specialization') }}</span><input name="specialization" maxlength="200" value="{{ old('specialization',$certificate->specialization) }}"><small>{{ __('certificate_catalog.specialization_help') }}</small></label>@endif
                <label class="pc-field"><span>{{ __('certificates.language') }} *</span><select name="language" required>@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $locale=>$label)<option value="{{ $locale }}" @selected(old('language',$certificate->language)===$locale)>{{ $label }}</option>@endforeach</select></label>
                <label class="pc-field"><span>{{ __('certificates.achievement_date') }} *</span><input type="date" name="achievement_date" required dir="ltr" value="{{ old('achievement_date',$certificate->achievement_date?->format('Y-m-d')) }}"></label>
                <label class="pc-field"><span>{{ __('certificates.expires_on') }}</span><input type="date" name="expires_on" dir="ltr" value="{{ old('expires_on',$certificate->expires_on?->format('Y-m-d')) }}"><small>{{ __('certificates.no_expiry') }}</small></label>
                <label class="pc-field pc-full"><span>{{ __('certificates.statement') }} *</span><textarea name="statement" required maxlength="1500" rows="6">{{ old('statement',$certificate->statement) }}</textarea></label>
            </div>
        </section>
        <section class="pc-card"><header class="pc-card-heading"><span class="pc-step" aria-hidden="true">03</span><div><h2>{{ __('certificates.signature') }}</h2></div></header><div class="pc-fields">
            <label class="pc-field"><span>{{ __('certificates.signatory_name') }} *</span><input name="signatory_name" required maxlength="120" value="{{ old('signatory_name',$certificate->signatory_name) }}"></label>
            <label class="pc-field"><span>{{ __('certificates.signatory_title') }} *</span><input name="signatory_title" required maxlength="120" value="{{ old('signatory_title',$certificate->signatory_title) }}"></label>
        </div></section>
        <div class="pc-form-footer"><p>{{ __('certificates.draft_notice') }}</p><div class="pc-button-row"><a class="pc-button pc-button-secondary" href="{{ route('certificates.index',['locale'=>app()->getLocale()]) }}">{{ __('certificates.cancel') }}</a><button class="pc-button pc-button-primary" type="submit" @disabled(!$editing && $organizations->isEmpty())>{{ __('certificates.save') }}</button></div></div>
    </form>
    @endif
</div>
@endsection
