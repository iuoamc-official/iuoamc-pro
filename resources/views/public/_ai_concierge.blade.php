<button class="ai-launcher" type="button" aria-expanded="false" aria-controls="iuoamc-ai" data-ai-launcher>
    <span class="ai-launcher-mark" aria-hidden="true">AI</span>
    <span>{{ __('public_site.ai.launcher') }}</span>
</button>
<section id="iuoamc-ai" class="ai-panel" aria-label="{{ __('public_site.ai.title') }}" hidden data-ai-panel data-endpoint="{{ route('public.ai.ask', ['locale' => app()->getLocale()]) }}" data-error="{{ __('public_site.ai.unavailable') }}">
    <header>
        <div><span>IUOAMC AI</span><strong>{{ __('public_site.ai.title') }}</strong></div>
        <button type="button" aria-label="{{ __('public_site.ai.close') }}" data-ai-close>×</button>
    </header>
    <div class="ai-messages" role="log" aria-live="polite" data-ai-messages>
        <article class="ai-message assistant"><span>AI</span><p>{{ __('public_site.ai.welcome') }}</p></article>
    </div>
    <form class="ai-form" data-ai-form>
        <label class="sr-only" for="iuoamc-ai-question">{{ __('public_site.ai.label') }}</label>
        <textarea id="iuoamc-ai-question" name="question" rows="2" minlength="3" maxlength="1000" required placeholder="{{ __('public_site.ai.placeholder') }}"></textarea>
        <button type="submit">{{ __('public_site.ai.send') }}</button>
    </form>
    <p class="ai-privacy">{{ __('public_site.ai.privacy') }}</p>
</section>
<script src="{{ asset('assets/js/iuoamc-public-1.0.0.js') }}?v={{ filemtime(public_path('assets/js/iuoamc-public-1.0.0.js')) }}" defer></script>
