@extends('layouts.journal')
@section('title', __('journal.policies'))
@section('content')
<section class="journal-page-head"><div class="public-container"><span>MCIJ / EDITORIAL GOVERNANCE</span><h1>{{ __('journal.policies') }}</h1><p>{{ __('journal.policies_intro') }}</p></div></section>
<section class="public-container journal-policies">
    @foreach(['separation','peer_review','originality_policy','ai_policy','ethics_policy','authorship_policy','conflicts_policy','data_availability_policy','misconduct_policy','appeals_policy','corrections_policy','retraction_policy','privacy_policy','preservation_policy','licensing_policy','fees_policy','doi_policy'] as $policy)
        <article><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h2>{{ __('journal.policy.'.$policy.'.title') }}</h2><p>{{ __('journal.policy.'.$policy.'.body') }}</p></div></article>
    @endforeach
    <article><span>18</span><div><h2>{{ __('journal.publication_information') }}</h2><p>{{ __('journal.publication_information_text', ['frequency'=>__('journal.frequencies.'.($journal->setting('publication_frequency') ?? 'unconfigured')),'fees'=>__('journal.fee_policies.'.($journal->setting('fee_policy') ?? 'unconfigured')),'email'=>$journal->setting('contact_email') ?? __('journal.readiness.not_configured')]) }}</p></div></article>
</section>
@endsection
