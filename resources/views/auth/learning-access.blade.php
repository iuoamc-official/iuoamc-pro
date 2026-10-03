<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>{{ __('learning.student_portal') }} — IUOAMC</title>
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-learning-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-learning-1.0.0.css')) }}">
</head>
<body class="learning-access-body">
<main class="learning learning-access">
<nav class="learning-access-top" aria-label="{{ __('learning.navigation') }}"><a href="{{ route('public.home',['locale'=>app()->getLocale()]) }}"><img class="learning-logo" src="{{ asset('assets/images/iuoamc-logo-gold-transparent.png') }}" alt="IUOAMC" width="210" height="85"></a><div class="learning-nav">
@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $code=>$label)<a href="{{ route('learning.access.index',['locale'=>$code]) }}" @if(app()->getLocale()===$code) aria-current="page" @endif>{{ $label }}</a>@endforeach
</div></nav>
<div class="learning-access-grid">
<section class="learning-access-story"><span class="learning-kicker">IUOAMC · {{ __('learning.track') }}</span><h1>{{ __('learning.student_portal') }}</h1><p>{{ __('learning.access_intro') }}</p><div class="learning-stats"><span>{{ __('learning.pages') }}</span><span>{{ __('learning.questions') }}</span><span>{{ __('learning.ten_lines') }}</span></div>
<img src="{{ asset('assets/images/learning/sensory-pathways.svg') }}" alt="{{ __('learning.portal_figure_alt') }}" width="900" height="460">
<ol class="learning-access-steps"><li>{{ __('learning.step_code') }}</li><li>{{ __('learning.step_profile') }}</li><li>{{ __('learning.step_study') }}</li></ol>
</section>
<section class="learning-card learning-access-form" aria-labelledby="activation-title">
<h2 id="activation-title">{{ __('learning.activate_title') }}</h2><p class="learning-muted">{{ __('learning.activate_hint') }}</p>
@if(session('success'))<p class="learning-feedback" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="learning-notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('learning.access.activate',['locale'=>app()->getLocale()]) }}">@csrf
<label for="name">{{ __('account.name') }}</label><input id="name" name="name" maxlength="255" autocomplete="name" required value="{{ old('name') }}">
<label for="email">{{ __('account.email') }}</label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required dir="ltr" value="{{ old('email') }}">
<div class="learning-form-grid"><div><label for="phone">{{ __('learning.phone') }}</label><input id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" required dir="ltr" value="{{ old('phone') }}"></div><div><label for="country">{{ __('learning.country') }}</label><input id="country" name="country" maxlength="100" autocomplete="country-name" required value="{{ old('country') }}"></div></div>
<label for="access-code">{{ __('learning.access_code') }}</label><input id="access-code" class="learning-code-input" name="access_code" maxlength="64" autocomplete="off" autocapitalize="characters" spellcheck="false" required dir="ltr" placeholder="IUOAMC-…"><small>{{ __('learning.code_email_hint') }}</small>
<label for="password">{{ __('account.password') }}</label><input id="password" type="password" name="password" autocomplete="new-password" maxlength="72" required><small>{{ __('learning.activation_password_hint') }}</small>
<label for="password-confirmation">{{ __('account.password_confirmation') }}</label><input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" maxlength="72"><small>{{ __('account.password_help') }}</small>
<label class="learning-option"><input type="checkbox" name="terms" value="1" required><span>{{ __('learning.learning_consent') }}</span></label>
<button class="learning-wide-button" type="submit">{{ __('learning.activate_button') }}</button>
</form>
<details class="learning-login" id="student-login" @if(old('login_form')) open @endif><summary>{{ __('learning.returning_student') }}</summary>
<form method="post" action="{{ route('learning.access.login',['locale'=>app()->getLocale()]) }}">@csrf<input type="hidden" name="login_form" value="1">
<label for="login-email">{{ __('account.email') }}</label><input id="login-email" name="email" type="email" autocomplete="username" maxlength="254" required dir="ltr" value="{{ old('login_form') ? old('email') : '' }}">
<label for="login-password">{{ __('account.password') }}</label><input id="login-password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
<p><button type="submit">{{ __('learning.sign_in') }}</button></p><a href="{{ route('password.request',['locale'=>app()->getLocale()]) }}">{{ __('account.forgot_password') }}</a>
</form></details>
@auth<p><a href="{{ route('account.learning.index',['locale'=>app()->getLocale()]) }}">{{ __('learning.continue_learning') }} ←</a></p>@endauth
</section></div>
<footer class="learning-access-footer">{{ __('learning.foundation_note') }}</footer>
</main>
</body></html>
