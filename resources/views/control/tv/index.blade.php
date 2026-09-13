@extends('layouts.control')

@section('title', 'IUOAMC TV')

@section('content')
<style>
.tv-shell{display:grid;gap:18px}.tv-hero{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,.6fr);gap:18px;align-items:stretch}.tv-card{background:linear-gradient(180deg,rgba(10,24,44,.98),rgba(7,18,34,.98));border:1px solid rgba(214,170,75,.18);border-radius:22px;padding:18px;box-shadow:0 18px 45px rgba(0,0,0,.14)}.tv-brand{display:flex;gap:18px;align-items:center}.tv-logo{width:120px;height:120px;object-fit:contain;filter:drop-shadow(0 10px 24px rgba(0,0,0,.34))}.tv-kicker{font-size:11px;letter-spacing:.18em;color:#d6aa4b;text-transform:uppercase}.tv-title{margin:5px 0 6px;font-size:32px}.tv-sub{margin:0;color:#98a9c1;line-height:1.7}.tv-clock{display:grid;align-content:center;justify-items:center;text-align:center}.tv-clock strong{font:700 38px/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.04em}.tv-clock span{margin-top:7px;color:#97a7bd;font-size:12px}.tv-ticker{overflow:hidden;border:1px solid rgba(214,170,75,.28);border-radius:14px;background:linear-gradient(90deg,#0c1730,#111d35);position:relative}.tv-ticker-track{white-space:nowrap;display:inline-block;min-width:100%;padding:10px 0;will-change:transform;animation:tvTicker 24s linear infinite}.tv-ticker-track span{display:inline-block;padding-inline:26px;color:#f3d07a;font-size:13px;font-weight:700}.tv-ticker-track i{font-style:normal;color:#8ea2bb;font-weight:500}.tv-ticker:hover .tv-ticker-track{animation-play-state:paused}@keyframes tvTicker{from{transform:translate3d(100%,0,0)}to{transform:translate3d(-100%,0,0)}}.tv-main{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,.5fr);gap:18px}.tv-preview{padding:0;overflow:hidden}.tv-screen{aspect-ratio:16/9;background:#02060c;display:grid;place-items:center;position:relative}.tv-screen iframe{width:100%;height:100%;border:0;background:#000}.tv-offline{display:grid;place-items:center;text-align:center;padding:26px;color:#96a5bb}.tv-offline img{width:110px;height:110px;object-fit:contain;opacity:.92;margin-bottom:12px}.tv-overlay{position:absolute;inset:0;pointer-events:none}.tv-bug{position:absolute;top:18px;right:18px;width:72px;height:72px;object-fit:contain;filter:drop-shadow(0 6px 12px rgba(0,0,0,.5))}.tv-live{position:absolute;top:20px;left:20px;background:#bf2f3b;border-radius:8px;padding:6px 9px;font-size:11px;font-weight:800;color:white;box-shadow:0 8px 20px rgba(191,47,59,.25)}.tv-now{padding:14px 18px;border-top:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;gap:12px;align-items:center}.tv-now b{display:block}.tv-now small{color:#8fa1b8}.tv-stack{display:grid;gap:14px}.tv-section h3{margin:0 0 10px;font-size:14px}.tv-status{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)}.tv-status:last-child{border-bottom:0}.tv-status small{display:block;color:#8fa1b8;margin-top:3px}.tv-dot{width:10px;height:10px;border-radius:50%;background:#d19a34;box-shadow:0 0 0 5px rgba(209,154,52,.1)}.tv-dot.ok{background:#19b77b;box-shadow:0 0 0 5px rgba(25,183,123,.1)}.tv-actions{display:flex;gap:8px;flex-wrap:wrap}.tv-btn{appearance:none;border:1px solid #2c405f;background:#0b1830;color:#e8eff9;border-radius:10px;padding:9px 11px;text-decoration:none;font-size:12px;cursor:pointer}.tv-btn.gold{border-color:rgba(214,170,75,.45);color:#f2ca6e}.tv-btn:hover{border-color:#58749c}.tv-ai{display:grid;gap:10px}.tv-ai textarea{width:100%;min-height:88px;resize:vertical;background:#071426;border:1px solid #243955;border-radius:12px;color:#eef4ff;padding:12px}.tv-ai-response{min-height:80px;border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:12px;background:#091526;color:#aab9cd;font-size:13px;line-height:1.65}.tv-ai-meta{font-size:11px;color:#75879f}.tv-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.tv-kpi{padding:14px;border-radius:14px;background:#091526;border:1px solid rgba(255,255,255,.06)}.tv-kpi small{color:#8395ae;display:block}.tv-kpi strong{display:block;margin-top:7px;font-size:19px}.tv-warning{border-color:rgba(222,165,63,.3);background:rgba(222,165,63,.05);color:#e7c272;padding:12px 14px;border-radius:12px;font-size:12px}@media(max-width:980px){.tv-hero,.tv-main{grid-template-columns:1fr}.tv-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.tv-brand{align-items:flex-start}.tv-logo{width:88px;height:88px}.tv-title{font-size:25px}.tv-grid{grid-template-columns:1fr}.tv-now{align-items:flex-start;flex-direction:column}.tv-clock strong{font-size:31px}}
</style>

<div class="tv-shell" dir="{{ app()->getLocale()==='ar' ? 'rtl' : 'ltr' }}">
    <section class="tv-hero">
        <article class="tv-card tv-brand">
            <img class="tv-logo" src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt="IUOAMC TV">
            <div>
                <div class="tv-kicker">IUOAMC BROADCAST COMMAND</div>
                <h1 class="tv-title">IUOAMC TV</h1>
                <p class="tv-sub">مركز إدارة القناة والبث المباشر والبرامج والجدولة والذكاء التشغيلي داخل iuoamc.pro.</p>
            </div>
        </article>
        <article class="tv-card tv-clock">
            <div class="tv-kicker">CHANNEL LOCAL TIME</div>
            <strong id="tvClock">--:--:--</strong>
            <span id="tvDate">—</span>
            <span>{{ $channel['timezone'] }}</span>
        </article>
    </section>

    <div class="tv-ticker" aria-label="Channel ticker"><div class="tv-ticker-track"><span>IUOAMC TV <i>· Broadcast Nexus integration active</i></span><span>4K MASTER <i>· 3840×2160 / 25fps</i></span><span>AI BROADCAST COPILOT <i>· operational assistant</i></span><span>PUBLIC OUTPUT <i>· {{ $channel['public_enabled'] ? 'ENABLED' : 'DISABLED / SAFE' }}</i></span></div></div>

    @if(!$channel['public_enabled'])
        <div class="tv-warning">وضع الأمان مفعل: هذه الواجهة لا تنشر البث الجديد للعامة ولا تستبدل القناة القديمة. الربط النهائي يتم فقط بعد اعتماد الجاهزية.</div>
    @endif

    <section class="tv-grid">
        <article class="tv-kpi"><small>Master</small><strong>4K UHD</strong></article>
        <article class="tv-kpi"><small>Frame Rate</small><strong>25 FPS</strong></article>
        <article class="tv-kpi"><small>Audio Path</small><strong>Opus 48k</strong></article>
        <article class="tv-kpi"><small>Mode</small><strong>{{ $channel['public_enabled'] ? 'PUBLIC' : 'STAGING' }}</strong></article>
    </section>

    <section class="tv-main">
        <article class="tv-card tv-preview">
            <div class="tv-screen">
                @if($channel['preview_url'])
                    <iframe src="{{ $channel['preview_url'] }}" allow="autoplay; fullscreen" title="IUOAMC TV Preview"></iframe>
                @else
                    <div class="tv-offline">
                        <img src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt="IUOAMC TV">
                        <b>Preview endpoint is not connected yet.</b>
                        <small>Set IUOAMC_TV_PREVIEW_URL after the secured Nexus ingress is ready.</small>
                    </div>
                @endif
                <div class="tv-overlay"><span class="tv-live">LIVE CONTROL</span><img class="tv-bug" src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt=""></div>
            </div>
            <div class="tv-now"><div><small>NOW</small><b>IUOAMC TV Experimental 4K Service</b></div><div><small>NEXT</small><b>Controlled by Scheduler / Playout</b></div></div>
        </article>

        <aside class="tv-stack">
            <article class="tv-card tv-section">
                <h3>Broadcast Health</h3>
                <div class="tv-status"><div><b>4K Master</b><small>Broadcast Nexus engine</small></div><span class="tv-dot ok"></span></div>
                <div class="tv-status"><div><b>Audio</b><small>Track detected; browser playback still under validation</small></div><span class="tv-dot"></span></div>
                <div class="tv-status"><div><b>Public Publishing</b><small>Safety lock</small></div><span class="tv-dot {{ $channel['public_enabled'] ? 'ok' : '' }}"></span></div>
            </article>

            <article class="tv-card tv-section">
                <h3>Quick Access</h3>
                <div class="tv-actions">
                    @if($channel['legacy_url'])<a class="tv-btn" href="{{ $channel['legacy_url'] }}" target="_blank" rel="noopener">Legacy Channel</a>@endif
                    @if($channel['hls_url'])<a class="tv-btn gold" href="{{ $channel['hls_url'] }}" target="_blank" rel="noopener">4K HLS</a>@endif
                    <a class="tv-btn" href="{{ route('dashboard',['locale'=>app()->getLocale()]) }}">Main Dashboard</a>
                </div>
            </article>

            <article class="tv-card tv-section tv-ai">
                <h3>AI Broadcast Copilot</h3>
                <textarea id="tvAiQuestion" placeholder="مثال: حلل حالة القناة واقترح أولوية العمل التالية"></textarea>
                <button type="button" class="tv-btn gold" id="tvAiAsk">تحليل</button>
                <div id="tvAiResponse" class="tv-ai-response">المساعد جاهز لتحليل التشغيل والمحتوى ضمن المعلومات المتاحة في iuoamc.pro.</div>
                <div id="tvAiMeta" class="tv-ai-meta"></div>
            </article>
        </aside>
    </section>
</div>

<script>
(() => {
    const zone = @json($channel['timezone']);
    const locale = @json(app()->getLocale());
    const clock = document.getElementById('tvClock');
    const date = document.getElementById('tvDate');
    const tick = () => {
        const now = new Date();
        clock.textContent = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-GB' : locale, {timeZone:zone,hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).format(now);
        date.textContent = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-GB' : locale, {timeZone:zone,weekday:'long',year:'numeric',month:'long',day:'numeric'}).format(now);
    };
    tick(); setInterval(tick,1000);

    const ask = document.getElementById('tvAiAsk');
    ask?.addEventListener('click', async () => {
        const q = document.getElementById('tvAiQuestion').value.trim();
        const out = document.getElementById('tvAiResponse');
        const meta = document.getElementById('tvAiMeta');
        if(q.length < 3){ out.textContent='اكتب سؤالاً أو طلب تحليل واضحاً.'; return; }
        ask.disabled=true; out.textContent='جارٍ التحليل...'; meta.textContent='';
        try{
            const response = await fetch(`/${locale}/ai/ask`, {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify({question:q,page_path:`/${locale}/control/tv`})});
            const data = await response.json();
            if(!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
            out.textContent=data.answer || 'No answer returned.';
            meta.textContent=data.request_id ? `Request: ${data.request_id}` : '';
        }catch(e){out.textContent='تعذر تشغيل مساعد الذكاء الآن: '+e.message;}
        finally{ask.disabled=false;}
    });
})();
</script>
@endsection
