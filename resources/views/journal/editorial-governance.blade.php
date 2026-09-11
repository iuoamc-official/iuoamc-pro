@extends('layouts.journal')
@section('title', __('journal.editorial_governance'))
@section('description', __('journal.editorial_governance_intro'))
@section('content')
<section class="journal-page-head"><div class="public-container"><span>MCIJ · GOVERNANCE</span><h1>{{ __('journal.editorial_governance') }}</h1><p>{{ __('journal.editorial_governance_intro') }}</p></div></section>
<section class="public-container journal-governance">
    <div class="journal-governance-principles"><strong>{{ __('journal.governance_independence') }}</strong><p>{{ __('journal.governance_independence_text') }}</p><a href="{{ route('journal.public.policies',['locale'=>app()->getLocale()]) }}">{{ __('journal.read_policies') }} →</a></div>
    <div class="journal-role-grid">
        @foreach(__('journal.editorial_roles') as $role)
            <article><h2>{{ $role['title'] }}</h2><p>{{ $role['body'] }}</p></article>
        @endforeach
    </div>
    <section class="journal-board-public"><h2>{{ __('journal.editorial_board') }}</h2>
        <div class="journal-role-grid">@forelse($editorialMembers as $member)<article><span>{{ __('journal.board_roles.'.$member->role) }}</span><h3>{{ $member->name }}</h3>@if($member->localized('title'))<p>{{ $member->localized('title') }}</p>@endif @if($member->localized('affiliation'))<p>{{ $member->localized('affiliation') }}</p>@endif @if($member->orcid)<a rel="external noopener" href="https://orcid.org/{{ $member->orcid }}"><bdi dir="ltr">ORCID {{ $member->orcid }}</bdi></a>@endif</article>@empty<p class="journal-governance-note">{{ __('journal.governance_names_notice') }}</p>@endforelse</div>
    </section>
</section>
@endsection
