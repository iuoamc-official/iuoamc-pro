(()=>{
  const STORAGE_KEY='iuoamc_nexus_lang';
  const supported=['ar','en','fr'];
  const dict={
    ar:{
      'IUOAMC Broadcast Nexus':'منظومة بث IUOAMC',
      'Mission-Critical Operations':'عمليات البث الحرجة',
      'Overview':'نظرة عامة','Live Studio':'الاستوديو المباشر','WebRTC Lab':'مختبر WebRTC','Channels':'القنوات','Scheduler':'الجدولة','Media Library':'مكتبة الوسائط','Playout':'التشغيل','Encoder':'المُرمِّز','Distribution':'التوزيع','IPTV / HLS':'IPTV / HLS','NOC':'مركز عمليات الشبكة','Failover':'التحويل الاحتياطي','Supervisor':'المشرف','System Health':'صحة النظام',
      'Operations Overview':'نظرة عامة على العمليات','Unified control surface for Broadcast Nexus staging.':'واجهة تحكم موحّدة لبيئة Broadcast Nexus التجريبية.','STAGING':'تجريبي','PRODUCTION SWITCHING OFF':'التحويل للإنتاج متوقف','Services Up':'الخدمات العاملة','Unified integration snapshot':'ملخص التكامل الموحّد','Broadcast runtime state':'حالة تشغيل البث','Monitoring control plane':'طبقة تحكم المراقبة','Control Room':'غرفة التحكم','Operational telemetry':'القياسات التشغيلية','Core Service Status':'حالة الخدمات الأساسية','Waiting for staging runtime':'بانتظار بيئة التشغيل التجريبية','Dashboard Integration API':'واجهة تكامل لوحة التحكم','Unified staging health aggregation':'تجميع موحّد لصحة بيئة الاختبار','Broadcast Supervisor':'مشرف البث','Runtime orchestration and recovery':'تنسيق التشغيل والاستعادة','NOC API':'واجهة مركز عمليات الشبكة','Monitoring, incidents and health':'المراقبة والحوادث والصحة','Safety Envelope':'نطاق الأمان','Production outputs disabled':'مخرجات الإنتاج معطلة','No live production switching in staging.':'لا يوجد تحويل مباشر للإنتاج في بيئة الاختبار.','Readiness':'الجاهزية','Integration layer ready; VPS deployment pending.':'طبقة التكامل جاهزة؛ استكمال نشر VPS قيد المتابعة.','Broadcast Modules':'وحدات البث','Hosts, guests, scenes, Preview / Program.':'المضيفون والضيوف والمشاهد والمعاينة والبرنامج.','Programs, episodes, playlists, EPG slots.':'البرامج والحلقات وقوائم التشغيل وفترات EPG.','Assets, object storage, presigned uploads.':'الأصول وتخزين الكائنات والرفع الموقّع مسبقًا.','Deterministic rundown compiler and fallback.':'تجميع قائمة التشغيل الحتمية والمصدر الاحتياطي.','House profiles and isolated output jobs.':'ملفات الترميز المعتمدة ومهام الإخراج المعزولة.','YouTube, IPTV, HLS, SRT, RTMP models.':'نماذج YouTube وIPTV وHLS وSRT وRTMP.','Black, freeze, silence, FPS, bitrate, latency.':'رصد السواد والتجمّد والصمت وFPS ومعدل البت وزمن التأخير.','Decision engine, redundancy and cooldown.':'محرك القرار والتكرار وفترة التهدئة.','STAGING ONLY · Production outputs disabled':'بيئة اختبار فقط · مخرجات الإنتاج معطلة','Refresh Health':'تحديث الصحة','READY':'جاهز','UNAVAILABLE':'غير متاح','UNKNOWN':'غير معروف','UP':'يعمل','DOWN':'متوقف','OFF':'متوقف','SAFE':'آمن','LOCKED':'مقفل','OUTPUTS OFF':'المخرجات متوقفة','OUTPUTS DISABLED':'المخرجات معطلة','CONTROL PLANE READY':'طبقة التحكم جاهزة','STAGING LOCKED':'الاختبار مقفل','DECISION ONLY':'قرار فقط',
      'Operator Session':'جلسة المشغّل','Authenticate once for the isolated staging control room. The JWT is exchanged for a short-lived server-side session and is not persisted in browser storage.':'سجّل الدخول مرة واحدة إلى غرفة التحكم التجريبية المعزولة. يتم استبدال JWT بجلسة قصيرة العمر على الخادم ولا يتم حفظه في تخزين المتصفح.','Operator JWT':'رمز JWT للمشغّل','Paste short-lived operator JWT':'ألصق رمز JWT قصير العمر','Start Operator Session':'بدء جلسة المشغّل','Check Session':'فحص الجلسة','No session checked.':'لم يتم فحص أي جلسة.','Session cookie is HttpOnly + SameSite=Strict. The token is stored server-side in Redis. Production switching and outputs remain disabled.':'ملف تعريف ارتباط الجلسة HttpOnly وSameSite=Strict. يُحفظ الرمز على الخادم في Redis. يبقى التحويل ومخرجات الإنتاج معطلة.','JWT required':'رمز JWT مطلوب'
    },
    fr:{
      'Mission-Critical Operations':'Opérations critiques','Overview':'Vue d’ensemble','Live Studio':'Studio en direct','WebRTC Lab':'Laboratoire WebRTC','Channels':'Chaînes','Scheduler':'Planificateur','Media Library':'Médiathèque','Playout':'Diffusion','Encoder':'Encodeur','Distribution':'Distribution','NOC':'Centre NOC','Failover':'Basculement','Supervisor':'Superviseur','System Health':'Santé du système','Operations Overview':'Vue d’ensemble des opérations','Unified control surface for Broadcast Nexus staging.':'Surface de contrôle unifiée pour l’environnement de préproduction Broadcast Nexus.','STAGING':'PRÉPRODUCTION','PRODUCTION SWITCHING OFF':'BASCULEMENT PRODUCTION DÉSACTIVÉ','Services Up':'Services actifs','Unified integration snapshot':'Vue unifiée de l’intégration','Broadcast runtime state':'État d’exécution de diffusion','Monitoring control plane':'Plan de contrôle de supervision','Control Room':'Régie','Operational telemetry':'Télémétrie opérationnelle','Core Service Status':'État des services principaux','Waiting for staging runtime':'En attente de l’environnement de préproduction','Dashboard Integration API':'API d’intégration du tableau de bord','Unified staging health aggregation':'Agrégation unifiée de l’état de préproduction','Broadcast Supervisor':'Superviseur de diffusion','Runtime orchestration and recovery':'Orchestration et récupération','NOC API':'API NOC','Monitoring, incidents and health':'Supervision, incidents et état','Safety Envelope':'Périmètre de sécurité','Production outputs disabled':'Sorties de production désactivées','No live production switching in staging.':'Aucun basculement de production en direct en préproduction.','Readiness':'Préparation','Integration layer ready; VPS deployment pending.':'Couche d’intégration prête ; déploiement VPS en attente.','Broadcast Modules':'Modules de diffusion','Refresh Health':'Actualiser l’état','READY':'PRÊT','UNAVAILABLE':'INDISPONIBLE','UNKNOWN':'INCONNU','UP':'ACTIF','DOWN':'HORS SERVICE','OFF':'ARRÊT','SAFE':'SÛR','LOCKED':'VERROUILLÉ','OUTPUTS OFF':'SORTIES ARRÊTÉES','OUTPUTS DISABLED':'SORTIES DÉSACTIVÉES','CONTROL PLANE READY':'PLAN DE CONTRÔLE PRÊT','STAGING LOCKED':'PRÉPRODUCTION VERROUILLÉE','DECISION ONLY':'DÉCISION UNIQUEMENT','Operator Session':'Session opérateur','Operator JWT':'JWT opérateur','Paste short-lived operator JWT':'Collez le JWT opérateur à courte durée','Start Operator Session':'Démarrer la session opérateur','Check Session':'Vérifier la session','No session checked.':'Aucune session vérifiée.','JWT required':'JWT requis'
    }
  };

  const original=new WeakMap();
  function translateString(value,lang){
    if(lang==='en') return value;
    return (dict[lang]&&dict[lang][value])||value;
  }
  function translateNode(node,lang){
    if(node.nodeType===Node.TEXT_NODE){
      const raw=node.nodeValue;
      if(!raw||!raw.trim())return;
      if(!original.has(node)) original.set(node,raw);
      const base=original.get(node), trimmed=base.trim();
      const translated=translateString(trimmed,lang);
      node.nodeValue=base.replace(trimmed,translated);
      return;
    }
    if(node.nodeType!==Node.ELEMENT_NODE)return;
    if(['SCRIPT','STYLE','NOSCRIPT'].includes(node.tagName))return;
    if(node.hasAttribute('placeholder')){
      if(!node.dataset.i18nPlaceholderOriginal)node.dataset.i18nPlaceholderOriginal=node.getAttribute('placeholder')||'';
      node.setAttribute('placeholder',translateString(node.dataset.i18nPlaceholderOriginal,lang));
    }
    [...node.childNodes].forEach(child=>translateNode(child,lang));
  }
  function apply(lang){
    if(!supported.includes(lang))lang='ar';
    localStorage.setItem(STORAGE_KEY,lang);
    document.documentElement.lang=lang;
    document.documentElement.dir=lang==='ar'?'rtl':'ltr';
    document.body?.classList.toggle('iuoamc-rtl',lang==='ar');
    translateNode(document.body,lang);
    document.querySelectorAll('[data-lang-choice]').forEach(b=>b.classList.toggle('active',b.dataset.langChoice===lang));
    window.dispatchEvent(new CustomEvent('iuoamc:language',{detail:{lang}}));
  }
  function addSwitcher(){
    if(document.getElementById('iuoamcLanguageSwitcher'))return;
    const style=document.createElement('style');
    style.textContent=`#iuoamcLanguageSwitcher{position:fixed;z-index:99999;top:12px;right:14px;display:flex;gap:5px;padding:5px;border:1px solid rgba(214,170,75,.3);background:rgba(7,17,31,.94);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.25);direction:ltr}#iuoamcLanguageSwitcher button{border:1px solid #263a5a;background:#0d1a2d;color:#b9c7db;border-radius:8px;padding:6px 9px;font:600 11px/1 system-ui;cursor:pointer}#iuoamcLanguageSwitcher button.active{color:#f3cc76;border-color:rgba(214,170,75,.7);background:#18253a}.iuoamc-rtl .main,.iuoamc-rtl .card,.iuoamc-rtl .sidebar{text-align:right}.iuoamc-rtl .sidebar{border-right:0;border-left:1px solid var(--line,#20324f)}@media(max-width:680px){#iuoamcLanguageSwitcher{top:auto;bottom:12px;right:12px}}`;
    document.head.appendChild(style);
    const box=document.createElement('div');box.id='iuoamcLanguageSwitcher';box.setAttribute('aria-label','Language');
    [['ar','العربية'],['en','English'],['fr','Français']].forEach(([code,label])=>{const b=document.createElement('button');b.type='button';b.dataset.langChoice=code;b.textContent=label;b.onclick=()=>apply(code);box.appendChild(b)});
    document.body.appendChild(box);
  }
  document.addEventListener('DOMContentLoaded',()=>{addSwitcher();apply(localStorage.getItem(STORAGE_KEY)||'ar');const obs=new MutationObserver(()=>{const lang=localStorage.getItem(STORAGE_KEY)||'ar';translateNode(document.body,lang)});obs.observe(document.body,{childList:true,subtree:true});});
  window.IUOAMCI18N={setLanguage:apply,getLanguage:()=>localStorage.getItem(STORAGE_KEY)||'ar'};
})();
