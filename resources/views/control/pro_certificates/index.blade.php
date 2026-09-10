@extends('layouts.control')
@section('title', __('certificates.title'))
@section('content')
<div class="pc-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
    <header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificates.eyebrow') }}</span><h1>{{ __('certificates.title') }}</h1><p>{{ __('certificates.index_lead') }}</p></div>
        @if(auth()->user()->canDo('certificates.manage'))<a class="pc-button pc-button-primary" href="{{ route('certificates.create',['locale'=>app()->getLocale()]) }}">{{ __('certificates.new') }} <span aria-hidden="true">+</span></a>@endif
    </header>
    @include('control.pro_certificates._tabs')
    @include('control.pro_certificates._messages')
    <div class="pc-stats"><div><span>{{ __('certificates.total') }}</span><strong>{{ number_format($stats['total']) }}</strong></div><div><span>{{ __('certificates.awaiting_review') }}</span><strong>{{ number_format($stats['review']) }}</strong></div><div><span>{{ __('certificates.issued_total') }}</span><strong>{{ number_format($stats['issued']) }}</strong></div><div><span>{{ __('certificates.archive_total') }}</span><strong>{{ number_format($stats['archived']) }}</strong></div></div>
    @php($scopeQuery = request()->except(['page', 'scope']))
    <nav class="pc-scope-tabs" aria-label="{{ __('certificates.scope') }}">
        @foreach(['current', 'archive', 'all'] as $workspaceScope)
            <a href="{{ route('certificates.index', array_merge(['locale' => app()->getLocale(), 'scope' => $workspaceScope], $scopeQuery)) }}" @if($scope === $workspaceScope) aria-current="page" @endif>{{ __('certificates.scope_'.$workspaceScope) }}</a>
        @endforeach
    </nav>
    @if($scope === 'archive')<div class="pc-notice pc-notice-warning" role="status">{{ __('certificates.archive_notice') }}</div>@endif
    <section class="pc-card">
        <form class="pc-filters" method="get" action="{{ route('certificates.index',['locale'=>app()->getLocale()]) }}">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <label class="pc-field pc-search"><span>{{ __('certificates.search') }}</span><input type="search" name="q" maxlength="120" value="{{ request('q') }}" placeholder="{{ __('certificates.search_placeholder') }}"></label>
            <label class="pc-field"><span>{{ __('certificates.organization') }}</span><select name="organization_id"><option value="">{{ __('certificates.all_organizations') }}</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)request('organization_id')===(string)$organization->id)>{{ $organization->display_name }}</option>@endforeach</select></label>
            <label class="pc-field"><span>{{ __('certificates.status') }}</span><select name="status"><option value="">{{ __('certificates.all_statuses') }}</option>@foreach(['draft','review','approved','issued','expired','revoked'] as $state)<option value="{{ $state }}" @selected(request('status')===$state)>{{ __('certificates.states.'.$state) }}</option>@endforeach</select></label>
            <div class="pc-filter-buttons"><button class="pc-button pc-button-primary" type="submit">{{ __('certificates.filter') }}</button><a class="pc-text-link" href="{{ route('certificates.index',['locale'=>app()->getLocale()]) }}">{{ __('certificates.reset') }}</a></div>
        </form>
        @if($certificates->count())
        <div class="pc-table-scroll"><table class="pc-table"><thead><tr><th scope="col">{{ __('certificates.recipient_name') }}</th><th scope="col">{{ __('certificates.organization') }}</th><th scope="col">{{ __('certificates.number') }}</th><th scope="col">{{ __('certificates.status') }}</th><th scope="col">{{ __('certificates.actions_label') }}</th></tr></thead><tbody>
            @foreach($certificates as $certificate)<tr>
                @if($checks[$certificate->id])
                    <td><strong><bdi>{{ $certificate->recipient_name }}</bdi></strong><small><bdi>{{ $certificate->program_title }}</bdi></small></td>
                    <td><bdi>{{ $certificate->organization?->display_name }}</bdi></td>
                    <td><bdi class="pc-number" dir="ltr">{{ $certificate->certificate_number ?: '—' }}</bdi></td>
                    <td><span class="pc-badge pc-state-{{ $statuses[$certificate->id] }}">{{ __('certificates.states.'.$statuses[$certificate->id]) }}</span></td>
                @else
                    <td colspan="3"><span class="pc-integrity-failed">{{ __('certificates.integrity_failed') }}</span></td><td><span class="pc-badge pc-state-unavailable">{{ __('certificates.states.unavailable') }}</span></td>
                @endif
                <td><a class="pc-text-link" href="{{ route('certificates.show',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}">{{ __('certificates.open') }}<span class="pc-sr-only"> {{ $certificate->id }}</span></a></td>
            </tr>@endforeach
        </tbody></table></div>
        @include('control.pro_certificates._pagination',['paginator'=>$certificates])
        @else
        <div class="pc-empty"><span class="pc-empty-mark" aria-hidden="true">◇</span><h2>{{ $stats['total'] ? __('certificates.no_results') : __('certificates.empty_title') }}</h2><p>{{ __('certificates.empty_text') }}</p></div>
        @endif
    </section>
</div>
@endsection
