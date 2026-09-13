@extends('layouts.control')

@section('title', 'IUOAMC TV')

@section('content')
<style>
.tv-shell{display:grid;gap:18px}.tv-hero,.tv-main{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.5fr);gap:18px}.tv-card{background:linear-gradient(180deg,rgba(10,24,44,.98),rgba(7,18,34,.98));border:1px solid rgba(214,170,75,.18);border-radius:22px;padding:18px}.tv-brand{display:flex;align-items:center;gap:18px}.tv-logo{width:112px;height:112px;object-fit:contain}.tv-kicker{font-size:11px;letter-spacing:.18em;color:#d6aa4b;text-transform:uppercase}.tv-title{margin:5px 0 6px;font-size:32px}.tv-sub{margin:0;color:#9aacbf;line-height:1.7}.tv-clock{display:grid;place-items:center;text-align:center}.tv-clock strong{font:700 36px/1 ui-monospace,SFMono-Regular,Menlo,monospace}.tv-clock span{color:#8fa1b8;font-size:12px}.tv-warning{padding:12px 14px;border:1px solid rgba(214,170,75,.32);border-radius:12px;background:rgba(214,170,75,.06);color:#e7c272}.tv-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.tv-kpi{padding:14px;border-radius:14px;background:#091526;border:1px solid rgba(255,255,255,.06)}.tv-kpi small{display:block;color:#8294ac}.tv-kpi strong{display:block;margin-top:6px;font-size:19px}.tv-screen{aspect-ratio:16/9;background:#02060c;border-radius:16px;overflow:hidden;display:grid;place-items:center;position:relative}.tv-screen iframe{width:100%;height:100%;border:0}.tv-placeholder{text-align:center;color:#91a2b8;padding:24px}.tv-placeholder img{width:108px;height:108px;object-fit:contain;margin:0 auto 12px}.tv-bug{position:absolute;right:16px;top:16px;width:68px;height:68px;object-fit:contain}.tv-live{position:absolute;left:16px;top:16px;background:#b93240;color:#fff;padding:6px 9px;border-radius:8px;font-size:11px;font-weight:800}.tv-stack{display:grid;gap:14px}.tv-status{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06)}.tv-status:last-child{border-bottom:0}.tv-status small{display:block;color:#8496ae;margin-top:2px}.tv-dot{width:10px;height:10px;border-radius:50%;background:#d19a34;box-shadow:0 0 0 5px rgba(209,154,52,.1);margin-top:5px}.tv-dot.ok{background:#19b77b;box-shadow:0 0 0 5px rgba(25,183,123,.1)}.tv-actions{display:flex;gap:8px;flex-wrap:wrap}.tv-btn{border:1px solid #2c405f;background:#0b1830;color:#e8eff9;border-radius:10px;padding:9px 11px;text-decoration:none;font-size:12px}.tv-btn.gold{border-color:rgba(214,170,75,.45);color:#f2ca6e}.tv-ai textarea{width:100%;min-height:88px;background:#071426;border:1px solid #243955;border-radius:12px;color:#eef4ff;padding:12px;resize:vertical}.tv-ai-response{margin-top:10px;min-height:78px;padding:12px;border:1px solid rgba(255,255,255,.06);border-radius:12px;background:#091526;color:#aab9cd;line-height:1.6;font-size:13px}.tv-ticker{overflow:hidden;border:1px solid rgba(214,170,75,.28);border-radius:14px;background:#0d1830}.tv-track{display:inline-block;white-space:nowrap;min-width:100%;padding:10px 0;animation:tvticker 24s linear infinite}.tv-track span{display:inline-block;padding-inline:26px;color:#f3d07a;font-weight:700;font-size:13px}@keyframes tvticker{from{transform:translateX(100%)}to{transform:translateX(-100%)}}@media(max-width:980px){.tv-hero,.tv-main{grid-template-columns:1fr}.tv-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.tv-grid{grid-template-columns:1fr}.tv-logo{width:84px;height:84px}.tv-title{font-size:25px}}
</style>

<div class="tv-shell" dir="{{ app()->getLocale()==='ar' ? 'rtl' : 'ltr' }}">
    <section class="tv-hero">
        <article class="tv-card tv-brand">
            <img class="tv-logo" src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt="IUOAMC TV">
            <div>
                <div class="tv-kicker">IUOAMC BROADCAST COMMAND</div>
                <h1 class="tv-title">IUOAMC TV</h1>
                <p class="tv-sub">مركز إدارة القناة والبث والبرامج والجدولة وربط Broadcast Nexus داخل iuoamc.pro.</p>
            </div>
        </article>
        <article class="tv-card tv-clock">
            <div class="tv-kicker">CHANNEL LOCAL TIME</div>
            <strong id="tvClock">--:--:--</strong>
            <span id="tvDate">—</span>
            <span>{{ $channel['timezone'] }}</span>
        </article>
    </section>

    <div class="tv-ticker"><div class="tv-track"><span>IUOAMC TV · Broadcast Nexus</span><span>4K MASTER · 3840×2160 / 25fps</span><span>AI Broadcast Copilot · Operational Assistant</span><span>Public Output · {{ $channel['public_enabled'] ? 'Enabled' : 'Safe / Disabled' }}</span></div></div>

    @if(!$channel['public_enabled'])
        <div class="tv-warning">وضع الأمان مفعل: القناة الجديدة لا تستبدل القناة القديمة ولا تنشر للعامة قبل اعتماد الجاهزية.</div>
    @endif

    <section class="tv-grid">
        <article class="tv-kpi"><small>Master</small><strong>4K UHD</strong></article>
        <article class="tv-kpi"><small>Frame Rate</small><strong>25 FPS</strong></article>
        <article class="tv-kpi"><small>Audio</small><strong>Opus 48k</strong></article>
        <article class="tv-kpi"><small>Mode</small><strong>{{ $channel['public_enabled'] ? 'PUBLIC' : 'STAGING' }}</strong></article>
    </section>

    <section class="tv-main">
        <article class="tv-card">
            <div class="tv-screen">
                @if($channel['preview_url'])
                    <iframe src="{{ $channel['preview_url'] }}" allow="autoplay; fullscreen" title="IUOAMC TV Preview"></iframe>
                @else
                    <div class="tv-placeholder"><img src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt="IUOAMC TV"><strong>Preview endpoint is not connected yet.</strong><div>سيتم ربط مشغل Nexus الآمن في مرحلة الربط التالية.</div></div>
                @endif
                <span class="tv-live">LIVE CONTROL</span>
                <img class="tv-bug" src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt="">
            </div>
        </article>

        <aside class="tv-stack">
            <article class="tv-card">
                <div class="tv-kicker">BROADCAST HEALTH</div>
                <div class="tv-status"><div><b>4K Master</b><small>Broadcast Nexus engine</small></div><span class="tv-dot ok"></span></div>
                <div class="tv-status"><div><b>Audio</b><small>Track detected; browser validation pending</small></div><span class="tv-dot"></span></div>
                <div class="tv-status"><div><b>Public Publishing</b><small>Safety lock</small></div><span class="tv-dot {{ $channel['public_enabled'] ? 'ok' : '' }}"></span></div>
            </article>

            <article class="tv-card">
                <div class="tv-kicker">QUICK ACCESS</div>
                <div class="tv-actions" style="margin-top:10px">
                    @if($channel['legacy_url'])<a class="tv-btn" href="{{ $channel['legacy_url'] }}" target="_blank" rel="noopener">Legacy Channel</a>@endif
                    @if($channel['hls_url'])<a class="tv-btn gold" href="{{ $channel['hls_url'] }}" target="_blank" rel="noopener">4K HLS</a>@endif
                    <a class="tv-btn" href="{{ route('dashboard',['locale'=>app()->getLocale()]) }}">Dashboard</a>
                </div>
            </article>

            <article class="tv-card tv-ai">
                <div class="tv-kicker">AI BROADCAST COPILOT</div>
                <textarea id="tvAiQuestion" placeholder="مثال: حلل حالة القناة واقترح أولوية العمل التالية"></textarea>
                <button type="button" class="tv-btn gold" id="tvAiAsk" style="margin-top:8px">تحليل</button>
                <div id="tvAiResponse" class="tv-ai-response">المساعد جاهز للتحليل ضمن المعلومات المتاحة في iuoamc.pro.</div>
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
        clock.textContent = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-GB' : locale,{timeZone:zone,hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).format(now);
        date.textContent = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-GB' : locale,{timeZone:zone,weekday:'long',year:'numeric',month:'long',day:'numeric'}).format(now);
    };
    tick(); setInterval(tick,1000);

    document.getElementById('tvAiAsk')?.addEventListener('click', async function(){
        const q = document.getElementById('tvAiQuestion').value.trim();
        const out = document.getElementById('tvAiResponse');
        if(q.length < 3){out.textContent='اكتب سؤالاً واضحاً.';return;}
        this.disabled=true;out.textContent='جارٍ التحليل...';
        try{
            const r=await fetch(`/${locale}/ai/ask`,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify({question:q,page_path:`/${locale}/control/tv`})});
            const d=await r.json();
            if(!r.ok) throw new Error(d.message||`HTTP ${r.status}`);
            out.textContent=d.answer||'No answer returned.';
        }catch(e){out.textContent='تعذر تشغيل المساعد الآن: '+e.message;}finally{this.disabled=false;}
    });
})();
</script>
@endsection
