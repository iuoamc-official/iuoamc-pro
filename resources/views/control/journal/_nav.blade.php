<nav class="journal-control-nav" aria-label="{{ __('journal.navigation') }}">
    <a class="{{ request()->routeIs('journal.control.articles.*') ? 'active' : '' }}" href="{{ route('journal.control.articles.index', ['locale' => app()->getLocale()]) }}">{{ __('journal.articles') }}</a>
    <a class="{{ request()->routeIs('journal.control.issues.*') ? 'active' : '' }}" href="{{ route('journal.control.issues.index', ['locale' => app()->getLocale()]) }}">{{ __('journal.issues') }}</a>
    <a target="_blank" rel="noopener" href="{{ route('journal.public.index', ['locale' => app()->getLocale()]) }}">{{ __('journal.public_journal') }} ↗</a>
</nav>
