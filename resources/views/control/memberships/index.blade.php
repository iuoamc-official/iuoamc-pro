@extends('layouts.control')
@section('title', __('memberships.title'))
@section('content')
<div class="membership-module">
    <section class="page-heading">
        <div><span class="eyebrow">IUOAMC / MEMBERSHIPS</span><h1>{{ __('memberships.title') }}</h1><p>{{ __('memberships.lead') }}</p></div>
        @if(auth()->user()->canDo('memberships.manage'))
            <a class="secondary-action" href="{{ route('memberships.settings', ['locale'=>app()->getLocale()]) }}">⚙ {{ __('memberships.catalog_settings') }}</a>
            <a class="primary-action" href="{{ route('memberships.create', ['locale'=>app()->getLocale()]) }}">＋ {{ __('memberships.new') }}</a>
        @endif
    </section>
    <div class="membership-kpis">
        @foreach(['total','pending','active'] as $key)
            <div><span>{{ __('memberships.kpis.'.$key) }}</span><strong>{{ number_format($stats[$key]) }}</strong></div>
        @endforeach
    </div>
    <form class="filter-bar" method="get">
        <label class="filter-search"><span class="sr-only">{{ __('memberships.search') }}</span><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('memberships.search') }}"></label>
        <label><span class="sr-only">{{ __('memberships.organization') }}</span><select name="organization_id"><option value="">{{ __('memberships.all_organizations') }}</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)request('organization_id')===(string)$organization->id)>{{ $organization->display_name }}</option>@endforeach</select></label>
        <label><span class="sr-only">{{ __('memberships.status') }}</span><select name="status"><option value="">{{ __('memberships.all_statuses') }}</option>@foreach(['draft','pending','active','scheduled','expired','suspended','rejected','revoked'] as $state)<option value="{{ $state }}" @selected(request('status')===$state)>{{ __('memberships.states.'.$state) }}</option>@endforeach</select></label>
        <button class="secondary-action" type="submit">{{ __('institutional.filter') }}</button>
        <a class="text-action" href="{{ route('memberships.index',['locale'=>app()->getLocale()]) }}">{{ __('institutional.reset') }}</a>
    </form>
    <section class="data-panel"><div class="table-scroll"><table class="data-table">
        <thead><tr><th>{{ __('memberships.member') }}</th><th>{{ __('memberships.number') }}</th><th>{{ __('memberships.organization') }}</th><th>{{ __('memberships.type') }}</th><th>{{ __('memberships.status') }}</th><th>{{ __('memberships.integrity') }}</th><th><span class="sr-only">{{ __('memberships.open') }}</span></th></tr></thead>
        <tbody>@forelse($memberships as $membership)<tr>
            <td><strong class="record-title"><bdi>{{ $membership->full_name }}</bdi></strong>@if($membership->latin_name)<small><bdi dir="ltr">{{ $membership->latin_name }}</bdi></small>@endif</td>
            <td><bdi dir="ltr" class="record-code">{{ $membership->membership_number ?? __('memberships.not_issued') }}</bdi></td>
            <td>{{ $membership->organization->display_name }}</td><td>{{ $membership->membership_type }}</td>
            <td><span class="membership-badge state-{{ $membership->effectiveStatus() }}">{{ __('memberships.states.'.$membership->effectiveStatus()) }}</span></td>
            <td><span class="membership-integrity {{ $checks[$membership->id] ? 'is-valid' : 'is-invalid' }}">{{ __($checks[$membership->id] ? 'memberships.verified' : 'memberships.integrity_failed') }}</span></td>
            <td><a href="{{ route('memberships.show',['locale'=>app()->getLocale(),'membership'=>$membership->id]) }}">{{ __('memberships.open') }}</a></td>
        </tr>@empty<tr><td colspan="7"><div class="membership-empty"><span aria-hidden="true">◇</span><h2>{{ __('memberships.empty_title') }}</h2><p>{{ __('memberships.empty_text') }}</p></div></td></tr>@endforelse</tbody>
    </table></div>@include('control.organizations._pagination',['paginator'=>$memberships])</section>
</div>
@endsection
