@extends('layouts.control')
@section('title', __('memberships.correct_identity'))
@section('content')
<div class="membership-module">
    <section class="page-heading">
        <div><span class="eyebrow">IUOAMC / MEMBER CORRECTION</span><h1>{{ __('memberships.correct_identity') }}</h1><p><bdi dir="ltr">{{ $membership->membership_number }}</bdi></p></div>
        <a class="secondary-action" href="{{ route('memberships.show',['locale'=>app()->getLocale(),'membership'=>$membership->id]) }}">{{ __('memberships.back') }}</a>
    </section>
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="alert"><strong>{{ __('memberships.correction_protection_title') }}</strong><p>{{ __('memberships.correction_protection_notice') }}</p></div>
    <section class="form-card">
        <form method="post" action="{{ route('memberships.correction.store',['locale'=>app()->getLocale(),'membership'=>$membership->id]) }}">
            @csrf
            <input type="hidden" name="lock_version" value="{{ $membership->lock_version }}">
            <label>{{ __('memberships.full_name') }}<input required name="full_name" maxlength="255" value="{{ old('full_name',$membership->full_name) }}"></label>
            <label>{{ __('memberships.latin_name') }}<input name="latin_name" maxlength="255" value="{{ old('latin_name',$membership->latin_name) }}"></label>
            <label>{{ __('memberships.professional_title') }}<select name="member_title_code" required><option value="">{{ __('memberships.choose_professional_title') }}</option>@foreach($memberTitles as $code=>$label)<option value="{{ $code }}" @selected(old('member_title_code',$selectedMemberTitle)===$code)>{{ $label }}</option>@endforeach</select></label>
            <label>{{ __('memberships.correction_reason') }}<textarea required name="reason" maxlength="1500">{{ old('reason') }}</textarea></label>
            <label><input type="checkbox" required> {{ __('memberships.confirm_correction') }}</label>
            <button class="primary-action" type="submit">{{ __('memberships.save_correction') }}</button>
        </form>
    </section>
</div>
@endsection
