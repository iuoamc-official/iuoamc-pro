(()=>{
  const CHANNEL={
    name:'IUOAMC TV',
    legalOwner:'INTERNATIONAL UNION OF ARAB MASTER CHEFS LTD',
    companyNo:'16649793',
    ukprn:'10099301',
    ipoRef:'UK00004435018',
    ipoJournal:'Trade Marks Journal 2026/036',
    ipoPublished:'4 September 2026',
    oppositionDeadline:'4 November 2026',
    classes:'38, 41',
    legacyUrl:'https://platform-iuoamc.uk/tv'
  };

  const copy={
    ar:{
      heading:'هوية القناة الرسمية',
      status:'قيد النشر لدى UK IPO',
      owner:'المالك القانوني',
      company:'رقم الشركة البريطانية',
      ukprn:'الرقم المرجعي لمقدم التعليم UKPRN',
      ipo:'مرجع العلامة لدى UK IPO',
      journal:'النشر الرسمي',
      published:'تاريخ النشر',
      opposition:'نهاية فترة الاعتراض',
      classes:'فئات العلامة',
      legacy:'المصدر القديم للقناة',
      note:'IUOAMC TV مملوكة قانونيًا للاتحاد الدولي ماستر شيف العرب. طلب العلامة منشور حاليًا في مجلة العلامات البريطانية ضمن فترة الاعتراض، ولا يُعرض على أنه تسجيل نهائي قبل اكتمال إجراءات UK IPO.',
      logoPending:'الشعار الرسمي',
      staging:'بيئة اختبار — مخرجات الإنتاج متوقفة'
    },
    en:{
      heading:'Official Channel Identity',
      status:'Published at UK IPO',
      owner:'Legal owner',
      company:'UK company number',
      ukprn:'UK Provider Reference Number',
      ipo:'UK IPO trade mark reference',
      journal:'Official publication',
      published:'Publication date',
      opposition:'Opposition deadline',
      classes:'Trade mark classes',
      legacy:'Legacy channel source',
      note:'IUOAMC TV is legally owned by the International Union of Arab Master Chefs Ltd. The trade mark application is currently published in the UK Trade Marks Journal and remains within the opposition period; it is not presented here as a completed registration before UK IPO procedures conclude.',
      logoPending:'Official logo',
      staging:'STAGING — production outputs disabled'
    },
    fr:{
      heading:'Identité officielle de la chaîne',
      status:'Publié auprès de l’UK IPO',
      owner:'Propriétaire légal',
      company:'Numéro de société au Royaume-Uni',
      ukprn:'Numéro de référence UKPRN',
      ipo:'Référence de marque UK IPO',
      journal:'Publication officielle',
      published:'Date de publication',
      opposition:'Fin de la période d’opposition',
      classes:'Classes de marque',
      legacy:'Source historique de la chaîne',
      note:'IUOAMC TV appartient légalement à International Union of Arab Master Chefs Ltd. La demande de marque est actuellement publiée au journal britannique des marques et demeure dans la période d’opposition; elle n’est pas présentée comme un enregistrement définitif avant la fin de la procédure UK IPO.',
      logoPending:'Logo officiel',
      staging:'PRÉPRODUCTION — sorties de production désactivées'
    }
  };

  function lang(){
    const x=localStorage.getItem('iuoamc_nexus_lang')||document.documentElement.lang||'ar';
    return ['ar','en','fr'].includes(x)?x:'ar';
  }

  function render(code=lang()){
    const panel=document.getElementById('channels');
    if(!panel)return;
    const t=copy[code]||copy.ar;
    panel.dataset.i18nSkip='true';
    panel.innerHTML=`
      <div class="channel-identity-shell" dir="${code==='ar'?'rtl':'ltr'}">
        <div class="channel-hero card">
          <div class="channel-logo-wrap">
            <img class="channel-logo-img" src="/branding/iuoamc-tv-logo.png" alt="IUOAMC TV" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
            <div class="channel-logo-fallback" style="display:none" aria-label="IUOAMC TV"><span>IUOAMC</span><strong>TV</strong></div>
          </div>
          <div class="channel-hero-copy">
            <div class="channel-kicker">${t.heading}</div>
            <h3>${CHANNEL.name}</h3>
            <div class="channel-status">${t.status}</div>
            <p>${t.note}</p>
          </div>
        </div>
        <div class="channel-grid">
          ${row(t.owner,CHANNEL.legalOwner)}
          ${row(t.company,CHANNEL.companyNo)}
          ${row(t.ukprn,CHANNEL.ukprn)}
          ${row(t.ipo,CHANNEL.ipoRef,true)}
          ${row(t.journal,CHANNEL.ipoJournal)}
          ${row(t.published,CHANNEL.ipoPublished)}
          ${row(t.opposition,CHANNEL.oppositionDeadline)}
          ${row(t.classes,CHANNEL.classes)}
          <div class="channel-field channel-field-wide"><span>${t.legacy}</span><a href="${CHANNEL.legacyUrl}" target="_blank" rel="noopener">${CHANNEL.legacyUrl}</a></div>
        </div>
        <div class="channel-safety">${t.staging}</div>
      </div>`;
  }

  function row(label,value,important=false){
    return `<div class="channel-field${important?' important':''}"><span>${label}</span><strong>${value}</strong></div>`;
  }

  function installStyle(){
    if(document.getElementById('iuoamcChannelIdentityStyle'))return;
    const s=document.createElement('style');
    s.id='iuoamcChannelIdentityStyle';
    s.textContent=`
      .channel-identity-shell{display:grid;gap:14px}.channel-hero{display:flex;align-items:center;gap:22px;padding:22px!important;border-color:rgba(214,170,75,.35)!important}.channel-logo-wrap{width:150px;height:150px;flex:0 0 150px;display:grid;place-items:center}.channel-logo-img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 14px 28px rgba(0,0,0,.28))}.channel-logo-fallback{width:142px;height:142px;border-radius:50%;border:5px solid #d6aa4b;background:radial-gradient(circle at 35% 30%,#173a70,#071e46 70%);color:#f4d06f;place-items:center;align-content:center;text-align:center;box-shadow:inset 0 0 0 2px #ffe99a,0 14px 30px rgba(0,0,0,.28)}.channel-logo-fallback span{font-size:17px;font-weight:800;letter-spacing:.08em}.channel-logo-fallback strong{font-size:32px;line-height:1;margin-top:4px}.channel-hero-copy{min-width:0}.channel-kicker{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#d6aa4b}.channel-hero h3{font-size:30px;margin:6px 0 8px}.channel-status{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid rgba(214,170,75,.4);background:rgba(214,170,75,.08);color:#f3cc76;font-size:11px;font-weight:700}.channel-hero p{max-width:850px;color:#aebed3;font-size:13px;line-height:1.7;margin:12px 0 0}.channel-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.channel-field{background:#0b1728;border:1px solid #20324f;border-radius:12px;padding:13px;min-width:0}.channel-field span{display:block;color:#95a5bc;font-size:10px;text-transform:uppercase;letter-spacing:.07em;margin-bottom:7px}.channel-field strong,.channel-field a{font-size:13px;color:#eef4ff;overflow-wrap:anywhere}.channel-field.important{border-color:rgba(214,170,75,.45);background:rgba(214,170,75,.045)}.channel-field.important strong{color:#f3cc76}.channel-field-wide{grid-column:1/-1}.channel-field a{color:#7fb7ff;text-decoration:none}.channel-field a:hover{text-decoration:underline}.channel-safety{padding:10px 12px;border-radius:10px;border:1px solid rgba(24,181,123,.25);color:#7fe0bb;background:rgba(24,181,123,.055);font-size:11px}@media(max-width:760px){.channel-hero{flex-direction:column;text-align:center}.channel-grid{grid-template-columns:1fr}.channel-field-wide{grid-column:auto}.channel-logo-wrap{width:128px;height:128px;flex-basis:128px}}
    `;
    document.head.appendChild(s);
  }

  document.addEventListener('DOMContentLoaded',()=>{installStyle();render();});
  window.addEventListener('iuoamc:language',e=>render(e.detail?.lang||lang()));
  window.IUOAMCChannelIdentity={render,data:{...CHANNEL}};
})();
