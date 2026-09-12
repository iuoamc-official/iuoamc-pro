@extends('layouts.account')
@section('title', __('account.apply_membership'))
@section('content')
<section class="account-hero"><div><span>IUOAMC / MEMBERSHIP</span><h1>{{ __('account.apply_membership') }}</h1><p>{{ __('account.application_intro') }}</p></div></section>
<form method="post" enctype="multipart/form-data" action="{{ route('account.membership.store',['locale'=>app()->getLocale()]) }}" class="account-form">
    @csrf
    <section class="account-card form-section"><header><div><span>01</span><h2>{{ __('account.membership_details') }}</h2></div></header><div class="form-grid">
        <label><span>{{ __('account.organization') }} *</span><select name="organization_id" required><option value="">{{ __('account.choose') }}</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected(old('organization_id')==$organization->id)>{{ $organization->display_name }}</option>@endforeach</select></label>
        <label><span>{{ __('account.membership_type') }} *</span><select name="membership_category_code" required><option value="">{{ __('account.choose') }}</option>@foreach($membershipCategories as $code=>$label)<option value="{{ $code }}" @selected(old('membership_category_code')===$code)>{{ $label }}</option>@endforeach</select></label>
        <label><span>{{ __('account.membership_term') }} *</span><select name="membership_term_years" required><option value="">{{ __('account.choose') }}</option>@foreach($membershipTermFees as $years=>$fee)<option value="{{ $years }}" @selected((string)old('membership_term_years')===(string)$years)>{{ trans_choice('account.years', $years, ['count'=>$years]) }} — £{{ number_format($fee / 100, 0) }}</option>@endforeach</select></label>
        <label><span>{{ __('account.full_name') }} *</span><input name="full_name" required maxlength="255" value="{{ old('full_name',auth()->user()->name) }}"></label>
        <label><span>{{ __('account.latin_name') }}</span><input name="latin_name" dir="ltr" maxlength="255" value="{{ old('latin_name') }}"></label>
        <label><span>{{ __('account.professional_title') }} *</span><select name="member_title_code" required><option value="">{{ __('account.choose') }}</option>@foreach($memberTitles as $code=>$label)<option value="{{ $code }}" @selected(old('member_title_code')===$code)>{{ $label }}</option>@endforeach</select></label>
        <label><span>{{ __('account.payment_method') }} *</span><select id="requested-payment-method" name="requested_payment_method_code" required><option value="">{{ __('account.choose') }}</option>@foreach($paymentMethods as $code=>$label)<option value="{{ $code }}" @selected(old('requested_payment_method_code')===$code)>{{ $label }}</option>@endforeach</select><small>{{ __('account.payment_method_notice') }}</small></label>
        <label id="fee-waiver-reason-field" class="wide" hidden><span>{{ __('account.fee_waiver_reason') }} *</span><textarea id="fee-waiver-reason" name="fee_waiver_reason" rows="4" minlength="20" maxlength="1500">{{ old('fee_waiver_reason') }}</textarea><small>{{ __('account.fee_waiver_notice') }}</small></label>
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
    <section class="account-card membership-policy-summary">
        <header><div><span>03</span><h2>{{ __('account.fees_and_terms') }}</h2></div></header>
        <p class="policy-warning">{{ __('account.membership_not_automatic') }}</p>
        <ul>
            <li>{{ __('account.file_review_deduction') }}</li>
            <li>{{ __('account.discount_refund_basis') }}</li>
            <li>{{ __('account.rejected_application_refund') }}</li>
        </ul>
        <p><a href="{{ route('legal.membership-terms',['locale'=>app()->getLocale()]) }}" target="_blank" rel="noopener noreferrer">{{ __('account.read_membership_terms') }}</a> · <a href="{{ route('legal.membership-privacy',['locale'=>app()->getLocale()]) }}" target="_blank" rel="noopener noreferrer">{{ __('account.read_privacy_notice') }}</a></p>
        <fieldset class="service-start-choice"><legend>{{ __('account.service_start_title') }} *</legend>
            <label><input type="radio" name="service_start_choice" value="immediate" required @checked(old('service_start_choice')==='immediate')><span>{{ __('account.service_start_immediate') }}</span></label>
            <label><input type="radio" name="service_start_choice" value="after_cooling_off" required @checked(old('service_start_choice')==='after_cooling_off')><span>{{ __('account.service_start_after') }}</span></label>
        </fieldset>
    </section>
    <section class="account-card consent">
        <label><input type="checkbox" name="application_consent" value="1" required @checked(old('application_consent'))><span>{{ __('account.consent') }}</span></label>
        <label><input type="checkbox" name="terms_consent" value="1" required @checked(old('terms_consent'))><span>{{ __('account.terms_consent') }}</span></label>
    </section>
    <div class="form-footer"><a class="button secondary" href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}">{{ __('account.cancel') }}</a><button class="button" type="submit">{{ __('account.submit_application') }}</button></div>
</form>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const select = document.getElementById('requested-payment-method');
    const field = document.getElementById('fee-waiver-reason-field');
    const reason = document.getElementById('fee-waiver-reason');
    const waiverCodes = @json($waiverPaymentMethods);
    const sync = () => {
        const required = waiverCodes.includes(select.value);
        field.hidden = !required;
        reason.required = required;
    };
    select.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
