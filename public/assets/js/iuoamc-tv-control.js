(() => {
  const root = document.querySelector('[data-tv-control]');
  if (!root) return;

  const zone = root.dataset.timezone || 'Europe/London';
  const locale = root.dataset.locale || 'ar';
  const statusUrl = root.dataset.statusUrl || '';
  const clock = root.querySelector('[data-tv-clock]');
  const date = root.querySelector('[data-tv-date]');

  const tick = () => {
    const now = new Date();
    const language = locale === 'ar' ? 'ar-GB' : locale;
    if (clock) {
      clock.textContent = new Intl.DateTimeFormat(language, {
        timeZone: zone,
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
      }).format(now);
    }
    if (date) {
      date.textContent = new Intl.DateTimeFormat(language, {
        timeZone: zone,
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
      }).format(now);
    }
  };
  tick();
  window.setInterval(tick, 1000);

  const nexusState = root.querySelector('[data-tv-nexus-state]');
  const nexusLed = root.querySelector('[data-tv-nexus-led]');
  const lastCheck = root.querySelector('[data-tv-last-check]');
  const nowTitle = root.querySelector('[data-tv-now-title]');
  const nowSubtitle = root.querySelector('[data-tv-now-subtitle]');
  const nextTitle = root.querySelector('[data-tv-next-title]');
  const nextSubtitle = root.querySelector('[data-tv-next-subtitle]');
  const epgRoot = root.querySelector('[data-tv-epg]');
  const epgSource = root.querySelector('[data-tv-epg-source]');

  const setProgram = (titleEl, subtitleEl, program) => {
    if (!program?.title || !titleEl || !subtitleEl) return;
    titleEl.textContent = program.title;
    subtitleEl.textContent = program.subtitle || program.start || '';
  };

  const renderEpg = (items) => {
    if (!epgRoot || !Array.isArray(items) || items.length === 0) return;
    epgRoot.replaceChildren(...items.map((item, index) => {
      const row = document.createElement('div');
      row.className = 'tv-epg-row';

      const time = document.createElement('div');
      time.className = 'tv-epg-time';
      time.textContent = item.start || (index === 0 ? 'LIVE' : `+ ${String(index + 1).padStart(2, '0')}`);

      const title = document.createElement('div');
      title.className = 'tv-epg-title';
      const strong = document.createElement('b');
      strong.textContent = item.title || 'Untitled';
      const small = document.createElement('small');
      small.textContent = item.subtitle || '';
      title.append(strong, small);

      const badge = document.createElement('span');
      badge.className = `tv-badge ${index === 0 ? 'ready' : 'locked'}`;
      badge.textContent = item.status || (index === 0 ? 'NOW' : 'SCHEDULED');

      row.append(time, title, badge);
      return row;
    }));
  };

  const refreshStatus = async () => {
    if (!statusUrl) return;
    try {
      const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();

      if (nexusState) nexusState.textContent = data.nexus?.online ? 'Online' : (data.nexus?.state || 'Unavailable');
      if (nexusLed) nexusLed.classList.toggle('ok', Boolean(data.nexus?.online));
      if (lastCheck) lastCheck.textContent = data.checked_at ? `Last check ${new Date(data.checked_at).toLocaleTimeString()}` : 'Read-only';

      setProgram(nowTitle, nowSubtitle, data.now);
      setProgram(nextTitle, nextSubtitle, data.next);
      renderEpg(data.epg);
      if (epgSource) epgSource.textContent = data.nexus?.online ? 'Live Nexus status feed' : 'Read-only preview';
    } catch (error) {
      if (nexusState) nexusState.textContent = locale === 'ar' ? 'غير متاح' : 'Unavailable';
      if (nexusLed) nexusLed.classList.remove('ok');
      if (lastCheck) lastCheck.textContent = locale === 'ar' ? 'تعذر التحديث' : 'Update failed';
    }
  };

  refreshStatus();
  window.setInterval(refreshStatus, 15000);

  const button = root.querySelector('[data-tv-ai-submit]');
  const question = root.querySelector('[data-tv-ai-question]');
  const output = root.querySelector('[data-tv-ai-output]');

  button?.addEventListener('click', async () => {
    const q = question?.value?.trim() || '';
    if (!output || q.length < 3) {
      if (output) output.textContent = locale === 'ar' ? 'اكتب سؤالاً واضحاً.' : 'Enter a clear question.';
      return;
    }

    button.disabled = true;
    output.textContent = locale === 'ar' ? 'جارٍ التحليل…' : 'Analysing…';

    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const response = await fetch(`/${locale}/ai/ask`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ question: q, page_path: `/${locale}/control/tv` }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
      output.textContent = data.answer || (locale === 'ar' ? 'لم يتم إرجاع إجابة.' : 'No answer returned.');
    } catch (error) {
      output.textContent = (locale === 'ar' ? 'تعذر تشغيل المساعد الآن: ' : 'Assistant unavailable: ') + error.message;
    } finally {
      button.disabled = false;
    }
  });
})();
