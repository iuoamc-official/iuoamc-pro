@extends('layouts.control')
@section('title', __('journal.title'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-journal-1.0.0.css') }}">@endpush
@section('content')
<div class="journal-control">
    @include('control.journal._nav')
    <section class="page-heading"><div><span class="eyebrow">MCIJ / EDITORIAL WORKSPACE</span><h1>{{ __('journal.title') }}</h1><p>{{ __('journal.control_intro') }}</p></div>@if(auth()->user()->canDo('journal.manage'))<a class="primary-action" href="{{ route('journal.control.articles.create',['locale'=>app()->getLocale()]) }}">{{ __('journal.new_article') }}</a>@endif</section>
    <div class="journal-stat-grid">@foreach(['submitted','under_review','revision_required','ready_to_publish','published'] as $status)<div><span>{{ __('journal.statuses.'.$status) }}</span><strong>{{ number_format($counts[$status] ?? 0) }}</strong></div>@endforeach</div>
    <section class="data-card">
        <div class="data-card-header"><div><h2>{{ __('journal.editorial_registry') }}</h2><p>{{ __('journal.editorial_registry_help') }}</p></div></div>
        <form class="journal-filter" method="get"><input name="q" maxlength="160" value="{{ request('q') }}" placeholder="{{ __('journal.search_placeholder') }}"><select name="type"><option value="">{{ __('journal.all_types') }}</option>@foreach(\App\Models\JournalArticle::TYPES as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ __('journal.types.'.$type) }}</option>@endforeach</select><select name="status"><option value="">{{ __('journal.all_statuses') }}</option>@foreach(['draft','submitted','initial_screening','under_review','revision_required','accepted','copyediting','typesetting','ready_to_publish','published','retracted'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ __('journal.statuses.'.$status) }}</option>@endforeach</select><button class="secondary-action">{{ __('journal.filter') }}</button></form>
        <div class="table-wrap"><table><thead><tr><th>{{ __('journal.article') }}</th><th>{{ __('journal.type') }}</th><th>{{ __('journal.author') }}</th><th>{{ __('journal.status') }}</th><th>{{ __('journal.issue') }}</th><th>{{ __('journal.action') }}</th></tr></thead><tbody>
        @forelse($articles as $article)@php($translation=$article->translation())<tr><td><strong>{{ $translation?->title }}</strong><small class="table-note"><bdi dir="ltr">{{ $article->article_code }}</bdi></small></td><td><span class="journal-type journal-type-{{ $article->type }}">{{ __('journal.types.'.$article->type) }}</span></td><td>{{ $article->authors->pluck('name')->join(', ') }}</td><td><span class="status-badge status-{{ $article->status }}">{{ __('journal.statuses.'.$article->status) }}</span></td><td>{{ $article->issue ? 'V'.$article->issue->volume.' / N'.$article->issue->number : '—' }}</td><td><a class="table-action" href="{{ route('journal.control.articles.show',['locale'=>app()->getLocale(),'article'=>$article]) }}">{{ __('journal.open') }}</a></td></tr>
        @empty<tr><td colspan="6">{{ __('journal.no_records') }}</td></tr>@endforelse
        </tbody></table></div>
        @include('control.organizations._pagination',['paginator'=>$articles])
    </section>
</div>
@endsection
