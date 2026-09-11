@extends('layouts.control')
@php($editing=$article->exists)
@section('title', __($editing ? 'journal.edit_article' : 'journal.new_article'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-journal-1.0.0.css') }}">@endpush
@section('content')
<div class="journal-control">
@include('control.journal._nav')
<section class="page-heading"><div><span class="eyebrow">MCIJ / EDITORIAL RECORD</span><h1>{{ __($editing ? 'journal.edit_article' : 'journal.new_article') }}</h1><p>{{ __('journal.form_intro') }}</p></div></section>
@if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
<form class="institutional-form" method="post" action="{{ $editing ? route('journal.control.articles.update',['locale'=>app()->getLocale(),'article'=>$article]) : route('journal.control.articles.store',['locale'=>app()->getLocale()]) }}">@csrf @if($editing)@method('put')<input type="hidden" name="lock_version" value="{{ $article->lock_version }}">@endif
    <section class="form-card"><header><span class="form-step">01</span><div><h2>{{ __('journal.classification') }}</h2><p>{{ __('journal.classification_help') }}</p></div></header><div class="form-grid two-columns">
        <label class="field"><span>{{ __('journal.type') }} *</span><select name="type" required>@foreach(\App\Models\JournalArticle::TYPES as $type)<option value="{{ $type }}" @selected(old('type',$article->type)===$type)>{{ __('journal.types.'.$type) }}</option>@endforeach</select><small>{{ __('journal.type_warning') }}</small></label>
        <label class="field"><span>{{ __('journal.issue') }}</span><select name="journal_issue_id"><option value="">{{ __('journal.unassigned_issue') }}</option>@foreach($issues as $issue)<option value="{{ $issue->id }}" @selected((string)old('journal_issue_id',$article->journal_issue_id)===(string)$issue->id)>V{{ $issue->volume }} / N{{ $issue->number }} — {{ $issue->localized('title') }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('journal.primary_language') }} *</span><select name="primary_locale">@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $locale=>$label)<option value="{{ $locale }}" @selected(old('primary_locale',$article->primary_locale)===$locale)>{{ $label }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('journal.license') }} *</span><select name="license">@foreach(['all-rights-reserved','CC-BY-4.0','CC-BY-NC-4.0'] as $license)<option value="{{ $license }}" @selected(old('license',$article->license)===$license)>{{ $license }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('journal.received') }}</span><input type="date" name="received_at" value="{{ old('received_at',$article->received_at?->format('Y-m-d')) }}"></label>
    </div></section>
    <section class="form-card"><header><span class="form-step">02</span><div><h2>{{ __('journal.corresponding_author') }}</h2><p>{{ __('journal.author_help') }}</p></div></header>@php($author=$article->authors->first())<div class="form-grid two-columns">
        <label class="field"><span>{{ __('journal.author_name') }} *</span><input name="author[name]" required maxlength="255" value="{{ old('author.name',$author?->name) }}"></label>
        <label class="field"><span>{{ __('journal.author_latin_name') }}</span><input name="author[latin_name]" dir="ltr" maxlength="255" value="{{ old('author.latin_name',$author?->latin_name) }}"></label>
        <label class="field"><span>{{ __('journal.author_email') }}</span><input type="email" name="author[email]" dir="ltr" maxlength="254" value="{{ old('author.email',$author?->email) }}"></label>
        <label class="field"><span>ORCID</span><input name="author[orcid]" dir="ltr" placeholder="0000-0000-0000-000X" value="{{ old('author.orcid',$author?->orcid) }}"></label>
        <label class="field"><span>{{ __('journal.affiliation') }}</span><input name="author[affiliation_name]" maxlength="255" value="{{ old('author.affiliation_name',$author?->pivot?->affiliation_name) }}"></label>
        <label class="field"><span>ROR URL</span><input type="url" name="author[affiliation_ror]" dir="ltr" maxlength="255" value="{{ old('author.affiliation_ror',$author?->pivot?->affiliation_ror) }}"></label>
    </div></section>
    @foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $locale=>$label)@php($translation=$article->translations->firstWhere('locale',$locale))
    <section class="form-card journal-language-card" dir="{{ $locale==='ar'?'rtl':'ltr' }}"><header><span class="form-step">{{ strtoupper($locale) }}</span><div><h2>{{ $label }}</h2><p>{{ __('journal.language_parity') }}</p></div></header><div class="form-grid two-columns">
        <label class="field span-two"><span>{{ __('journal.article_title') }} *</span><input name="translations[{{ $locale }}][title]" required maxlength="500" value="{{ old('translations.'.$locale.'.title',$translation?->title) }}"></label>
        <label class="field span-two"><span>{{ __('journal.subtitle') }}</span><input name="translations[{{ $locale }}][subtitle]" maxlength="500" value="{{ old('translations.'.$locale.'.subtitle',$translation?->subtitle) }}"></label>
        <label class="field span-two"><span>{{ __('journal.abstract') }} *</span><textarea name="translations[{{ $locale }}][abstract]" rows="6" required maxlength="12000">{{ old('translations.'.$locale.'.abstract',$translation?->abstract) }}</textarea></label>
        <label class="field span-two"><span>{{ __('journal.body') }} *</span><textarea name="translations[{{ $locale }}][body]" rows="14" required maxlength="200000">{{ old('translations.'.$locale.'.body',$translation?->body) }}</textarea></label>
        <label class="field span-two"><span>{{ __('journal.keywords') }} *</span><input name="translations[{{ $locale }}][keywords]" required maxlength="1200" value="{{ old('translations.'.$locale.'.keywords',implode(', ',$translation?->keywords ?? [])) }}"><small>{{ __('journal.comma_separated') }}</small></label>
        <label class="field span-two"><span>{{ __('journal.references') }}</span><textarea name="translations[{{ $locale }}][references]" rows="7" maxlength="50000">{{ old('translations.'.$locale.'.references',implode("\n",$translation?->references ?? [])) }}</textarea><small>{{ __('journal.reference_per_line') }}</small></label>
    </div></section>@endforeach
    <section class="form-card"><header><span class="form-step">03</span><div><h2>{{ __('journal.disclosures') }}</h2><p>{{ __('journal.disclosures_help') }}</p></div></header><div class="form-grid">@foreach(['conflicts','funding','ethics'] as $field)<label class="field"><span>{{ __('journal.'.$field) }}</span><textarea name="declarations[{{ $field }}]" rows="3" maxlength="3000">{{ old('declarations.'.$field,$article->declarations[$field] ?? '') }}</textarea></label>@endforeach</div></section>
    <div class="form-footer"><p>{{ __('journal.draft_notice') }}</p><div><a class="secondary-action" href="{{ route('journal.control.articles.index',['locale'=>app()->getLocale()]) }}">{{ __('institutional.cancel') }}</a><button class="primary-action" type="submit">{{ __('journal.save_draft') }}</button></div></div>
</form></div>
@endsection
