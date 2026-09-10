<nav class="sidebar-nav" aria-label="{{ __('institutional.navigation_label') }}">
    <a href="{{ route('dashboard',['locale'=>app()->getLocale()]) }}" class="{{ request()->routeIs('dashboard')?'active':'' }}"><span class="module-sidebar-icon">@include('control.navigation._icon',['icon'=>'dashboard'])</span>{{ __('ui.dashboard') }}</a>
    @if(collect(['organizations.view','core.users.view','core.roles.view','core.audit.view'])->contains(fn($permission)=>auth()->user()->canDo($permission)))
        <a href="{{ route('governance.index',['locale'=>app()->getLocale()]) }}" class="{{ request()->routeIs('governance.*','organizations.*','users.*','roles.*','audit.*')?'active':'' }}"><span class="module-sidebar-icon">@include('control.navigation._icon',['icon'=>'governance'])</span>{{ __('institutional.governance_section') }}</a>
    @endif
    @if(auth()->user()->canDo('memberships.view'))
        <a href="{{ route('memberships.index',['locale'=>app()->getLocale()]) }}" class="{{ request()->routeIs('memberships.*')?'active':'' }}"><span class="module-sidebar-icon">@include('control.navigation._icon',['icon'=>'memberships'])</span>{{ __('memberships.title') }}</a>
    @endif
    {{-- IUOAMC_LEGACY_CERTIFICATE_SIDEBAR_1_0_0 --}}
    @if(auth()->user()?->status === 'active' && auth()->user()->hasRole('super-admin'))
        <a href="{{ route('legacy-certificates.index',['locale'=>app()->getLocale()]) }}" class="{{ request()->routeIs('legacy-certificates.*')?'active':'' }}"><span class="module-sidebar-icon">@include('control.navigation._icon',['icon'=>'legacy-certificates'])</span>{{ __('legacy_certificates.title') }}</a>
    @endif
    {{-- IUOAMC_PRO_CERTIFICATE_SIDEBAR_1_0_0 --}}
    @if(auth()->user()?->status === 'active' && auth()->user()->canDo('certificates.view'))
        <a href="{{ route('certificates.index',['locale'=>app()->getLocale()]) }}" class="{{ request()->routeIs('certificates.*')?'active':'' }}"><span class="module-sidebar-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M6 3h12v12H6zM9 7h6M9 10h4"/><circle cx="12" cy="16" r="3"/><path d="m10 18-1 4 3-2 3 2-1-4"/></svg></span>{{ __('certificates.title') }}</a>
    @endif

    {{-- IUOAMC_WICP_SIDEBAR_1_0_0_BEGIN --}}
    @if(\Illuminate\Support\Facades\Route::has('wicp.index') && auth()->check() && auth()->user()->canDo('wicp.view'))
        <a href="{{ route('wicp.index', ['locale' => app()->getLocale()]) }}" class="{{ request()->routeIs('wicp.*') ? 'active' : '' }}" @if(request()->routeIs('wicp.*')) aria-current="page" @endif data-iuoamc-wicp-sidebar="1">
            <span class="module-sidebar-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 3 4.5 6v5.5c0 4.3 2.8 7.6 7.5 9.5 4.7-1.9 7.5-5.2 7.5-9.5V6L12 3Z"/><path d="m8.5 12 2.3 2.3 4.7-4.7"/></svg></span>
            <span>{{ __('wicp.title') }}</span>
        </a>
    @endif
    {{-- IUOAMC_WICP_SIDEBAR_1_0_0_END --}}

</nav>
