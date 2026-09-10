@extends('layouts.control')

@section('title', __('ui.dashboard'))

@section('content')
    <section class="hero-card">
        <div>
            <span class="eyebrow">EXECUTIVE COMMAND / LIVE</span>
            <h1 class="welcome-title">
                <span>{{ __('ui.welcome_prefix') }}</span>
                <bdi class="welcome-name" dir="ltr">{{ auth()->user()->name }}</bdi>
            </h1>
            <p>{{ __('ui.dashboard_intro') }}</p>
        </div>
        <div class="status-panel">
            <span>{{ __('ui.system_status') }}</span>
            <strong><i class="status-dot"></i>{{ __('ui.operational') }}</strong>
            @if($certificateOperations)
                <dl class="status-panel-facts">
                    <div><dt>{{ __('ui.certificate_ops.current') }}</dt><dd>{{ number_format($certificateOperations['stats']['current']) }}</dd></div>
                    <div><dt>{{ __('ui.certificate_ops.archive') }}</dt><dd>{{ number_format($certificateOperations['stats']['archive']) }}</dd></div>
                </dl>
            @endif
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

    @if($certificateOperations)
        <section class="section-heading dashboard-section-heading">
            <div>
                <span class="eyebrow">CERTIFICATE OPERATIONS</span>
                <h2>{{ __('ui.certificate_ops.title') }}</h2>
                <p>{{ __('ui.certificate_ops.intro') }}</p>
            </div>
            <a class="dashboard-action" href="{{ route('certificates.index', ['locale' => app()->getLocale()]) }}">{{ __('ui.certificate_ops.open_workspace') }} <span aria-hidden="true">→</span></a>
        </section>

        <section class="operations-kpi-grid" aria-label="{{ __('ui.certificate_ops.title') }}">
            @foreach([
                'current' => __('ui.certificate_ops.current'),
                'issued' => __('ui.certificate_ops.issued'),
                'review' => __('ui.certificate_ops.review'),
                'approved' => __('ui.certificate_ops.approved'),
                'archive' => __('ui.certificate_ops.archive'),
            ] as $key => $label)
                <article class="operations-kpi operations-kpi-{{ $key }}">
                    <span>{{ $label }}</span>
                    <strong>{{ number_format($certificateOperations['stats'][$key]) }}</strong>
                </article>
            @endforeach
        </section>

        <section class="operations-assurance" aria-label="{{ __('ui.certificate_ops.assurance_title') }}">
            <header>
                <span class="operations-assurance-mark" aria-hidden="true">✓</span>
                <div>
                    <span class="eyebrow">ISSUANCE ASSURANCE</span>
                    <h3>{{ __('ui.certificate_ops.assurance_title') }}</h3>
                    <p>{{ __('ui.certificate_ops.assurance_help') }}</p>
                </div>
            </header>
            <div class="operations-assurance-grid">
                @foreach([
                    'registry' => __('ui.certificate_ops.assurance_registry'),
                    'history' => __('ui.certificate_ops.assurance_history'),
                    'verification' => __('ui.certificate_ops.assurance_verification'),
                    'privacy' => __('ui.certificate_ops.assurance_privacy'),
                ] as $control => $label)
                    <div><span aria-hidden="true">✓</span><strong>{{ $label }}</strong><small>{{ __('ui.certificate_ops.assurance_'.$control.'_help') }}</small></div>
                @endforeach
            </div>
        </section>

        <section class="operations-launchpad" aria-labelledby="operations-launchpad-title">
            <header>
                <span class="eyebrow">CONTROL DESK</span>
                <h3 id="operations-launchpad-title">{{ __('ui.certificate_ops.launchpad') }}</h3>
                <p>{{ __('ui.certificate_ops.launchpad_help') }}</p>
            </header>
            <nav aria-label="{{ __('ui.certificate_ops.launchpad') }}">
                @if(auth()->user()->canDo('certificates.manage'))
                    <a href="{{ route('certificates.create', ['locale' => app()->getLocale()]) }}"><span>01</span><strong>{{ __('ui.certificate_ops.create') }}</strong><small>{{ __('ui.certificate_ops.create_help') }}</small></a>
                @endif
                <a href="{{ route('certificates.batches.index', ['locale' => app()->getLocale()]) }}"><span>02</span><strong>{{ __('ui.certificate_ops.batches') }}</strong><small>{{ __('ui.certificate_ops.batches_help') }}</small></a>
                <a href="{{ route('certificates.catalog.index', ['locale' => app()->getLocale()]) }}"><span>03</span><strong>{{ __('ui.certificate_ops.catalog') }}</strong><small>{{ __('ui.certificate_ops.catalog_help') }}</small></a>
                @if(auth()->user()->canDo('certificates.manage'))
                    <a href="{{ route('certificates.intakes.programs.index', ['locale' => app()->getLocale()]) }}"><span>04</span><strong>{{ __('ui.certificate_ops.intakes') }}</strong><small>{{ __('ui.certificate_ops.intakes_help') }}</small></a>
                @endif
            </nav>
        </section>

        <section class="operations-panel">
            <header>
                <div><h3>{{ __('ui.certificate_ops.recent') }}</h3><p>{{ __('ui.certificate_ops.recent_help') }}</p></div>
                @if($certificateOperations['stats']['review'] || $certificateOperations['stats']['approved'])
                    <a href="{{ route('certificates.index', ['locale' => app()->getLocale(), 'status' => $certificateOperations['stats']['approved'] ? 'approved' : 'review']) }}">{{ __('ui.certificate_ops.open_queue') }}</a>
                @endif
            </header>
            @if($certificateOperations['recent']->isNotEmpty())
                <div class="operations-table-wrap"><table class="operations-table">
                    <thead><tr><th>{{ __('ui.certificate_ops.certificate') }}</th><th>{{ __('ui.certificate_ops.recipient') }}</th><th>{{ __('ui.certificate_ops.organization') }}</th><th>{{ __('ui.certificate_ops.state') }}</th><th>{{ __('ui.certificate_ops.integrity') }}</th><th><span class="sr-only">{{ __('ui.certificate_ops.action') }}</span></th></tr></thead>
                    <tbody>@foreach($certificateOperations['recent'] as $certificate)<tr>
                        <td><bdi class="operations-number" dir="ltr">{{ $certificate->certificate_number ?: '—' }}</bdi><small><bdi>{{ $certificate->program_title }}</bdi></small></td>
                        <td><bdi>{{ $certificate->recipient_name }}</bdi></td>
                        <td><bdi>{{ $certificate->organization?->display_name }}</bdi></td>
                        <td><span class="operations-state">{{ __('certificates.states.'.$certificateOperations['statuses'][$certificate->id]) }}</span></td>
                        <td><span class="operations-integrity operations-integrity-{{ $certificateOperations['integrity'][$certificate->id] ? 'ok' : 'failed' }}">{{ $certificateOperations['integrity'][$certificate->id] ? __('ui.certificate_ops.integrity_ok') : __('ui.certificate_ops.integrity_failed') }}</span></td>
                        <td><a class="operations-open" href="{{ route('certificates.show', ['locale' => app()->getLocale(), 'certificate' => $certificate->id]) }}">{{ __('ui.certificate_ops.open') }}</a></td>
                    </tr>@endforeach</tbody>
                </table></div>
            @else
                <p class="operations-empty">{{ __('ui.certificate_ops.empty') }}</p>
            @endif
        </section>
    @endif

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
