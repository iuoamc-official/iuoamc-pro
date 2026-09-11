@extends('layouts.journal')
@section('title', __('journal.policies'))
@section('content')
<section class="journal-page-head"><div class="public-container"><span>MCIJ / EDITORIAL GOVERNANCE</span><h1>{{ __('journal.policies') }}</h1><p>{{ __('journal.policies_intro') }}</p></div></section>
<section class="public-container journal-policies">
    @foreach(['separation','peer_review','ethics_policy','authorship_policy','conflicts_policy','corrections_policy','retraction_policy','data_policy','licensing_policy','doi_policy'] as $policy)
        <article><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h2>{{ __('journal.policy.'.$policy.'.title') }}</h2><p>{{ __('journal.policy.'.$policy.'.body') }}</p></div></article>
    @endforeach
</section>
@endsection
