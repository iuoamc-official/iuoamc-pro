@extends('layouts.control')

@section('title', __('ui.dashboard'))

@section('content')
    <section class="hero-card">
        <div>
            <span class="eyebrow">FOUNDATION / 01</span>
            <h1 class="welcome-title">
                <span>{{ __('ui.welcome_prefix') }}</span>
                <bdi class="welcome-name" dir="ltr">{{ auth()->user()->name }}</bdi>
            </h1>
            <p>{{ __('ui.dashboard_intro') }}</p>
        </div>
        <div class="status-panel">
            <span>{{ __('ui.system_status') }}</span>
            <strong><i class="status-dot"></i>{{ __('ui.operational') }}</strong>
        </div>
    </section>

    <section class="kpi-grid">
        @foreach ([
            'organizations' => __('ui.organizations'),
            'users' => __('ui.users'),
            'roles' => __('ui.roles'),
            'permissions' => __('ui.permissions'),
            'audit_logs' => __('ui.audit_events'),
        ] as $key => $label)
            <article class="kpi-card">
                <span>{{ $label }}</span>
                <strong>{{ number_format($stats[$key]) }}</strong>
                <small>{{ __('ui.foundation') }}</small>
            </article>
        @endforeach
    </section>

    <section class="section-heading">
        <div>
            <span class="eyebrow">MODULAR ARCHITECTURE</span>
            <h2>{{ __('platform.modules') }}</h2>
            <p>{{ __('platform.intro') }}</p>
        </div>
    </section>

    {{-- IUOAMC_PLATFORM_DASHBOARD_1_0_1 --}}
    @include('control.navigation._platform_modules')

    <section class="assurance-grid">
        <article><span class="status-dot"></span>{{ __('ui.no_external_assets') }}</article>
        <article><span class="status-dot"></span>{{ __('ui.protected_environment') }}</article>
    </section>
@endsection
