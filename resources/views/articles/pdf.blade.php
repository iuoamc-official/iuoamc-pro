<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:dejavusans;color:#13283a;line-height:1.75}h1{font-size:25px;line-height:1.4;color:#071d2e;margin:0 0 10px}h2{font-size:18px;color:#8b6914;border-bottom:1px solid #d8c89b;padding-bottom:4px;margin-top:22px}h3{font-size:15px;color:#163d59;margin-top:18px}p,li,blockquote{font-size:11px}ul{padding-inline-start:24px}blockquote{border-inline-start:3px solid #b78a24;padding-inline-start:12px;color:#42586a}.masthead{border-bottom:2px solid #b78a24;padding-bottom:10px;margin-bottom:22px}.masthead strong{font-size:15px}.meta{color:#5f7281;font-size:9px;margin:8px 0 18px}.cover{width:100%;max-height:290px;object-fit:cover;margin:0 0 20px}.source{border-top:1px solid #d8e0e6;margin-top:28px;padding-top:10px;font-size:9px;color:#5f7281}.footer{margin-top:24px;text-align:center;font-size:8px;color:#6d7d89}
    </style>
</head>
<body>
    <header class="masthead"><strong>{{ $siteProfile['legal_name'] }}</strong><br><span>IUOAMC · EDITORIAL</span></header>
    <h1>{{ $article->localized('title', $locale) }}</h1>
    <div class="meta">{{ $article->author_name }} · {{ $article->published_at?->format('Y-m-d') }} · {{ $article->section?->localized('name', $locale) }}</div>
    @if($cover)<img class="cover" src="{{ $cover }}" alt="">@endif
    <div>{!! $body !!}</div>
    @if($article->source_url)<div class="source">{{ __('public_site.articles.legacy_source') }}: {{ $article->source_url }}<br>{{ __('public_site.articles.originally_published') }} {{ $article->original_published_at?->format('Y-m-d') }}</div>@endif
    <div class="footer">{{ $siteProfile['contact_email'] }} · {{ url('/') }} · {{ $article->record_uuid }}</div>
</body>
</html>
