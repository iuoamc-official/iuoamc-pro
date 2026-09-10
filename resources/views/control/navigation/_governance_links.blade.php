@php
    $governanceItems = [
        ['key'=>'organizations','permission'=>'organizations.view','label'=>'institutional.organizations'],
        ['key'=>'users','permission'=>'core.users.view','label'=>'institutional.users'],
        ['key'=>'roles','permission'=>'core.roles.view','label'=>'institutional.roles'],
        ['key'=>'audit','permission'=>'core.audit.view','label'=>'institutional.audit_log'],
    ];
@endphp
<nav class="governance-icon-links {{ ($hub ?? false) ? 'is-hub' : 'is-compact' }}" aria-label="{{ __('institutional.governance_section') }}">
    @foreach($governanceItems as $item)
        @if(auth()->user()->canDo($item['permission']))
            <a class="governance-icon-link {{ request()->routeIs($item['key'].'.*')?'is-active':'' }}" href="{{ route($item['key'].'.index',['locale'=>app()->getLocale()]) }}" @if(request()->routeIs($item['key'].'.*')) aria-current="page" @endif>
                <span class="governance-icon">@include('control.navigation._icon',['icon'=>$item['key']])</span>
                <span><strong>{{ __($item['label']) }}</strong>@if($hub ?? false)<small>{{ __('memberships.governance_descriptions.'.$item['key']) }}</small>@endif</span>
            </a>
        @endif
    @endforeach
</nav>
