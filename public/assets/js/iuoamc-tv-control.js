(() => {
  const root = document.querySelector('[data-tv-control]');
  if (!root) return;

  const zone = root.dataset.timezone || 'Europe/London';
  const locale = root.dataset.locale || 'ar';
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
