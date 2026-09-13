@extends('layouts.account')
@section('title', $submission->title)
@section('content')
<section class="account-hero">
    <div><span>MCIJ / <bdi dir="ltr">{{ $submission->submission_code }}</bdi></span><h1>{{ $submission->title }}</h1><p>{{ __('account.submission_workspace_intro') }}</p></div>
    <a class="button secondary" href="{{ route('account.journal.index',['locale'=>app()->getLocale()]) }}">{{ __('account.back_to_submissions') }}</a>
</section>

<section class="account-summary journal-summary">
    <article><span>{{ __('journal.status') }}</span><strong class="summary-text">{{ __('journal.submission_statuses.'.$submission->status) }}</strong></article>
    <article><span>{{ __('journal.received') }}</span><strong class="summary-text">{{ $submission->received_at->format('Y-m-d') }}</strong></article>
    <article><span>{{ __('journal.received_revisions') }}</span><strong>{{ $submission->revisions->count() }}</strong></article>
</section>

@if($submission->convertedArticle)
<section class="account-card">
    <header><div><span>01</span><h2>{{ __('journal.editorial_record') }}</h2></div><small><bdi dir="ltr">{{ $submission->convertedArticle->article_code }}</bdi></small></header>
    @forelse($submission->convertedArticle->decisions as $decision)
        @if($decision->letter)<article class="decision-row"><header><strong>{{ __('journal.statuses.'.$decision->decision) }}</strong><time>{{ $decision->issued_at->format('Y-m-d H:i') }} UTC</time></header><p>{!! nl2br(e($decision->letter)) !!}</p></article>@endif
    @empty <div class="empty-state"><p>{{ __('account.no_author_decisions') }}</p></div>@endforelse
</section>
@endif

@if($submission->convertedArticle?->status === 'revision_required')
<section class="account-card">
    <header><div><span>02</span><h2>{{ __('journal.upload_revision') }}</h2></div><small>{{ __('account.secure_private_upload') }}</small></header>
    <p class="account-help">{{ __('journal.upload_revision_help') }}</p>
    <form method="post" enctype="multipart/form-data" action="{{ route('account.journal.submissions.revisions.store',['locale'=>app()->getLocale(),'submission'=>$submission]) }}" class="account-form">
        @csrf
        <div class="form-grid">
            <label class="upload wide"><span>{{ __('journal.revised_manuscript') }}</span><input type="file" name="manuscript" accept=".pdf,.doc,.docx" required></label>
            <label class="upload wide"><span>{{ __('journal.response_letter') }}</span><input type="file" name="response_letter" accept=".pdf,.doc,.docx"></label>
            <label class="wide"><span>{{ __('journal.author_note') }}</span><textarea name="author_note" maxlength="5000" rows="5"></textarea></label>
        </div>
        <div class="form-footer"><button class="button" type="submit">{{ __('journal.submit_revision') }}</button></div>
    </form>
</section>
@endif

<section class="account-card">
    <header><div><span>03</span><h2>{{ __('journal.received_revisions') }}</h2></div></header>
    @forelse($submission->revisions as $revision)
        <article class="record-row"><div><strong>{{ __('journal.revision_number',['number'=>$revision->revision_number]) }}</strong><span>{{ $revision->status }} · {{ $revision->received_at->format('Y-m-d H:i') }} UTC</span><small><bdi dir="ltr">SHA-256: {{ $revision->file_sha256 }}</bdi></small></div></article>
    @empty <div class="empty-state"><p>{{ __('account.no_revisions') }}</p></div>@endforelse
</section>
@endsection
