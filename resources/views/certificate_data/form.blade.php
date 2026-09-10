@extends('certificate_data.layout')
@section('title', __('certificate_intake.public_title'))
@section('languages')
<nav class="ci-languages" aria-label="{{ __('certificate_intake.language') }}">
@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $language=>$label)
    <a href="{{ route('certificate-data.form',['locale'=>$language,'token'=>$token]) }}" lang="{{ $language }}" hreflang="{{ $language }}" @if(app()->getLocale()===$language) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
@endsection
@section('content')
@php
    $snapshot = $intake->source_snapshot ?? [];
    $fieldValue = static fn(string $field): string => is_scalar($values[$field] ?? null) ? (string)$values[$field] : '';
    $program = app()->getLocale()==='ar' ? (($snapshot['program_name_ar'] ?? '') ?: ($snapshot['program_name_en'] ?? '')) : (($snapshot['program_name_en'] ?? '') ?: ($snapshot['program_name_ar'] ?? ''));
    $program = is_scalar($program) ? (string)$program : '';
    $programCode = is_scalar($snapshot['program_code'] ?? null) ? (string)$snapshot['program_code'] : '';
@endphp
<header class="ci-intro"><span class="ci-eyebrow">{{ __('certificate_intake.public_eyebrow') }}</span><h1>{{ __('certificate_intake.public_title') }}</h1><p>{{ __('certificate_intake.public_lead') }}</p></header>
<div class="ci-form-layout">
    <section class="ci-form-card" aria-labelledby="ci-details-title">
        <div class="ci-card-heading"><span class="ci-step-number" aria-hidden="true">01</span><div><h2 id="ci-details-title">{{ __('certificate_intake.your_details') }}</h2><p>{{ __('certificate_intake.required_hint') }}</p></div></div>
        @if($errors->any())
        <div class="ci-error-summary" role="alert" aria-labelledby="ci-error-title"><h2 id="ci-error-title">{{ __('certificate_intake.fix_errors') }}</h2><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
        <form method="post" action="{{ route('certificate-data.submit',['locale'=>app()->getLocale(),'token'=>$token]) }}" class="ci-form">
            @csrf
            <div class="ci-field-grid">
                <div class="ci-field">
                    <label for="ci-name-ar">{{ __('certificate_intake.fields.name_ar') }} <span class="ci-optional">{{ __('certificate_intake.optional') }}</span></label>
                    <input id="ci-name-ar" name="name_ar" value="{{ $fieldValue('name_ar') }}" maxlength="180" dir="rtl" autocomplete="name" aria-describedby="ci-name-ar-hint{{ $errors->has('name_ar') ? ' ci-name-ar-error' : '' }}" @if($errors->has('name_ar')) aria-invalid="true" @endif>
                    <span class="ci-field-hint" id="ci-name-ar-hint">{{ __('certificate_intake.name_ar_hint') }}</span>
                    @if($errors->has('name_ar'))<span class="ci-field-error" id="ci-name-ar-error">{{ $errors->first('name_ar') }}</span>@endif
                </div>
                <div class="ci-field">
                    <label for="ci-name-en">{{ __('certificate_intake.fields.name_en') }} <span class="ci-required" aria-hidden="true">*</span></label>
                    <input id="ci-name-en" name="name_en" value="{{ $fieldValue('name_en') }}" maxlength="180" dir="ltr" autocomplete="name" required aria-describedby="ci-name-en-hint{{ $errors->has('name_en') ? ' ci-name-en-error' : '' }}" @if($errors->has('name_en')) aria-invalid="true" @endif>
                    <span class="ci-field-hint" id="ci-name-en-hint">{{ __('certificate_intake.name_en_hint') }}</span>
                    @if($errors->has('name_en'))<span class="ci-field-error" id="ci-name-en-error">{{ $errors->first('name_en') }}</span>@endif
                </div>
                <div class="ci-field">
                    <label for="ci-email">{{ __('certificate_intake.fields.email') }} <span class="ci-required" aria-hidden="true">*</span></label>
                    <input id="ci-email" type="email" name="email" value="{{ $fieldValue('email') }}" maxlength="254" dir="ltr" autocomplete="email" required @if($errors->has('email')) aria-invalid="true" aria-describedby="ci-email-error" @endif>
                    @if($errors->has('email'))<span class="ci-field-error" id="ci-email-error">{{ $errors->first('email') }}</span>@endif
                </div>
                <div class="ci-field">
                    <label for="ci-phone">{{ __('certificate_intake.fields.phone') }} <span class="ci-optional">{{ __('certificate_intake.optional') }}</span></label>
                    <input id="ci-phone" type="tel" name="phone" value="{{ $fieldValue('phone') }}" maxlength="40" dir="ltr" autocomplete="tel" aria-describedby="ci-phone-hint{{ $errors->has('phone') ? ' ci-phone-error' : '' }}" @if($errors->has('phone')) aria-invalid="true" @endif>
                    <span class="ci-field-hint" id="ci-phone-hint">{{ __('certificate_intake.phone_hint') }}</span>
                    @if($errors->has('phone'))<span class="ci-field-error" id="ci-phone-error">{{ $errors->first('phone') }}</span>@endif
                </div>
                <div class="ci-field">
                    <label for="ci-country">{{ __('certificate_intake.fields.country') }} <span class="ci-optional">{{ __('certificate_intake.optional') }}</span></label>
                    <input id="ci-country" name="country" value="{{ $fieldValue('country') }}" maxlength="100" autocomplete="country-name" @if($errors->has('country')) aria-invalid="true" aria-describedby="ci-country-error" @endif>
                    @if($errors->has('country'))<span class="ci-field-error" id="ci-country-error">{{ $errors->first('country') }}</span>@endif
                </div>
                <div class="ci-field">
                    <label for="ci-specialization">{{ __('certificate_intake.fields.specialization') }} <span class="ci-required" aria-hidden="true">*</span></label>
                    <input id="ci-specialization" name="specialization" value="{{ $fieldValue('specialization') }}" maxlength="160" required aria-describedby="ci-specialization-hint{{ $errors->has('specialization') ? ' ci-specialization-error' : '' }}" @if($errors->has('specialization')) aria-invalid="true" @endif>
                    <span class="ci-field-hint" id="ci-specialization-hint">{{ __('certificate_intake.specialization_hint') }}</span>
                    @if($errors->has('specialization'))<span class="ci-field-error" id="ci-specialization-error">{{ $errors->first('specialization') }}</span>@endif
                </div>
                <div class="ci-field ci-field-wide">
                    <label for="ci-notes">{{ __('certificate_intake.fields.notes') }} <span class="ci-optional">{{ __('certificate_intake.optional') }}</span></label>
                    <textarea id="ci-notes" name="notes" rows="4" maxlength="1000" aria-describedby="ci-notes-hint{{ $errors->has('notes') ? ' ci-notes-error' : '' }}" @if($errors->has('notes')) aria-invalid="true" @endif>{{ $fieldValue('notes') }}</textarea>
                    <span class="ci-field-hint" id="ci-notes-hint">{{ __('certificate_intake.notes_hint') }}</span>
                    @if($errors->has('notes'))<span class="ci-field-error" id="ci-notes-error">{{ $errors->first('notes') }}</span>@endif
                </div>
            </div>
            <div class="ci-confirmation">
                <label for="ci-confirmation"><input id="ci-confirmation" type="checkbox" name="confirmation" value="1" required @checked(in_array($fieldValue('confirmation'),['1','yes','on','true'],true)) @if($errors->has('confirmation')) aria-invalid="true" aria-describedby="ci-confirmation-error" @endif><span>{{ __('certificate_intake.confirmation_text') }} <span class="ci-required" aria-hidden="true">*</span></span></label>
                @if($errors->has('confirmation'))<span class="ci-field-error" id="ci-confirmation-error">{{ $errors->first('confirmation') }}</span>@endif
            </div>
            <div class="ci-submit-row"><button class="ci-button ci-button-primary" type="submit">{{ __('certificate_intake.submit') }}<svg viewBox="0 0 24 24" aria-hidden="true" width="20" height="20"><path d="m5 12 4 4L19 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button><p>{{ __('certificate_intake.submit_hint') }}</p></div>
        </form>
    </section>
    <aside class="ci-context">
        <section class="ci-program-card" aria-labelledby="ci-program-title"><span class="ci-eyebrow">{{ __('certificate_intake.your_program') }}</span><h2 id="ci-program-title"><bdi>{{ $program !== '' ? $program : __('certificate_intake.program_on_record') }}</bdi></h2>@if($programCode !== '')<span class="ci-program-code"><bdi dir="ltr">{{ $programCode }}</bdi></span>@endif<p>{{ __('certificate_intake.program_hint') }}</p></section>
        <section class="ci-next-card" aria-labelledby="ci-next-title"><h2 id="ci-next-title">{{ __('certificate_intake.what_next') }}</h2><ol><li><span>{{ __('certificate_intake.next_confirm') }}</span></li><li><span>{{ __('certificate_intake.next_review') }}</span></li><li><span>{{ __('certificate_intake.next_prepare') }}</span></li></ol><p class="ci-private-note"><svg viewBox="0 0 24 24" aria-hidden="true" width="19" height="19"><rect x="5" y="10" width="14" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8 10V6a4 4 0 0 1 8 0v4" fill="none" stroke="currentColor" stroke-width="1.7"/></svg><span>{{ __('certificate_intake.privacy_hint') }}</span></p></section>
    </aside>
</div>
@endsection
