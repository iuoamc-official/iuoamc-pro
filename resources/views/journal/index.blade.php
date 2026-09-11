@extends('layouts.journal')

@section('title', $journal->localized('name'))
@section('content')
    <section class="journal-hero">
        <div class="public-container journal-hero-grid">
            <div>
                <span class="journal-kicker">MCIJ · SCHOLARLY PUBLISHING</span>
                <h1>{{ $journal->localized('name') }}</h1>
                <p>{{ $journal->localized('description') }}</p>
                <div class="journal-assurance">
                    <span>{{ __('journal.assurance.peer_review') }}</span>
                    <span>{{ __('journal.assurance.versioning') }}</span>
                    <span>AR · EN · FR</span>
                </div>
            </div>
            <aside>
                <span>{{ __('journal.scope') }}</span>
                <strong>{{ __('journal.scope_title') }}</strong>
                <p>{{ __('journal.scope_text') }}</p>
                <div class="journal-hero-actions"><a href="{{ route('journal.public.submissions.create', ['locale' => app()->getLocale()]) }}">{{ __('journal.submit_manuscript') }} →</a><a href="{{ route('journal.public.author-guidelines', ['locale' => app()->getLocale()]) }}">{{ __('journal.author_guidelines') }} ↗</a></div>
            </aside>
        </div>
    </section>

    <section class="journal-catalog public-container">
        <header class="journal-section-heading">
            <div><span>{{ __('journal.catalog') }}</span><h2>{{ __('journal.latest_publications') }}</h2></div>
            <form method="get" action="{{ route('journal.public.index', ['locale' => app()->getLocale()]) }}" role="search">
                @if($filters['type'] ?? null)<input type="hidden" name="type" value="{{ $filters['type'] }}">@endif
                <label class="sr-only" for="journal-search">{{ __('journal.search') }}</label>
                <input id="journal-search" name="q" maxlength="160" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('journal.search_placeholder') }}">
                <button type="submit">{{ __('journal.search') }}</button>
            </form>
        </header>

        <div class="journal-type-switch">
            <a class="{{ empty($filters['type']) ? 'active' : '' }}" href="{{ route('journal.public.index', ['locale' => app()->getLocale()]) }}">{{ __('journal.all_publications') }}</a>
            <a class="{{ ($filters['type'] ?? null) === 'peer_reviewed_research' ? 'active' : '' }}" href="{{ route('journal.public.index', ['locale' => app()->getLocale(), 'type' => 'peer_reviewed_research']) }}">{{ __('journal.types.peer_reviewed_research') }}</a>
            <a class="{{ ($filters['type'] ?? null) === 'professional_article' ? 'active' : '' }}" href="{{ route('journal.public.index', ['locale' => app()->getLocale(), 'type' => 'professional_article']) }}">{{ __('journal.types.professional_article') }}</a>
        </div>

        <div class="journal-article-grid">
            @forelse($articles as $article)
                @php($translation = $article->translation())
                <article class="journal-card">
                    <div class="journal-card-meta">
                        <span class="journal-type journal-type-{{ $article->type }}">{{ __('journal.types.'.$article->type) }}</span>
                        <bdi dir="ltr">{{ $article->article_code }}</bdi>
                    </div>
                    <h3><a href="{{ route('journal.public.articles.show', ['locale' => app()->getLocale(), 'article' => $article]) }}">{{ $translation?->title }}</a></h3>
                    <p>{{ \Illuminate\Support\Str::limit($translation?->abstract, 230) }}</p>
                    <div class="journal-byline">
                        <span>{{ $article->authors->pluck('name')->join(' · ') }}</span>
                        <time datetime="{{ $article->published_at?->toDateString() }}">{{ $article->published_at?->format('Y-m-d') }}</time>
                    </div>
                    <a class="journal-read" href="{{ route('journal.public.articles.show', ['locale' => app()->getLocale(), 'article' => $article]) }}">{{ __('journal.read_article') }} →</a>
                </article>
            @empty
                <div class="journal-empty"><strong>{{ __('journal.no_publications') }}</strong><p>{{ __('journal.no_publications_text') }}</p></div>
            @endforelse
        </div>
        {{ $articles->links() }}
    </section>
@endsection
