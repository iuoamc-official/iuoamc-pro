@extends('layouts.control')

@section('title', 'IUOAMC TV')

@section('content')
<style>
.tv-shell{display:grid;gap:18px}.tv-hero,.tv-main,.tv-lower{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,.45fr);gap:18px}.tv-card{background:linear-gradient(180deg,rgba(10,24,44,.985),rgba(7,18,34,.985));border:1px solid rgba(214,170,75,.18);border-radius:22px;padding:18px;box-shadow:0 18px 42px rgba(0,0,0,.12)}.tv-brand{display:flex;align-items:center;gap:18px;position:relative;overflow:hidden}.tv-brand:after{content:"";position:absolute;inset:auto -60px -90px auto;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(214,170,75,.12),transparent 68%);pointer-events:none}.tv-logo{width:112px;height:112px;object-fit:contain;filter:drop-shadow(0 10px 25px rgba(0,0,0,.34))}.tv-kicker{font-size:11px;letter-spacing:.18em;color:#d6aa4b;text-transform:uppercase}.tv-title{margin:5px 0 6px;font-size:32px}.tv-sub{margin:0;color:#9aacbf;line-height:1.75;max-width:850px}.tv-clock{display:grid;place-items:center;text-align:center}.tv-clock strong{font:700 36px/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.04em}.tv-clock span{color:#8fa1b8;font-size:12px}.tv-warning{padding:12px 14px;border:1px solid rgba(214,170,75,.32);border-radius:12px;background:rgba(214,170,75,.06);color:#e7c272}.tv-ticker{overflow:hidden;border:1px solid rgba(214,170,75,.28);border-radius:14px;background:linear-gradient(90deg,#0a1730,#101c34)}.tv-track{display:inline-block;white-space:nowrap;min-width:100%;padding:10px 0;animation:tvticker 25s linear infinite;will-change:transform}.tv-track span{display:inline-block;padding-inline:28px;color:#f3d07a;font-weight:700;font-size:13px}.tv-track i{font-style:normal;color:#8ea2bb;font-weight:500}.tv-ticker:hover .tv-track{animation-play-state:paused}@keyframes tvticker{from{transform:translate3d(100%,0,0)}to{transform:translate3d(-100%,0,0)}}.tv-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.tv-kpi{padding:14px;border-radius:14px;background:#091526;border:1px solid rgba(255,255,255,.06)}.tv-kpi small{display:block;color:#8294ac}.tv-kpi strong{display:block;margin-top:6px;font-size:19px}.tv-kpi em{display:block;margin-top:5px;color:#647891;font-size:11px;font-style:normal}.tv-screen{aspect-ratio:16/9;background:#02060c;border-radius:16px;overflow:hidden;display:grid;place-items:center;position:relative;border:1px solid rgba(255,255,255,.05)}.tv-screen iframe{width:100%;height:100%;border:0;background:#000}.tv-placeholder{text-align:center;color:#91a2b8;padding:24px}.tv-placeholder img{width:108px;height:108px;object-fit:contain;margin:0 auto 12px}.tv-placeholder strong{display:block;color:#e6edf7;margin-bottom:5px}.tv-bug{position:absolute;right:16px;top:16px;width:68px;height:68px;object-fit:contain;filter:drop-shadow(0 6px 12px rgba(0,0,0,.5));pointer-events:none}.tv-live{position:absolute;left:16px;top:16px;background:#b93240;color:#fff;padding:6px 9px;border-radius:8px;font-size:11px;font-weight:800;box-shadow:0 8px 18px rgba(185,50,64,.25)}.tv-programbar{display:grid;grid-template-columns:1fr 1fr;gap:1px;margin-top:12px;border-radius:14px;overflow:hidden;background:rgba(255,255,255,.06)}.tv-program{background:#091526;padding:14px}.tv-program small{display:block;color:#7890aa;font-size:10px;letter-spacing:.12em}.tv-program b{display:block;margin-top:5px;font-size:13px}.tv-program span{display:block;margin-top:3px;color:#8294aa;font-size:11px}.tv-stack{display:grid;gap:14px}.tv-status{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06)}.tv-status:last-child{border-bottom:0}.tv-status small{display:block;color:#8496ae;margin-top:2px}.tv-dot{width:10px;height:10px;border-radius:50%;background:#d19a34;box-shadow:0 0 0 5px rgba(209,154,52,.1);margin-top:5px;flex:0 0 auto}.tv-dot.ok{background:#19b77b;box-shadow:0 0 0 5px rgba(25,183,123,.1)}.tv-dot.off{background:#56677c;box-shadow:0 0 0 5px rgba(86,103,124,.1)}.tv-actions{display:flex;gap:8px;flex-wrap:wrap}.tv-btn{border:1px solid #2c405f;background:#0b1830;color:#e8eff9;border-radius:10px;padding:9px 11px;text-decoration:none;font-size:12px;cursor:pointer}.tv-btn.gold{border-color:rgba(214,170,75,.45);color:#f2ca6e}.tv-btn:hover{border-color:#58749c}.tv-ai textarea{width:100%;min-height:88px;background:#071426;border:1px solid #243955;border-radius:12px;color:#eef4ff;padding:12px;resize:vertical}.tv-ai-response{margin-top:10px;min-height:78px;padding:12px;border:1px solid rgba(255,255,255,.06);border-radius:12px;background:#091526;color:#aab9cd;line-height:1.6;font-size:13px}.tv-section-title{display:flex;justify-content:space-between;align-items:end;gap:12px;margin-bottom:12px}.tv-section-title h2,.tv-section-title h3{margin:3px 0 0;font-size:16px}.tv-section-title small{color:#71869f}.tv-epg{display:grid;gap:8px}.tv-epg-row{display:grid;grid-template-columns:86px 1fr auto;gap:12px;align-items:center;padding:11px 12px;border:1px solid rgba(255,255,255,.055);background:#091526;border-radius:12px}.tv-epg-row time{font:700 12px/1 ui-monospace,SFMono-Regular,Menlo,monospace;color:#d6aa4b}.tv-epg-row b{display:block;font-size:13px}.tv-epg-row small{display:block;margin-top:3px;color:#7f91a7}.tv-chip{border:1px solid #2a3d58;border-radius:999px;padding:5px 8px;color:#91a3ba;font-size:10px;white-space:nowrap}.tv-chip.live{border-color:rgba(185,50,64,.4);color:#ff929c;background:rgba(185,50,64,.08)}.tv-modules{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.tv-module{padding:13px;border-radius:13px;background:#091526;border:1px solid rgba(255,255,255,.055)}.tv-module b{display:block;font-size:12px}.tv-module small{display:block;margin-top:4px;color:#788ca5;line-height:1.5}.tv-module .state{display:inline-block;margin-top:8px;font-size:10px;color:#d5aa4e}.tv-readonly{font-size:11px;color:#71849b;line-height:1.6;margin-top:10px}.tv-audio-meter{height:7px;border-radius:999px;background:#07111f;overflow:hidden;margin-top:7px}.tv-audio-meter span{display:block;height:100%;width:68%;background:linear-gradient(90deg,#19b77b,#d6aa4b);border-radius:inherit;opacity:.75}.tv-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.tv-meta div{padding:9px 10px;border:1px solid rgba(255,255,255,.05);border-radius:10px;background:#081321}.tv-meta small{display:block;color:#73869e}.tv-meta b{display:block;margin-top:3px;font-size:11px}@media(max-width:1100px){.tv-modules{grid-template-columns:repeat(2,1fr)}}@media(max-width:980px){.tv-hero,.tv-main,.tv-lower{grid-template-columns:1fr}.tv-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.tv-grid,.tv-modules,.tv-programbar,.tv-meta{grid-template-columns:1fr}.tv-logo{width:84px;height:84px}.tv-title{font-size:25px}.tv-epg-row{grid-template-columns:70px 1fr}.tv-epg-row .tv-chip{grid-column:2}}
</style>

<div class="tv-shell" dir="{{ app()->getLocale()==='ar' ? 'rtl' : 'ltr' }}">
    <section class="tv-hero">
        <article class="tv-card tv-brand">
            <img class="tv-logo" src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt="IUOAMC TV">
            <div>
                <div class="tv-kicker">IUOAMC BROADCAST COMMAND</div>
                <h1 class="tv-title">IUOAMC TV</h1>
                <p class="tv-sub">مركز تشغيل القناة داخل iuoamc.pro. الواجهة الحالية للمتابعة والإدارة الآمنة، بينما يبقى Broadcast Nexus محرك البث الخلفي والقناة القديمة مستقلة دون أي استبدال أو قطع.</p>
            </div>
        </article>
        <article class="tv-card tv-clock">
            <div class="tv-kicker">CHANNEL LOCAL TIME</div>
            <strong id="tvClock">--:--:--</strong>
            <span id="tvDate">—</span>
            <span>{{ $channel['timezone'] }}</span>
        </article>
    </section>

    <div class="tv-ticker" aria-label="IUOAMC TV ticker"><div class="tv-track"><span>IUOAMC TV <i>· Broadcast Nexus Control</i></span><span>4K MASTER <i>· 3840×2160 / 25fps</i></span><span>AI BROADCAST COPILOT <i>· Operational Assistant</i></span><span>PUBLIC OUTPUT <i>· {{ $channel['public_enabled'] ? 'Enabled' : 'Safe / Disabled' }}</i></span></div></div>

    @if(!$channel['public_enabled'])
        <div class="tv-warning">وضع الأمان مفعل: لا يوجد Cutover ولا نشر عام جديد من هذه الصفحة. القناة القديمة تبقى مستقلة، والربط مع Nexus يتم تدريجيًا بعد التحقق من الصورة والصوت والجدولة.</div>
    @endif

    <section class="tv-grid">
        <article class="tv-kpi"><small>Master</small><strong>4K UHD</strong><em>3840×2160</em></article>
        <article class="tv-kpi"><small>Frame Rate</small><strong>25 FPS</strong><em>Broadcast master</em></article>
        <article class="tv-kpi"><small>Audio</small><strong>Opus 48k</strong><em>Browser output under validation</em></article>
        <article class="tv-kpi"><small>Publishing</small><strong>{{ $channel['public_enabled'] ? 'PUBLIC' : 'LOCKED' }}</strong><em>{{ $channel['public_enabled'] ? 'Enabled by config' : 'Safety guard active' }}</em></article>
    </section>

    <section class="tv-main">
        <article class="tv-card">
            <div class="tv-section-title"><div><div class="tv-kicker">PROGRAM MONITOR</div><h2>IUOAMC TV Preview</h2></div><small>{{ $channel['preview_url'] ? 'Preview configured' : 'Awaiting secure Nexus ingress' }}</small></div>
            <div class="tv-screen">
                @if($channel['preview_url'])
                    <iframe src="{{ $channel['preview_url'] }}" allow="autoplay; fullscreen" title="IUOAMC TV Preview"></iframe>
                @else
                    <div class="tv-placeholder"><img src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt="IUOAMC TV"><strong>المشغل الآمن غير مربوط بعد</strong><div>سيظهر بث Nexus هنا بعد اعتماد رابط Preview داخلي/آمن بدل تعريض MediaMTX مباشرة للعامة.</div></div>
                @endif
                <span class="tv-live">CONTROL</span>
                <img class="tv-bug" src="/assets/brand/master-v1/iuoamc-tv-seal-v1.webp" alt="">
            </div>
            <div class="tv-programbar">
                <div class="tv-program"><small>NOW</small><b>IUOAMC TV Experimental Service</b><span>Read-only operational view</span></div>
                <div class="tv-program"><small>NEXT</small><b>Scheduler / Playout Sync</b><span>Dynamic EPG wiring is the next integration step</span></div>
            </div>
        </article>

        <aside class="tv-stack">
            <article class="tv-card">
                <div class="tv-kicker">BROADCAST HEALTH</div>
                <div class="tv-status"><div><b>4K Master</b><small>Nexus master profile defined</small></div><span class="tv-dot ok"></span></div>
                <div class="tv-status"><div><b>Preview Player</b><small>{{ $channel['preview_url'] ? 'Configured' : 'Not connected' }}</small></div><span class="tv-dot {{ $channel['preview_url'] ? 'ok' : 'off' }}"></span></div>
                <div class="tv-status"><div><b>4K HLS</b><small>{{ $channel['hls_url'] ? 'Endpoint configured' : 'Not exposed to site' }}</small></div><span class="tv-dot {{ $channel['hls_url'] ? 'ok' : 'off' }}"></span></div>
                <div class="tv-status"><div><b>Public Publishing</b><small>{{ $channel['public_enabled'] ? 'Enabled' : 'Safety locked' }}</small></div><span class="tv-dot {{ $channel['public_enabled'] ? 'ok' : 'off' }}"></span></div>
                <div class="tv-status"><div style="width:100%"><b>Audio path</b><small>Source/Nexus track known; audible browser output still requires client validation.</small><div class="tv-audio-meter"><span></span></div></div></div>
            </article>

            <article class="tv-card">
                <div class="tv-kicker">QUICK ACCESS</div>
                <div class="tv-actions" style="margin-top:10px">
                    @if($channel['legacy_url'])<a class="tv-btn" href="{{ $channel['legacy_url'] }}" target="_blank" rel="noopener">Legacy Channel</a>@endif
                    @if($channel['hls_url'])<a class="tv-btn gold" href="{{ $channel['hls_url'] }}" target="_blank" rel="noopener">4K HLS</a>@endif
                    <a class="tv-btn" href="{{ route('dashboard',['locale'=>app()->getLocale()]) }}">Main Dashboard</a>
                </div>
                <div class="tv-readonly">هذه الصفحة لا ترسل أوامر switching أو publish إلى Nexus في هذه المرحلة.</div>
            </article>

            <article class="tv-card tv-ai">
                <div class="tv-kicker">AI BROADCAST COPILOT</div>
                <textarea id="tvAiQuestion" placeholder="مثال: حلل حالة القناة واقترح أولوية العمل التالية"></textarea>
                <button type="button" class="tv-btn gold" id="tvAiAsk" style="margin-top:8px">تحليل</button>
                <div id="tvAiResponse" class="tv-ai-response">المساعد جاهز للتحليل ضمن المعلومات المتاحة في iuoamc.pro. لا ينفذ أوامر بث أو switching.</div>
            </article>
        </aside>
    </section>

    <section class="tv-lower">
        <article class="tv-card">
            <div class="tv-section-title"><div><div class="tv-kicker">EPG / RUNDOWN</div><h3>الجدول التشغيلي</h3></div><small>Preview until Scheduler API is connected</small></div>
            <div class="tv-epg">
                <div class="tv-epg-row"><time>LIVE</time><div><b>Experimental Service</b><small>واجهة القناة الجديدة داخل iuoamc.pro</small></div><span class="tv-chip live">CONTROL VIEW</span></div>
                <div class="tv-epg-row"><time>+ NEXT</time><div><b>Scheduler Integration</b><small>ربط Now / Next وEPG من Broadcast Nexus بدل البيانات الثابتة</small></div><span class="tv-chip">PENDING</span></div>
                <div class="tv-epg-row"><time>+ 02</time><div><b>Playout Integration</b><small>قراءة rundown والـqueue وحالة playout دون منح تحكم إنتاجي بعد</small></div><span class="tv-chip">PLANNED</span></div>
                <div class="tv-epg-row"><time>+ 03</time><div><b>Distribution & NOC</b><small>Health / destinations / alarms ضمن لوحة واحدة</small></div><span class="tv-chip">PLANNED</span></div>
            </div>
        </article>

        <article class="tv-card">
            <div class="tv-section-title"><div><div class="tv-kicker">NEXUS MODULES</div><h3>مركز الربط</h3></div><small>Read-only phase</small></div>
            <div class="tv-modules">
                <div class="tv-module"><b>Scheduler</b><small>الجدولة والـEPG والبرامج القادمة.</small><span class="state">CONNECT NEXT</span></div>
                <div class="tv-module"><b>Playout</b><small>الراندوان والـqueue وحالة التشغيل.</small><span class="state">READ-ONLY FIRST</span></div>
                <div class="tv-module"><b>Media</b><small>مكتبة البرامج والهوية والـpromos.</small><span class="state">AVAILABLE IN NEXUS</span></div>
                <div class="tv-module"><b>Encoder</b><small>4K master ومسارات preview/ABR.</small><span class="state">NO PUBLIC ACTIONS</span></div>
                <div class="tv-module"><b>NOC</b><small>الصورة والصوت والـbitrate والتنبيهات.</small><span class="state">MONITORING TARGET</span></div>
                <div class="tv-module"><b>Distribution</b><small>IPTV / Web / YouTube / Apps.</small><span class="state">SAFETY LOCKED</span></div>
            </div>
            <div class="tv-meta">
                <div><small>Legacy channel</small><b>Independent / untouched</b></div>
                <div><small>New channel</small><b>Experimental / controlled</b></div>
                <div><small>Master target</small><b>2160p25</b></div>
                <div><small>Public cutover</small><b>{{ $channel['public_enabled'] ? 'Enabled' : 'Not authorized' }}</b></div>
            </div>
        </article>
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
