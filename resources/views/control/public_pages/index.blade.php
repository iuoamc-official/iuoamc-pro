@extends('layouts.control')
@section('title', __('public_site.control.title'))
@section('content')
    <section class="page-heading compact-heading">
        <div><span class="eyebrow">PUBLIC EXPERIENCE / CMS</span><h1>{{ __('public_site.control.title') }}</h1><p>{{ __('public_site.control.intro') }}</p></div>
        <div class="heading-actions"><a class="secondary-action" href="{{ route('public-content.articles.index',['locale'=>app()->getLocale()]) }}">{{ __('public_site.articles.title') }}</a><a class="secondary-action" target="_blank" rel="noopener" href="{{ route('public.home', ['locale' => app()->getLocale()]) }}">{{ __('public_site.control.preview') }}</a><a class="primary-action" href="{{ route('public-content.settings.edit', ['locale' => app()->getLocale()]) }}">{{ __('public_site.control.identity') }}</a></div>
    </section>
    <section class="form-card public-cms-assurance">
        <header><span class="form-step">CMS</span><div><h2>{{ __('public_site.control.governance_title') }}</h2><p>{{ __('public_site.control.governance_help') }}</p></div></header>
        <div class="cms-assurance-grid">
            @foreach(['languages', 'workflow', 'audit', 'security'] as $item)
                <div><span class="status-dot"></span><strong>{{ __('public_site.control.assurance.'.$item) }}</strong></div>
            @endforeach
        </div>
    </section>
    <section class="data-card">
        <div class="data-card-header"><div><h2>{{ __('public_site.control.pages') }}</h2><p>{{ __('public_site.control.pages_help') }}</p></div></div>
        <div class="table-wrap"><table><thead><tr><th>{{ __('public_site.control.page') }}</th><th>{{ __('public_site.control.slug') }}</th><th>{{ __('public_site.control.status') }}</th><th>{{ __('public_site.control.revision') }}</th><th>{{ __('public_site.control.updated') }}</th><th>{{ __('public_site.control.action') }}</th></tr></thead><tbody>
            @foreach($pages as $page)
                <tr><td><strong>{{ $page->localized('title') }}</strong><small class="table-note">{{ $page->localized('navigation_label') }}</small></td><td><code>{{ $page->slug }}</code></td><td><span class="status-badge status-{{ $page->status }}">{{ __('public_site.control.'.$page->status) }}</span></td><td>V{{ $page->revision }}</td><td>{{ $page->updated_at?->format('Y-m-d H:i') }}</td><td><a class="table-action" href="{{ route('public-content.pages.edit', ['locale' => app()->getLocale(), 'public_page' => $page]) }}">{{ __('public_site.control.edit') }}</a></td></tr>
            @endforeach
        </tbody></table></div>
    </section>
@endsection
