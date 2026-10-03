<nav class="learning-nav" aria-label="{{ __('learning.title') }}">
<a href="{{ route('account.learning.index', ['locale'=>app()->getLocale()]) }}">{{ __('learning.lessons') }}</a>
<a href="{{ route('account.learning.exam', ['locale'=>app()->getLocale()]) }}">{{ __('learning.exam') }}</a>
<a href="{{ route('account.learning.assignment', ['locale'=>app()->getLocale()]) }}">{{ __('learning.assignment') }}</a>
</nav>
