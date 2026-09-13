@extends('layouts.journal')
@section('title', __('journal.submit_manuscript'))
@section('description', __('journal.submission_intro'))
@php($creditRoles = \App\Support\CreditRoles::ALL)
@push('metadata')<meta name="robots" content="noindex,nofollow,noarchive">@endpush
@section('content')
<section class="journal-page-head"><div class="public-container"><span>MCIJ · {{ __('journal.kickers.submission') }}</span><h1>{{ __('journal.submit_manuscript') }}</h1><p>{{ __('journal.submission_intro') }}</p></div></section>
<div class="public-container journal-submission-shell">
    <aside><strong>{{ __('journal.submission_security') }}</strong><p>{{ __('journal.submission_security_text') }}</p><ul><li>{{ __('journal.submission_check_file') }}</li><li>{{ __('journal.submission_check_metadata') }}</li><li>{{ __('journal.submission_check_declarations') }}</li></ul><a href="{{ route('journal.public.author-guidelines',['locale'=>app()->getLocale()]) }}">{{ __('journal.author_guidelines') }} →</a></aside>
    <form class="journal-submission-form" method="post" enctype="multipart/form-data" action="{{ route('journal.public.submissions.store',['locale'=>app()->getLocale()]) }}">@csrf
        @if($errors->any())<div class="journal-form-error" role="alert">{{ $errors->first() }}</div>@endif
        <label class="journal-honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
        <fieldset><legend>01 · {{ __('journal.manuscript_identity') }}</legend><div class="journal-form-grid">
            <div class="journal-fixed-class"><span>{{ __('journal.type') }}</span><strong>{{ __('journal.types.peer_reviewed_research') }}</strong><small>{{ __('journal.research_submission_only') }}</small><input type="hidden" name="type" value="peer_reviewed_research"></div>
            <label><span>{{ __('journal.primary_language') }} *</span><select name="primary_locale" required>@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $code=>$label)<option value="{{ $code }}" @selected(old('primary_locale','ar')===$code)>{{ $label }}</option>@endforeach</select></label>
            <label class="wide"><span>{{ __('journal.article_title') }} *</span><input name="title" value="{{ old('title') }}" maxlength="500" required></label>
            <label class="wide"><span>{{ __('journal.abstract') }} *</span><textarea name="abstract" rows="7" maxlength="12000" required>{{ old('abstract') }}</textarea></label>
            <label class="wide"><span>{{ __('journal.keywords') }} *</span><input name="keywords" value="{{ old('keywords') }}" maxlength="1200" required><small>{{ __('journal.comma_separated') }}</small></label>
            <label class="wide journal-file-field"><span>{{ __('journal.manuscript_file') }} *</span><input type="file" name="manuscript" accept=".pdf,.doc,.docx" required><small>{{ __('journal.manuscript_file_help') }}</small></label>
        </div></fieldset>
        <fieldset><legend>02 · {{ __('journal.corresponding_author') }}</legend><p>{{ __('journal.corresponding_author_note') }}</p><div class="journal-form-grid">
            <label><span>{{ __('journal.author_name') }} *</span><input name="author_name" value="{{ old('author_name') }}" maxlength="255" required></label>
            <label><span>{{ __('journal.latin_name') }}</span><input dir="ltr" name="author_latin_name" value="{{ old('author_latin_name') }}" maxlength="255"></label>
            <label><span>{{ __('journal.author_email') }} *</span><input type="email" dir="ltr" name="author_email" value="{{ old('author_email') }}" maxlength="254" required></label>
            <label><span>{{ __('journal.affiliation') }}</span><input name="affiliation" value="{{ old('affiliation') }}" maxlength="255"></label>
            <label><span>{{ __('journal.affiliation_ror') }}</span><input type="url" dir="ltr" name="author_affiliation_ror" value="{{ old('author_affiliation_ror') }}" maxlength="255" placeholder="https://ror.org/..."></label>
            <label><span>ORCID</span><input dir="ltr" name="orcid" value="{{ old('orcid') }}" placeholder="0000-0000-0000-000X"></label>
            <label><span>{{ __('journal.country_code') }}</span><input dir="ltr" name="country_code" value="{{ old('country_code') }}" maxlength="2" placeholder="GB"></label>
            <label class="wide"><span>{{ __('journal.credit_roles') }}</span><select name="author_contribution_roles[]" multiple size="7">@foreach($creditRoles as $role)<option value="{{ $role }}" @selected(in_array($role, old('author_contribution_roles',['writing_original_draft']), true))>{{ __('journal.credit.'.$role) }}</option>@endforeach</select></label>
        </div></fieldset>
        <fieldset><legend>03 · {{ __('journal.additional_authors') }}</legend><div id="journal-coauthors">@foreach(old('coauthors',[]) as $index=>$coauthor)@include('journal.submissions._coauthor',['index'=>$index,'coauthor'=>$coauthor,'creditRoles'=>$creditRoles])@endforeach</div><button class="journal-add-author" type="button" id="journal-add-author">{{ __('journal.add_author') }}</button></fieldset>
        <template id="journal-coauthor-template">@include('journal.submissions._coauthor',['index'=>'__INDEX__','coauthor'=>[],'creditRoles'=>$creditRoles])</template>
        <fieldset><legend>04 · {{ __('journal.disclosures') }}</legend><div class="journal-form-grid single">
            @foreach(['conflicts','funding','ethics'] as $field)<label><span>{{ __('journal.'.$field) }} *</span><textarea name="{{ $field }}" rows="3" maxlength="3000" required>{{ old($field) }}</textarea></label>@endforeach
        </div></fieldset>
        <fieldset><legend>05 · {{ __('journal.author_declaration') }}</legend><div class="journal-consents">
            <label><input type="checkbox" name="authorship_confirmed" value="1" required @checked(old('authorship_confirmed'))><span>{{ __('journal.authorship_confirmation') }}</span></label>
            <label><input type="checkbox" name="originality_confirmed" value="1" required @checked(old('originality_confirmed'))><span>{{ __('journal.originality_confirmation') }}</span></label>
            <label><input type="checkbox" name="privacy_confirmed" value="1" required @checked(old('privacy_confirmed'))><span>{{ __('journal.privacy_confirmation') }}</span></label>
        </div></fieldset>
        <button class="journal-submit-button" type="submit">{{ __('journal.submit_for_screening') }}</button>
    </form>
</div>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.addEventListener('DOMContentLoaded',()=>{const box=document.getElementById('journal-coauthors'),tpl=document.getElementById('journal-coauthor-template'),add=document.getElementById('journal-add-author');let i=box.children.length;add?.addEventListener('click',()=>{if(i>=19)return;box.insertAdjacentHTML('beforeend',tpl.innerHTML.replaceAll('__INDEX__',String(i++)));});box?.addEventListener('click',event=>{const button=event.target.closest('[data-remove-coauthor]');if(button)button.closest('.journal-coauthor')?.remove();});});
</script>
@endsection
