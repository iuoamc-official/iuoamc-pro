@extends('layouts.account')
@section('title', __('account.apply_membership'))
@section('content')
<section class="account-hero"><div><span>IUOAMC / MEMBERSHIP</span><h1>{{ __('account.apply_membership') }}</h1><p>{{ __('account.application_intro') }}</p></div></section>
<form method="post" enctype="multipart/form-data" action="{{ route('account.membership.store',['locale'=>app()->getLocale()]) }}" class="account-form">
    @csrf
    <section class="account-card form-section"><header><div><span>01</span><h2>{{ __('account.membership_details') }}</h2></div></header><div class="form-grid">
        <label><span>{{ __('account.organization') }} *</span><select name="organization_id" required><option value="">{{ __('account.choose') }}</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected(old('organization_id')==$organization->id)>{{ $organization->display_name }}</option>@endforeach</select></label>
        <label><span>{{ __('account.membership_type') }} *</span><input name="membership_type" required maxlength="120" value="{{ old('membership_type') }}"></label>
        <label><span>{{ __('account.full_name') }} *</span><input name="full_name" required maxlength="255" value="{{ old('full_name',auth()->user()->name) }}"></label>
        <label><span>{{ __('account.latin_name') }}</span><input name="latin_name" dir="ltr" maxlength="255" value="{{ old('latin_name') }}"></label>
        <label><span>{{ __('account.professional_title') }}</span><input name="professional_title" maxlength="160" value="{{ old('professional_title') }}"></label>
        <label><span>{{ __('account.country_code') }} *</span><input name="country_code" dir="ltr" maxlength="2" placeholder="GB" required value="{{ old('country_code') }}"></label>
        <label><span>{{ __('account.email') }}</span><input type="email" readonly value="{{ auth()->user()->email }}"></label>
        <label><span>{{ __('account.phone') }} *</span><input name="phone" type="tel" dir="ltr" maxlength="40" required value="{{ old('phone') }}"></label>
    </div></section>
    <section class="account-card form-section"><header><div><span>02</span><h2>{{ __('account.identity_details') }}</h2></div><small>{{ __('account.private_data') }}</small></header><div class="form-grid">
        <label><span>{{ __('account.date_of_birth') }} *</span><input type="date" name="date_of_birth" required value="{{ old('date_of_birth') }}"></label>
        <label><span>{{ __('account.nationality_code') }} *</span><input name="nationality_code" dir="ltr" maxlength="2" required value="{{ old('nationality_code') }}"></label>
        <label><span>{{ __('account.address') }} *</span><input name="address" required maxlength="1000" value="{{ old('address') }}"></label>
        <label><span>{{ __('account.city') }} *</span><input name="city" required maxlength="120" value="{{ old('city') }}"></label>
        <label><span>{{ __('account.postal_code') }}</span><input name="postal_code" maxlength="30" value="{{ old('postal_code') }}"></label>
        <label><span>{{ __('account.residence_country_code') }} *</span><input name="residence_country_code" dir="ltr" maxlength="2" required value="{{ old('residence_country_code') }}"></label>
        <label><span>{{ __('account.identification_type') }} *</span><input name="identification_type" required maxlength="60" value="{{ old('identification_type') }}"></label>
        <label><span>{{ __('account.identification_number') }} *</span><input name="identification_number" required maxlength="120" value="{{ old('identification_number') }}"></label>
        <label class="wide"><span>{{ __('account.qualifications') }}</span><textarea name="qualifications" rows="4" maxlength="3000">{{ old('qualifications') }}</textarea></label>
        <label class="wide upload"><span>{{ __('account.photo') }} *</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><small>{{ __('account.photo_help') }}</small></label>
    </div></section>
    <section class="account-card consent"><label><input type="checkbox" name="application_consent" value="1" required @checked(old('application_consent'))><span>{{ __('account.consent') }}</span></label></section>
    <div class="form-footer"><a class="button secondary" href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}">{{ __('account.cancel') }}</a><button class="button" type="submit">{{ __('account.submit_application') }}</button></div>
</form>
@endsection
