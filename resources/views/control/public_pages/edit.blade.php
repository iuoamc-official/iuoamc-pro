@extends('layouts.control')
@section('title', __('public_site.control.edit_page'))
@section('content')
    <section class="page-heading compact-heading"><div><span class="eyebrow">PUBLIC EXPERIENCE / {{ strtoupper($publicPage->slug) }} / V{{ $publicPage->revision }}</span><h1>{{ __('public_site.control.edit_page') }}</h1><p>{{ __('public_site.control.edit_help') }}</p></div><div class="heading-actions"><a class="secondary-action" href="{{ route('public-content.pages.index', ['locale' => app()->getLocale()]) }}">{{ __('public_site.control.back') }}</a>@if($publicPage->status === 'published')<a class="primary-action" target="_blank" rel="noopener" href="{{ $publicPage->slug === 'home' ? route('public.home', ['locale' => app()->getLocale()]) : route('public.pages.show', ['locale' => app()->getLocale(), 'public_page' => $publicPage]) }}">{{ __('public_site.control.preview') }}</a>@endif</div></section>
    @if($errors->any())<div class="alert alert-error" role="alert"><strong>{{ $errors->first() }}</strong></div>@endif
    <form class="institutional-form" method="post" action="{{ route('public-content.pages.update', ['locale' => app()->getLocale(), 'public_page' => $publicPage]) }}">
        @csrf @method('put')
        <input type="hidden" name="revision" value="{{ $publicPage->revision }}">
        @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $label)
            <section class="form-card cms-language-card" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                <header><span class="form-step">{{ strtoupper($locale) }}</span><div><h2>{{ $label }}</h2><p>{{ __('public_site.control.language_complete') }}</p></div></header>
                <div class="form-grid two-columns">
                    @foreach(['navigation_label', 'eyebrow', 'title', 'summary', 'seo_title', 'seo_description'] as $field)
                        <label class="field {{ in_array($field, ['title', 'summary', 'seo_description'], true) ? 'span-two' : '' }}"><span>{{ __('public_site.control.fields.'.$field) }}</span>@if(in_array($field, ['summary', 'seo_description'], true))<textarea name="{{ $field }}[{{ $locale }}]" rows="3" required>{{ old($field.'.'.$locale, $publicPage->{$field}[$locale] ?? '') }}</textarea>@else<input name="{{ $field }}[{{ $locale }}]" maxlength="180" required value="{{ old($field.'.'.$locale, $publicPage->{$field}[$locale] ?? '') }}">@endif @error($field.'.'.$locale)<small class="field-error">{{ $message }}</small>@enderror</label>
                    @endforeach
                    <label class="field span-two"><span>{{ __('public_site.control.fields.body') }}</span><textarea name="body[{{ $locale }}]" rows="10" required>{{ old('body.'.$locale, $publicPage->body[$locale] ?? '') }}</textarea>@error('body.'.$locale)<small class="field-error">{{ $message }}</small>@enderror</label>
                </div>
            </section>
        @endforeach
        <section class="form-card"><header><span class="form-step">PUB</span><div><h2>{{ __('public_site.control.publication') }}</h2><p>{{ __('public_site.control.publication_help') }}</p></div></header><div class="form-grid two-columns"><label class="field"><span>{{ __('public_site.control.order') }}</span><input type="number" name="navigation_order" min="0" max="1000" value="{{ old('navigation_order', $publicPage->navigation_order) }}" required></label><label class="field"><span>{{ __('public_site.control.status') }}</span><select name="status" required @disabled(!auth()->user()->canDo('public-content.publish'))><option value="draft" @selected(old('status', $publicPage->status) === 'draft')>{{ __('public_site.control.draft') }}</option><option value="published" @selected(old('status', $publicPage->status) === 'published')>{{ __('public_site.control.published') }}</option></select>@if(!auth()->user()->canDo('public-content.publish'))<input type="hidden" name="status" value="{{ $publicPage->status }}">@endif</label><label class="field checkbox-field"><input type="hidden" name="show_in_navigation" value="0"><input type="checkbox" name="show_in_navigation" value="1" @checked(old('show_in_navigation', $publicPage->show_in_navigation))><span>{{ __('public_site.control.show_navigation') }}</span></label></div></section>
        <div class="form-footer"><p><span class="status-dot"></span>{{ __('public_site.control.audit_notice') }}</p><button class="primary-action" type="submit">{{ __('public_site.control.save') }}</button></div>
    </form>
@endsection
