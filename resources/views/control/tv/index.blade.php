@extends('layouts.control')

@section('title', 'IUOAMC TV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-tv-control.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-tv-control.css')) }}">
@endpush

@section('content')
<div class="tv-control"
     data-tv-control
     data-timezone="{{ $channel['timezone'] }}"
     data-locale="{{ app()->getLocale() }}"
     data-status-url="{{ route('tv.control.status', ['locale' => app()->getLocale()]) }}">
    <section class="tv-top">
        <div class="tv-brand">
            <img src="{{ asset('assets/brand/master-v1/iuoamc-tv-seal-v1.webp') }}" alt="IUOAMC TV">
            <div class="tv-brand-copy">
                <div class="tv-eyebrow">IUOAMC BROADCAST CONTROL</div>
                <h1 class="tv-title">IUOAMC TV</h1>
                <p class="tv-subtitle">واجهة تشغيل ومراقبة القناة داخل iuoamc.pro. القناة القديمة مستقلة، وBroadcast Nexus يبقى محرك البث الخلفي دون أي cutover من هذه الصفحة.</p>
            </div>
        </div>
        <div class="tv-clock">
            <div class="tv-eyebrow">CHANNEL TIME</div>
            <strong data-tv-clock>--:--:--</strong>
            <span data-tv-date>—</span>
            <span>{{ $channel['timezone'] }}</span>
        </div>
    </section>

    @unless($channel['public_enabled'])
        <div class="tv-safety"><span class="tv-safety-dot"></span><span>وضع الأمان مفعل: لا نشر عام، لا switching، ولا استبدال للقناة القديمة.</span></div>
    @endunless

    <section class="tv-grid">
        <article class="tv-panel">
            <header class="tv-panel-head">
                <div>
                    <div class="tv-eyebrow">PROGRAM MONITOR</div>
                    <h2>IUOAMC TV Preview</h2>
                </div>
                <span class="tv-badge {{ $channel['preview_url'] ? 'ready' : 'locked' }}">{{ $channel['preview_url'] ? 'PREVIEW READY' : 'PREVIEW LOCKED' }}</span>
            </header>

            <div class="tv-monitor">
                @if($channel['preview_url'])
                    <iframe src="{{ $channel['preview_url'] }}" title="IUOAMC TV Preview" allow="autoplay; fullscreen"></iframe>
                @else
                    <div class="tv-monitor-placeholder">
                        <img src="{{ asset('assets/brand/master-v1/iuoamc-tv-seal-v1.webp') }}" alt="IUOAMC TV">
                        <strong>المشغل الآمن غير مربوط بعد</strong>
                        <span>سيظهر Preview هنا بعد اعتماد ingress داخلي آمن.</span>
                    </div>
                @endif
            </div>

            <div class="tv-now-next">
                <div>
                    <small>NOW</small>
                    <strong data-tv-now-title>IUOAMC TV Experimental Service</strong>
                    <span data-tv-now-subtitle>Read-only operational view</span>
                </div>
                <div>
                    <small>NEXT</small>
                    <strong data-tv-next-title>Scheduler / Playout Sync</strong>
                    <span data-tv-next-subtitle>Now / Next سيصبح ديناميكيًا بعد ربط Scheduler API.</span>
                </div>
            </div>
        </article>

        <aside class="tv-side">
            <article class="tv-panel">
                <header class="tv-panel-head"><h3>Broadcast Health</h3><small data-tv-last-check>Read-only</small></header>
                <div class="tv-status-list">
                    <div class="tv-status-row"><div><b>Broadcast Nexus</b><small data-tv-nexus-state>{{ $channel['status_url'] ? 'Configured / checking' : 'Status endpoint not configured' }}</small></div><span class="tv-led" data-tv-nexus-led></span></div>
                    <div class="tv-status-row"><div><b>4K Master</b><small>3840×2160 / 25fps</small></div><span class="tv-led ok"></span></div>
                    <div class="tv-status-row"><div><b>Preview Player</b><small>{{ $channel['preview_url'] ? 'Connected' : 'Not connected' }}</small></div><span class="tv-led {{ $channel['preview_url'] ? 'ok' : '' }}"></span></div>
                    <div class="tv-status-row"><div><b>4K HLS</b><small>{{ $channel['hls_url'] ? 'Configured' : 'Not exposed to site' }}</small></div><span class="tv-led {{ $channel['hls_url'] ? 'ok' : '' }}"></span></div>
                    <div class="tv-status-row"><div><b>Public Publishing</b><small>{{ $channel['public_enabled'] ? 'Enabled' : 'Safety locked' }}</small></div><span class="tv-led {{ $channel['public_enabled'] ? 'ok' : '' }}"></span></div>
                    <div class="tv-status-row"><div><b>Audio Path</b><small>Opus 48k; browser validation pending</small></div><span class="tv-led"></span></div>
                </div>
            </article>

            <article class="tv-panel">
                <header class="tv-panel-head"><h3>Quick Access</h3><small>No broadcast actions</small></header>
                <div class="tv-actions">
                    @if($channel['legacy_url'])<a class="tv-action" href="{{ $channel['legacy_url'] }}" target="_blank" rel="noopener">Legacy Channel</a>@endif
                    @if($channel['hls_url'])<a class="tv-action gold" href="{{ $channel['hls_url'] }}" target="_blank" rel="noopener">4K HLS</a>@endif
                    <a class="tv-action" href="{{ route('dashboard',['locale'=>app()->getLocale()]) }}">Main Dashboard</a>
                </div>
            </article>

            <article class="tv-panel">
                <header class="tv-panel-head"><h3>AI Broadcast Copilot</h3><small>Advisory only</small></header>
                <div class="tv-ai">
                    <textarea data-tv-ai-question placeholder="مثال: حلل حالة القناة واقترح أولوية العمل التالية"></textarea>
                    <div class="tv-ai-controls"><button type="button" data-tv-ai-submit>تحليل</button></div>
                    <div class="tv-ai-output" data-tv-ai-output>المساعد جاهز للتحليل. لا ينفذ أوامر بث أو switching.</div>
                </div>
            </article>
        </aside>
    </section>

    <section class="tv-panel">
        <header class="tv-panel-head">
            <div><div class="tv-eyebrow">EPG / RUNDOWN</div><h3>الجدول التشغيلي</h3></div>
            <small data-tv-epg-source>Read-only preview</small>
        </header>
        <div class="tv-epg" data-tv-epg>
            <div class="tv-epg-row">
                <div class="tv-epg-time">LIVE</div>
                <div class="tv-epg-title"><b>Experimental Service</b><small>بانتظار ربط Scheduler status feed.</small></div>
                <span class="tv-badge ready">CONTROL VIEW</span>
            </div>
            <div class="tv-epg-row">
                <div class="tv-epg-time">+ NEXT</div>
                <div class="tv-epg-title"><b>Scheduler Integration</b><small>سيتم استبدال هذا الصف ببيانات Nexus عند تفعيل endpoint الآمن.</small></div>
                <span class="tv-badge locked">PENDING</span>
            </div>
        </div>
    </section>

    <div class="tv-footnote">IUOAMC TV Control — safe read-only integration phase. Legacy channel remains independent.</div>
</div>
<script src="{{ asset('assets/js/iuoamc-tv-control.js') }}?v={{ filemtime(public_path('assets/js/iuoamc-tv-control.js')) }}" defer></script>
@endsection
