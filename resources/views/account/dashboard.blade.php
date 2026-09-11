@extends('layouts.account')
@section('title', __('account.my_account'))
@section('content')
<section class="account-hero">
    <div><span>IUOAMC / SECURE ACCOUNT · {{ __('account.account_reference') }} <bdi dir="ltr">IUOAMC-U-{{ str_pad((string)auth()->id(),8,'0',STR_PAD_LEFT) }}</bdi></span><h1>{{ __('account.welcome',['name'=>auth()->user()->name]) }}</h1><p>{{ __('account.dashboard_intro') }}</p></div>
    <form method="post" action="{{ route('account.refresh',['locale'=>app()->getLocale()]) }}">@csrf<button class="button secondary" type="submit">{{ __('account.refresh_records') }}</button></form>
</section>

<section class="account-summary">
    <article><span>{{ __('account.memberships') }}</span><strong>{{ $memberships->count() }}</strong></article>
    <article><span>{{ __('account.certificates') }}</span><strong>{{ $certificates->count() }}</strong></article>
    <article><span>{{ __('account.documents') }}</span><strong>{{ $documents->count() }}</strong></article>
</section>

<section class="account-card" id="profile">
    <header><div><span>01</span><h2>{{ __('account.my_profile') }}</h2></div><small>{{ __('account.verified_email') }}</small></header>
    <form method="post" action="{{ route('account.profile.update',['locale'=>app()->getLocale()]) }}" class="account-form compact">
        @csrf @method('patch')
        <label><span>{{ __('account.name') }}</span><input name="name" maxlength="255" required value="{{ old('name',auth()->user()->name) }}"></label>
        <label><span>{{ __('account.email') }}</span><input type="email" readonly value="{{ auth()->user()->email }}"><small>{{ __('account.email_locked') }}</small></label>
        <label><span>{{ __('account.language') }}</span><select name="preferred_locale">@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $code=>$label)<option value="{{ $code }}" @selected(auth()->user()->preferred_locale===$code)>{{ $label }}</option>@endforeach</select></label>
        <div class="form-action"><button class="button" type="submit">{{ __('account.save') }}</button></div>
    </form>
</section>

<section class="account-card" id="memberships">
    <header><div><span>02</span><h2>{{ __('account.memberships') }}</h2></div><a class="button small" href="{{ route('account.membership.create',['locale'=>app()->getLocale()]) }}">{{ __('account.new_application') }}</a></header>
    @forelse($memberships as $membership)
        <article class="record-row">
            <div><strong><bdi>{{ $membership->membership_type }}</bdi></strong><span><bdi>{{ $membership->organization->display_name }}</bdi> · {{ __('account.status_'.$membership->effectiveStatus()) }}</span>@if($membership->membership_number)<small><bdi dir="ltr">{{ $membership->membership_number }}</bdi></small>@endif</div>
            <div class="record-actions">
                @if($membershipChecks[$membership->id])
                    @foreach($membership->credentials as $credential)
                        <a href="{{ route('account.memberships.credentials.download',['locale'=>app()->getLocale(),'membership'=>$membership->id,'credential'=>$credential->id,'kind'=>'card']) }}">{{ __('account.download_card') }}</a>
                        <a href="{{ route('account.memberships.credentials.download',['locale'=>app()->getLocale(),'membership'=>$membership->id,'credential'=>$credential->id,'kind'=>'certificate']) }}">{{ __('account.download_membership_certificate') }}</a>
                    @endforeach
                @else<span class="blocked">{{ __('account.integrity_blocked') }}</span>@endif
            </div>
        </article>
    @empty <div class="empty-state"><p>{{ __('account.no_memberships') }}</p><a class="button" href="{{ route('account.membership.create',['locale'=>app()->getLocale()]) }}">{{ __('account.apply_membership') }}</a></div>@endforelse
</section>

<section class="account-card" id="certificates">
    <header><div><span>03</span><h2>{{ __('account.certificates') }}</h2></div></header>
    @forelse($certificates as $certificate)
        <article class="record-row"><div><strong><bdi>{{ $certificate->certificate_title }}</bdi></strong><span><bdi>{{ $certificate->program_title }}</bdi> · {{ __('account.status_'.app(\App\Services\ProCertificateRegistry::class)->effectiveStatus($certificate)) }}</span><small><bdi dir="ltr">{{ $certificate->certificate_number }}</bdi></small></div><div class="record-actions">@if($certificateChecks[$certificate->id])<a href="{{ route('account.certificates.download',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}">{{ __('account.download_pdf') }}</a>@else<span class="blocked">{{ __('account.integrity_blocked') }}</span>@endif</div></article>
    @empty <div class="empty-state"><p>{{ __('account.no_certificates') }}</p></div>@endforelse
</section>

<section class="account-card" id="documents">
    <header><div><span>04</span><h2>{{ __('account.documents_invoices_receipts') }}</h2></div></header>
    @forelse($documents as $document)
        <article class="record-row"><div><strong><bdi>{{ $document->title }}</bdi></strong><span>{{ __('account.category_'.$document->category) }} · {{ $document->issued_at->format('Y-m-d') }}</span><small><bdi dir="ltr">{{ $document->reference ?: $document->record_uuid }}</bdi>@if($document->amount !== null) · {{ $document->amount }} {{ $document->currency }}@endif</small></div><div class="record-actions">@if($documentChecks[$document->id])<a href="{{ route('account.documents.download',['locale'=>app()->getLocale(),'document'=>$document->id]) }}">{{ __('account.download_pdf') }}</a>@else<span class="blocked">{{ __('account.integrity_blocked') }}</span>@endif</div></article>
    @empty <div class="empty-state"><p>{{ __('account.no_documents') }}</p></div>@endforelse
</section>
@endsection
