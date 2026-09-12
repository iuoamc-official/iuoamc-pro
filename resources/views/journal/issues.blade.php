@extends('layouts.journal')
@section('title', __('journal.issues'))
@section('content')
<section class="journal-page-head"><div class="public-container"><span>MCIJ · {{ __('journal.kickers.archive') }}</span><h1>{{ __('journal.issues') }}</h1><p>{{ __('journal.issues_intro') }}</p></div></section>
<section class="public-container journal-issue-grid">
    @forelse($issues as $issue)
        <a class="journal-issue-card" href="{{ route('journal.public.issues.show', ['locale' => app()->getLocale(), 'issue' => $issue->slug]) }}">
            <span>{{ __('journal.volume') }} {{ $issue->volume }} · {{ __('journal.issue') }} {{ $issue->number }}</span>
            <strong>{{ $issue->localized('title') }}</strong><p>{{ $issue->localized('description') }}</p><b>{{ trans_choice('journal.article_count', $issue->articles_count, ['count' => $issue->articles_count]) }}</b>
        </a>
    @empty<div class="journal-empty"><strong>{{ __('journal.no_issues') }}</strong></div>@endforelse
    {{ $issues->links() }}
</section>
@endsection
