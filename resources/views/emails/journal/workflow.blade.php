<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><title>{{ __('journal.mail.subjects.'.$event, $payload) }}</title></head>
<body style="margin:0;background:#f3f6f8;color:#102536;font-family:Arial,sans-serif">
<div style="max-width:680px;margin:0 auto;padding:32px 18px">
    <div style="background:#071b2d;color:#fff;padding:22px 26px;border-radius:12px 12px 0 0"><strong>MCIJ</strong><br><span>{{ __('journal.title') }}</span></div>
    <div style="background:#fff;padding:28px 26px;border:1px solid #dbe3e8;border-top:0">
        <p>{{ __('journal.mail.greeting', ['name' => $payload['name'] ?? __('journal.author')]) }}</p>
        <p>{{ __('journal.mail.events.'.$event, $payload) }}</p>
        @if(!empty($payload['reason']))<div style="border-inline-start:4px solid #b88a36;padding:12px 16px;background:#fbf8f1;white-space:pre-line">{{ $payload['reason'] }}</div>@endif
        @if($event === 'submission_received')
            <p><strong>{{ __('journal.submission_code') }}:</strong> <bdi dir="ltr">{{ $payload['code'] }}</bdi><br><strong>{{ __('journal.tracking_token') }}:</strong> <bdi dir="ltr">{{ $payload['token'] }}</bdi></p>
            <p><a href="{{ $payload['tracking_url'] }}">{{ __('journal.track_submission') }}</a></p>
        @elseif(in_array($event, ['review_assigned', 'review_reminder', 'revision_received', 'new_submission_received', 'review_completed'], true))
            <p><a href="{{ $payload['workspace_url'] }}">{{ __('journal.open_editorial_record') }}</a></p>
        @elseif($event === 'article_published')
            <p><a href="{{ $payload['record_url'] }}">{{ __('journal.public_view') }}</a></p>
        @endif
        <p style="margin-top:28px;color:#506473;font-size:13px">{{ __('journal.mail.automated_notice') }}</p>
    </div>
</div>
</body>
</html>
