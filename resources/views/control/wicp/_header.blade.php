<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-wicp-1.0.0.css') }}">
<header class="wicp-hero">
    <img src="{{ asset('assets/brand/wicp-original.webp') }}" alt="{{ __('wicp.logo') }}" width="112" height="112">
    <div><span class="wicp-eyebrow">WORLD CENTRE FOR INTELLECTUAL PROTECTION</span><h1>{{ $heading }}</h1><p>{{ __('wicp.lead') }}</p></div>
</header>
<nav class="wicp-nav" aria-label="{{ __('wicp.navigation') }}">
    <a href="{{ route('wicp.index',['locale'=>app()->getLocale()]) }}" @if(request()->routeIs('wicp.index','wicp.show')) aria-current="page" @endif>{{ __('wicp.title') }}</a>
    @if(auth()->user()->canDo('wicp.register'))
    <a href="{{ route('wicp.programs.create',['locale'=>app()->getLocale()]) }}" @if(request()->routeIs('wicp.programs.*')) aria-current="page" @endif>{{ __('wicp.new_program') }}</a>
    @if(auth()->user()->canDo('certificates.view'))
    <a href="{{ route('wicp.certificates.create',['locale'=>app()->getLocale()]) }}" @if(request()->routeIs('wicp.certificates.*')) aria-current="page" @endif>{{ __('wicp.new_certificate') }}</a>
    @endif
    @endif
    @if(auth()->user()->canDo('certificates.view'))
    <a href="{{ route('certificates.index',['locale'=>app()->getLocale()]) }}">{{ __('wicp.certificates') }}</a>
    @endif
</nav>
@if(session('success'))
<div class="wicp-notice" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="wicp-notice wicp-alert" role="alert"><ul>
@foreach($errors->all() as $message)
<li>{{ $message }}</li>
@endforeach
</ul></div>
@endif
