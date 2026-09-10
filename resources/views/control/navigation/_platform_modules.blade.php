@php
    $platformModules = [];
    if (collect(['organizations.view','core.users.view','core.roles.view','core.audit.view'])->contains(fn($permission)=>auth()->user()->canDo($permission))) {
        $platformModules[] = ['key'=>'governance','route'=>'governance.index','title'=>'institutional.governance_section'];
    }
    if (auth()->user()->canDo('memberships.view')) {
        $platformModules[] = ['key'=>'memberships','route'=>'memberships.index','title'=>'memberships.title'];
    }
    // IUOAMC_LEGACY_CERTIFICATE_MODULE_1_0_0
    if (auth()->user()?->status === 'active' && auth()->user()->hasRole('super-admin')) {
        $platformModules[] = ['key'=>'legacy-certificates','route'=>'legacy-certificates.index','title'=>'legacy_certificates.title','description'=>'legacy_certificates.module_description'];
    }
    // IUOAMC_PRO_CERTIFICATE_MODULE_1_0_0
    if (auth()->user()?->status === 'active' && auth()->user()->canDo('certificates.view')) {
        $platformModules[] = ['key'=>'certificates','route'=>'certificates.index','title'=>'certificates.title','description'=>'certificates.module_description'];
    }
@endphp
<nav class="governance-icon-links is-hub platform-module-links" aria-label="{{ __('platform.modules') }}" data-platform-modules="1.0.1">
    @forelse($platformModules as $platformModule)
        <a class="governance-icon-link platform-module-link" href="{{ route($platformModule['route'],['locale'=>app()->getLocale()]) }}">
            <span class="platform-module-top"><span class="governance-icon">@include('control.navigation._icon',['icon'=>$platformModule['key']])</span><span class="platform-available">{{ __('platform.available') }}</span></span>
            <span class="platform-module-copy"><strong>{{ __($platformModule['title']) }}</strong><small>{{ __($platformModule['description'] ?? ('platform.descriptions.'.$platformModule['key'])) }}</small></span>
            <span class="platform-module-open">{{ __('platform.open') }}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M5 12h14M14 7l5 5-5 5"/></svg></span>
        </a>
    @empty
        <p class="platform-empty">{{ __('platform.empty') }}</p>
    @endforelse
</nav>
