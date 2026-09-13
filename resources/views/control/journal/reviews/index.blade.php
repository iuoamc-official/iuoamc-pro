@extends('layouts.control')
@section('title', __('journal.my_reviews'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-journal-1.0.0.css') }}">@endpush
@section('content')
<div class="journal-control">
    @include('control.journal._nav')
    <section class="page-heading"><div><span class="eyebrow">MCIJ / REVIEWER DESK</span><h1>{{ __('journal.my_reviews') }}</h1><p>{{ __('journal.my_reviews_intro') }}</p></div></section>
    <section class="journal-stat-grid">
        @foreach(['invited','in_progress','submitted','declined'] as $status)<div><span>{{ __('journal.review_statuses.'.$status) }}</span><strong>{{ $counts[$status] ?? 0 }}</strong></div>@endforeach
    </section>
    <section class="data-card">
        <div class="data-card-header"><div><h2>{{ __('journal.assigned_reviews') }}</h2><p>{{ __('journal.reviewer_confidentiality_notice') }}</p></div></div>
        <div class="journal-review-list">
            @forelse($reviews as $review)
                @php($translation=$review->article->translation())
                <article>
                    <header><strong>{{ __('journal.review_round',['round'=>$review->round]) }}</strong><span class="status-badge">{{ __('journal.review_statuses.'.$review->status) }}</span></header>
                    <h3>{{ $translation?->title ?? $review->article->article_code }}</h3>
                    <p><bdi dir="ltr">{{ $review->article->article_code }}</bdi>@if($review->due_at) · {{ __('journal.review_due') }}: {{ $review->due_at->format('Y-m-d') }}@if(in_array($review->status,['invited','in_progress'],true) && $review->due_at->isPast()) · <strong>{{ __('journal.overdue') }}</strong>@endif @endif</p>
                    <a class="primary-action" href="{{ route('journal.control.articles.show',['locale'=>app()->getLocale(),'article'=>$review->article]) }}">{{ __('journal.open_review') }}</a>
                </article>
            @empty <div class="journal-empty">{{ __('journal.no_reviews') }}</div>@endforelse
        </div>
        {{ $reviews->links() }}
    </section>
</div>
@endsection
