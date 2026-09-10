<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale==='ar'?'rtl':'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>{{ __('wicp.public_title') }}</title><link rel="stylesheet" href="{{ asset('assets/css/iuoamc-wicp-1.0.0.css') }}"></head>
<body class="wicp-public"><main class="wicp-public-shell">
<header class="wicp-public-brand"><img src="{{ asset('assets/brand/wicp-original.webp') }}" alt="{{ __('wicp.logo') }}" width="132" height="132"><p>WORLD CENTRE FOR INTELLECTUAL PROTECTION</p><h1>{{ __('wicp.public_title') }}</h1></header>
@if($public)
<nav class="wicp-nav" aria-label="{{ __('wicp.language') }}">
@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $code=>$label)
<a href="{{ route('wicp.verify',['token'=>$token,'lang'=>$code]) }}" lang="{{ $code }}" @if($locale===$code) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
<section class="wicp-card"><span class="wicp-status {{ $public['status']==='registered'?'wicp-status-good':'wicp-status-review' }}">{{ __('wicp.statuses.'.$public['status']) }}</span><h2><bdi dir="ltr">{{ $public['reference'] }}</bdi></h2>
<dl class="wicp-facts"><div><dt>{{ __('wicp.kind') }}</dt><dd>{{ __('wicp.kinds.'.$public['kind']) }}</dd></div><div><dt>{{ __('wicp.program_title') }}</dt><dd>{{ $public['program_title'] }}</dd></div><div><dt>{{ __('wicp.program_code') }}</dt><dd><bdi dir="ltr">{{ $public['program_code'] }}</bdi></dd></div><div><dt>{{ __('wicp.program_version') }}</dt><dd><bdi dir="ltr">{{ $public['program_version'] }}</bdi></dd></div><div><dt>{{ __('wicp.registered_at') }}</dt><dd><bdi>{{ $public['registered_at'] }}</bdi></dd></div>
@if($public['parent_reference'])
<div><dt>{{ __('wicp.parent_program') }}</dt><dd><bdi dir="ltr">{{ $public['parent_reference'] }}</bdi></dd></div>
@endif
</dl><p class="wicp-note">{{ __('wicp.public_notice') }}</p><a class="wicp-button" href="{{ route('wicp.proof',['token'=>$token,'lang'=>$locale]) }}">{{ __('wicp.download_proof') }}</a></section>
@else
<section class="wicp-card"><h2>{{ __('wicp.unavailable') }}</h2><p>{{ __('wicp.unavailable_help') }}</p></section>
@endif
<footer class="wicp-public-footer">{{ __('wicp.privacy_notice') }}</footer>
</main></body></html>
