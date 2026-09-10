@extends('layouts.control')
@section('title', __($membership->exists ? 'memberships.edit' : 'memberships.new'))
@section('content')
@php($editing = $membership->exists)
@php($identityLocked = $editing && $membership->status !== 'draft')
<div class="membership-module">
    <section class="page-heading"><div><span class="eyebrow">IUOAMC / MEMBER RECORD</span><h1>{{ __($editing?'memberships.edit':'memberships.new') }}</h1><p>{{ __('memberships.form_lead') }}</p></div></section>
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
    @if($identityLocked)<div class="alert membership-notice">{{ __('memberships.contact_only') }}</div>@endif
    <form class="institutional-form" method="post" action="{{ $editing ? route('memberships.update',['locale'=>app()->getLocale(),'membership'=>$membership->id]) : route('memberships.store',['locale'=>app()->getLocale()]) }}">
        @csrf
        @if($editing) @method('put')<input type="hidden" name="lock_version" value="{{ $membership->lock_version }}"> @endif
        <section class="form-card"><header><span class="form-step">01</span><div><h2>{{ __('memberships.identity') }}</h2><p>{{ __('institutional.required_fields') }}</p></div></header>
            <div class="form-grid two-columns">
                <label class="field"><span>{{ __('memberships.organization') }} *</span>@if($editing)<input value="{{ $membership->organization->display_name }}" readonly>@else<select name="organization_id" required><option value="">{{ __('memberships.choose') }}</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)old('organization_id')===(string)$organization->id)>{{ $organization->display_name }}</option>@endforeach</select>@endif</label>
                <label class="field"><span>{{ __('memberships.type') }} *</span><input name="membership_type" required maxlength="120" value="{{ old('membership_type',$membership->membership_type) }}" @readonly($identityLocked)></label>
                <label class="field"><span>{{ __('memberships.full_name') }} *</span><input name="full_name" required maxlength="255" autocomplete="name" value="{{ old('full_name',$membership->full_name) }}" @readonly($identityLocked)></label>
                <label class="field"><span>{{ __('memberships.latin_name') }}</span><input name="latin_name" maxlength="255" dir="ltr" value="{{ old('latin_name',$membership->latin_name) }}" @readonly($identityLocked)></label>
                <label class="field"><span>{{ __('memberships.professional_title') }}</span><input name="professional_title" maxlength="160" value="{{ old('professional_title',$membership->professional_title) }}" @readonly($identityLocked)></label>
                <label class="field"><span>{{ __('memberships.country') }}</span><input name="country_code" maxlength="2" pattern="[A-Za-z]{2}" dir="ltr" placeholder="GB" value="{{ old('country_code',$membership->country_code) }}" @readonly($identityLocked)><small>{{ __('memberships.country_help') }}</small></label>
                <label class="field"><span>{{ __('memberships.language') }} *</span><select name="preferred_locale" @disabled($identityLocked)>@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $locale=>$label)<option value="{{ $locale }}" @selected(old('preferred_locale',$membership->preferred_locale)===$locale)>{{ $label }}</option>@endforeach</select>@if($identityLocked)<input type="hidden" name="preferred_locale" value="{{ $membership->preferred_locale }}">@endif</label>
            </div>
        </section>
        <section class="form-card"><header><span class="form-step">02</span><div><h2>{{ __('memberships.contact') }}</h2><p>{{ __('memberships.contact_notice') }}</p></div></header>
            <div class="form-grid two-columns">
                <label class="field"><span>{{ __('memberships.email') }}</span><input type="email" name="email" dir="ltr" maxlength="254" autocomplete="email" value="{{ old('email',$membership->email) }}"></label>
                <label class="field"><span>{{ __('memberships.phone') }}</span><input type="tel" name="phone" dir="ltr" maxlength="40" autocomplete="tel" value="{{ old('phone',$membership->phone) }}"></label>
            </div>
            <label class="field"><span>{{ __('memberships.notes') }}</span><textarea name="private_notes" rows="4" maxlength="5000">{{ old('private_notes',$membership->private_notes) }}</textarea></label>
        </section>
        <div class="form-footer"><p>{{ __('memberships.draft_notice') }}</p><div><a class="secondary-action" href="{{ route('memberships.index',['locale'=>app()->getLocale()]) }}">{{ __('institutional.cancel') }}</a><button type="submit" class="primary-action">{{ __('memberships.save') }}</button></div></div>
    </form>
</div>
@endsection
