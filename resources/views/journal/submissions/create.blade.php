@extends('layouts.journal')
@section('title', __('journal.submit_manuscript'))
@section('description', __('journal.submission_intro'))
@push('metadata')<meta name="robots" content="noindex,nofollow,noarchive">@endpush
@section('content')
<section class="journal-page-head"><div class="public-container"><span>MCIJ · SUBMISSION</span><h1>{{ __('journal.submit_manuscript') }}</h1><p>{{ __('journal.submission_intro') }}</p></div></section>
<div class="public-container journal-submission-shell">
    <aside><strong>{{ __('journal.submission_security') }}</strong><p>{{ __('journal.submission_security_text') }}</p><ul><li>{{ __('journal.submission_check_file') }}</li><li>{{ __('journal.submission_check_metadata') }}</li><li>{{ __('journal.submission_check_declarations') }}</li></ul><a href="{{ route('journal.public.author-guidelines',['locale'=>app()->getLocale()]) }}">{{ __('journal.author_guidelines') }} →</a></aside>
    <form class="journal-submission-form" method="post" enctype="multipart/form-data" action="{{ route('journal.public.submissions.store',['locale'=>app()->getLocale()]) }}">@csrf
        @if($errors->any())<div class="journal-form-error" role="alert">{{ $errors->first() }}</div>@endif
        <label class="journal-honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
        <fieldset><legend>01 · {{ __('journal.manuscript_identity') }}</legend><div class="journal-form-grid">
            <label><span>{{ __('journal.type') }} *</span><select name="type" required>@foreach(\App\Models\JournalArticle::TYPES as $type)<option value="{{ $type }}" @selected(old('type')===$type)>{{ __('journal.types.'.$type) }}</option>@endforeach</select></label>
            <label><span>{{ __('journal.primary_language') }} *</span><select name="primary_locale" required>@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $code=>$label)<option value="{{ $code }}" @selected(old('primary_locale','ar')===$code)>{{ $label }}</option>@endforeach</select></label>
            <label class="wide"><span>{{ __('journal.article_title') }} *</span><input name="title" value="{{ old('title') }}" maxlength="500" required></label>
            <label class="wide"><span>{{ __('journal.abstract') }} *</span><textarea name="abstract" rows="7" maxlength="12000" required>{{ old('abstract') }}</textarea></label>
            <label class="wide"><span>{{ __('journal.keywords') }} *</span><input name="keywords" value="{{ old('keywords') }}" maxlength="1200" required><small>{{ __('journal.comma_separated') }}</small></label>
            <label class="wide journal-file-field"><span>{{ __('journal.manuscript_file') }} *</span><input type="file" name="manuscript" accept=".pdf,.doc,.docx" required><small>{{ __('journal.manuscript_file_help') }}</small></label>
        </div></fieldset>
        <fieldset><legend>02 · {{ __('journal.corresponding_author') }}</legend><div class="journal-form-grid">
            <label><span>{{ __('journal.author_name') }} *</span><input name="author_name" value="{{ old('author_name') }}" maxlength="255" required></label>
            <label><span>{{ __('journal.author_email') }} *</span><input type="email" dir="ltr" name="author_email" value="{{ old('author_email') }}" maxlength="254" required></label>
            <label><span>{{ __('journal.affiliation') }}</span><input name="affiliation" value="{{ old('affiliation') }}" maxlength="255"></label>
            <label><span>ORCID</span><input dir="ltr" name="orcid" value="{{ old('orcid') }}" placeholder="0000-0000-0000-000X"></label>
            <label><span>{{ __('journal.country_code') }}</span><input dir="ltr" name="country_code" value="{{ old('country_code') }}" maxlength="2" placeholder="GB"></label>
        </div></fieldset>
        <fieldset><legend>03 · {{ __('journal.disclosures') }}</legend><div class="journal-form-grid single">
            @foreach(['conflicts','funding','ethics'] as $field)<label><span>{{ __('journal.'.$field) }} *</span><textarea name="{{ $field }}" rows="3" maxlength="3000" required>{{ old($field) }}</textarea></label>@endforeach
        </div></fieldset>
        <fieldset><legend>04 · {{ __('journal.author_declaration') }}</legend><div class="journal-consents">
            <label><input type="checkbox" name="authorship_confirmed" value="1" required @checked(old('authorship_confirmed'))><span>{{ __('journal.authorship_confirmation') }}</span></label>
            <label><input type="checkbox" name="originality_confirmed" value="1" required @checked(old('originality_confirmed'))><span>{{ __('journal.originality_confirmation') }}</span></label>
            <label><input type="checkbox" name="privacy_confirmed" value="1" required @checked(old('privacy_confirmed'))><span>{{ __('journal.privacy_confirmation') }}</span></label>
        </div></fieldset>
        <button class="journal-submit-button" type="submit">{{ __('journal.submit_for_screening') }}</button>
    </form>
</div>
@endsection
