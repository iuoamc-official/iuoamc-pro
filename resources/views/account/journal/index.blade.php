@extends('layouts.account')
@section('title', __('account.research_portal'))
@section('content')
<section class="account-hero">
    <div><span>MCIJ / {{ __('account.research_portal') }}</span><h1>{{ __('account.my_submissions') }}</h1><p>{{ __('account.research_portal_intro') }}</p></div>
    <a class="button secondary" href="{{ route('journal.public.submissions.create',['locale'=>app()->getLocale()]) }}">{{ __('account.new_research_submission') }}</a>
</section>

<section class="account-card">
    <header><div><span>01</span><h2>{{ __('account.link_existing_submission') }}</h2></div><small>{{ __('account.private_verified_access') }}</small></header>
    <p class="account-help">{{ __('account.link_submission_help') }}</p>
    <form method="post" action="{{ route('account.journal.claims.store',['locale'=>app()->getLocale()]) }}" class="account-form claim-form">
        @csrf
        <label><span>{{ __('journal.submission_code') }}</span><input name="submission_code" dir="ltr" maxlength="80" required value="{{ old('submission_code') }}"></label>
        <label><span>{{ __('journal.tracking_token') }}</span><input name="tracking_token" dir="ltr" minlength="64" maxlength="64" required autocomplete="off"></label>
        <div class="form-action"><button class="button" type="submit">{{ __('account.link_submission') }}</button></div>
    </form>
</section>

<section class="account-card">
    <header><div><span>02</span><h2>{{ __('account.my_submissions') }}</h2></div><small>{{ $submissions->total() }}</small></header>
    @forelse($submissions as $submission)
        <article class="record-row">
            <div><strong>{{ $submission->title }}</strong><span>{{ __('journal.submission_statuses.'.$submission->status) }} · {{ $submission->received_at->format('Y-m-d') }}</span><small><bdi dir="ltr">{{ $submission->submission_code }}</bdi>@if($submission->convertedArticle) · <bdi dir="ltr">{{ $submission->convertedArticle->article_code }}</bdi>@endif</small></div>
            <div class="record-actions"><a href="{{ route('account.journal.submissions.show',['locale'=>app()->getLocale(),'submission'=>$submission]) }}">{{ __('account.open_submission') }}</a></div>
        </article>
    @empty
        <div class="empty-state"><p>{{ __('account.no_research_submissions') }}</p></div>
    @endforelse
    {{ $submissions->links() }}
</section>
@endsection
