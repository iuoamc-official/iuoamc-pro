(() => {
    const button = document.querySelector('[data-nav-toggle]');
    const navigation = document.querySelector('[data-navigation]');
    button?.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!open));
        navigation?.classList.toggle('open', !open);
    });

    document.querySelector('[data-verify-form]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const token = String(new FormData(form).get('token') || '').trim();
        if (token !== '') {
            window.location.assign(`${form.dataset.base}/${encodeURIComponent(token)}`);
        }
    });

    const aiLauncher = document.querySelector('[data-ai-launcher]');
    const aiPanel = document.querySelector('[data-ai-panel]');
    const aiClose = document.querySelector('[data-ai-close]');
    const aiForm = document.querySelector('[data-ai-form]');
    const aiMessages = document.querySelector('[data-ai-messages]');
    const aiQuestion = aiForm?.querySelector('textarea');

    const cleanAiText = (text) => String(text || '')
        .replace(/\\([@*_\[\]()])/g, '$1')
        .replace(/\*\*(.*?)\*\*/g, '$1');

    const toggleAi = (open) => {
        if (!aiPanel || !aiLauncher) return;
        aiPanel.hidden = !open;
        aiLauncher.setAttribute('aria-expanded', String(open));
        if (open) aiForm?.querySelector('textarea')?.focus();
    };

    const addMessage = (role, text, sources = []) => {
        const article = document.createElement('article');
        article.className = `ai-message ${role}`;
        const badge = document.createElement('span');
        badge.textContent = role === 'assistant' ? 'AI' : 'YOU';
        const paragraph = document.createElement('p');
        paragraph.textContent = cleanAiText(text);
        article.append(badge, paragraph);
        if (sources.length) {
            const list = document.createElement('nav');
            list.className = 'ai-sources';
            sources.forEach((source, index) => {
                const link = document.createElement('a');
                link.href = source.url;
                link.textContent = `[${index + 1}] ${source.title}`;
                list.append(link);
            });
            article.append(list);
        }
        aiMessages?.append(article);
        aiMessages?.scrollTo({ top: aiMessages.scrollHeight, behavior: 'smooth' });
        return article;
    };

    aiLauncher?.addEventListener('click', () => toggleAi(aiPanel?.hidden ?? true));
    aiClose?.addEventListener('click', () => toggleAi(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && aiPanel && !aiPanel.hidden) toggleAi(false);
    });

    aiQuestion?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            aiForm.requestSubmit();
        }
    });

    aiForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = String(new FormData(aiForm).get('question') || '').trim();
        if (question.length < 3) return;
        addMessage('visitor', question);
        aiForm.reset();
        const submit = aiForm.querySelector('button');
        submit.disabled = true;
        const pending = addMessage('assistant pending', '…');
        try {
            const response = await fetch(aiPanel.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ question, page_path: window.location.pathname }),
            });
            const payload = await response.json();
            pending.remove();
            addMessage('assistant', payload.answer || payload.message || 'Service unavailable.', payload.sources || []);
        } catch (_) {
            pending.remove();
            addMessage('assistant', aiPanel.dataset.error || 'Service unavailable.');
        } finally {
            submit.disabled = false;
            aiForm.querySelector('textarea')?.focus();
        }
    });
})();
