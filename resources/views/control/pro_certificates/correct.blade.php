@extends('layouts.control')
@section('title', __('certificates.correct'))
@section('content')
<div class="pc-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
    <header class="pc-heading">
        <div><span class="pc-eyebrow">{{ __('certificates.correction_eyebrow') }}</span><h1>{{ __('certificates.correct') }}</h1><p><bdi dir="ltr">{{ $certificate->certificate_number }}</bdi></p></div>
        <a class="pc-button pc-button-secondary" href="{{ route('certificates.show',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}">{{ __('certificates.back_to_record') }}</a>
    </header>
    @include('control.pro_certificates._messages')

    <div class="pc-notice pc-notice-warning">
        <strong>{{ __('certificates.correction_protection_title') }}</strong>
        <p>{{ __('certificates.correction_protection_notice') }}</p>
    </div>

    <div class="pc-detail-grid">
        <section class="pc-card">
            <header class="pc-card-heading"><div><h2>{{ __('certificates.delivery_contact_title') }}</h2><p>{{ __('certificates.delivery_contact_notice') }}</p></div></header>
            <form method="post" action="{{ route('certificates.delivery-contact.update',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}">
                @csrf
                @method('PATCH')
                <label>{{ __('certificates.recipient_email') }}
                    <input type="email" name="recipient_email" maxlength="254" value="{{ old('recipient_email',$deliveryEmail) }}" autocomplete="email">
                </label>
                <button class="pc-button pc-button-primary" type="submit">{{ __('certificates.save_delivery_email') }}</button>
            </form>
        </section>

        <section class="pc-card">
            <header class="pc-card-heading"><div><h2>{{ __('certificates.identity_correction_title') }}</h2><p>{{ __('certificates.identity_correction_notice') }}</p></div></header>
            @if($replacement)
                <div class="pc-notice"><strong>{{ __('certificates.replacement_exists') }}</strong><p><a href="{{ route('certificates.show',['locale'=>app()->getLocale(),'certificate'=>$replacement->id]) }}">{{ $replacement->certificate_number ?: $replacement->record_uuid }}</a></p></div>
            @elseif($certificate->status==='issued')
                <form method="post" action="{{ route('certificates.replacement.store',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}">
                    @csrf
                    <label>{{ __('certificates.recipient_name') }}
                        <input required name="recipient_name" maxlength="180" value="{{ old('recipient_name',$certificate->recipient_name) }}">
                    </label>
                    <label>{{ __('certificates.public_name') }}
                        <input required name="public_name" maxlength="120" value="{{ old('public_name',$certificate->public_name) }}">
                    </label>
                    <label>{{ __('certificates.recipient_email') }}
                        <input type="email" name="recipient_email" maxlength="254" value="{{ old('recipient_email',$deliveryEmail) }}">
                    </label>
                    <label>{{ __('certificates.correction_reason') }}
                        <textarea required name="reason" maxlength="1500">{{ old('reason') }}</textarea>
                    </label>
                    <label><input type="checkbox" required> {{ __('certificates.confirm_replacement') }}</label>
                    <button class="pc-button pc-button-primary" type="submit">{{ __('certificates.create_replacement') }}</button>
                </form>
            @else
                <p class="pc-help">{{ __('certificates.revoked_correction_notice') }}</p>
            @endif
        </section>
    </div>
</div>
@endsection
