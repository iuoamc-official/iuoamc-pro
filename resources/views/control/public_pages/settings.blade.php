@extends('layouts.control')
@section('title', __('public_site.control.identity'))
@section('content')
    <section class="page-heading compact-heading"><div><span class="eyebrow">PUBLIC EXPERIENCE / BRAND CONTROL</span><h1>{{ __('public_site.control.identity') }}</h1><p>{{ __('public_site.control.identity_help') }}</p></div><a class="secondary-action" href="{{ route('public-content.pages.index', ['locale' => app()->getLocale()]) }}">{{ __('public_site.control.back') }}</a></section>
    @if($errors->any())<div class="alert alert-error" role="alert"><strong>{{ $errors->first() }}</strong></div>@endif
    <form class="institutional-form" method="post" action="{{ route('public-content.settings.update', ['locale' => app()->getLocale()]) }}">@csrf @method('put')
        <section class="form-card"><header><span class="form-step">ID</span><div><h2>{{ __('public_site.control.identity') }}</h2><p>{{ __('public_site.control.identity_help') }}</p></div></header><div class="form-grid two-columns">
            <label class="field"><span>{{ __('public_site.control.brand_name') }}</span><input name="brand_name" required maxlength="80" value="{{ old('brand_name', $profile['brand_name']) }}"></label>
            <label class="field"><span>{{ __('public_site.control.legal_name') }}</span><input name="legal_name" required maxlength="180" value="{{ old('legal_name', $profile['legal_name']) }}"></label>
            <label class="field"><span>{{ __('public_site.control.logo') }}</span><select name="primary_logo">@foreach($allowedLogos as $logo)<option value="{{ $logo }}" @selected(old('primary_logo', $profile['primary_logo']) === $logo)>{{ basename($logo) }}</option>@endforeach</select></label>
            <label class="field"><span>{{ __('public_site.control.email') }}</span><input type="email" name="contact_email" required value="{{ old('contact_email', $profile['contact_email']) }}"></label>
            <label class="field"><span>{{ __('public_site.control.registration_label') }}</span><input name="registration_label" required value="{{ old('registration_label', $profile['registration_label']) }}"></label>
            <label class="field"><span>{{ __('public_site.control.registration_number') }}</span><input name="registration_number" required value="{{ old('registration_number', $profile['registration_number']) }}"></label>
        </div></section>
        <section class="form-card"><header><span class="form-step">NEWS</span><div><h2>{{ __('public_site.control.announcement') }}</h2><p>{{ __('public_site.control.announcement_help') }}</p></div></header><div class="form-grid">@foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $label)<label class="field"><span>{{ $label }}</span><input name="announcement[{{ $locale }}]" maxlength="240" value="{{ old('announcement.'.$locale, $profile['announcement'][$locale] ?? '') }}"></label>@endforeach</div></section>
        <div class="form-footer"><p><span class="status-dot"></span>{{ __('public_site.control.audit_notice') }}</p><button class="primary-action" type="submit">{{ __('public_site.control.save') }}</button></div>
    </form>
@endsection
