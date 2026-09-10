<!doctype html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><style>
body { color:#0b2543; font-family:dejavusans; font-size:11pt; }
.frame { border:0.6mm solid #c7a53e; padding:6mm 8mm; }
table { border-collapse:collapse; width:100%; }
td { vertical-align:middle; }
.masthead { border-bottom:0.3mm solid #d9c989; padding-bottom:3mm; }
.issuer { text-align:{{ $language === 'ar' ? 'left' : 'right' }}; font-size:10pt; line-height:1.5; }
.muted { color:#60748a; font-size:8pt; }
.eyebrow { margin:4mm 0 1mm; text-align:center; color:#9c7620; font-size:9pt; }
.title { margin:1mm 0 2mm; text-align:center; font-weight:bold; font-size:24pt; line-height:1.3; }
.recipient-label { text-align:center; color:#65788a; font-size:10pt; margin:2mm 0 1mm; }
.recipient { text-align:center; font-size:{{ mb_strlen($payload['recipient_name']) > 80 ? 18 : 24 }}pt; font-weight:bold; margin:1mm 0 3mm; line-height:1.3; }
.program-label { text-align:center; font-size:8pt; color:#60748a; margin:0; }
.program { text-align:center; font-size:13pt; font-weight:bold; margin:1mm 0 3mm; line-height:1.4; }
.statement { text-align:center; font-size:11pt; line-height:1.6; margin:2mm 6mm 4mm; }
.dates { border-top:0.2mm solid #dce3eb; border-bottom:0.2mm solid #dce3eb; }
.dates td { width:33.333%; padding:2.5mm; text-align:center; font-size:9pt; }
.number { text-align:center; font-size:10pt; margin:3mm 0; }
.signatory { font-size:11pt; font-weight:bold; border-top:0.3mm solid #c7a53e; padding-top:2mm; }
.foot { font-size:7pt; line-height:1.5; color:#617487; margin-top:2mm; text-align:center; }
.draft { text-align:center; background:#fff5dd; color:#815b09; padding:2mm; font-size:9pt; margin-bottom:2mm; }
.code { direction:ltr; font-family:dejavusans; }
</style></head>
<body><div class="frame">
@if($draft)<div class="draft">{{ $labels['draft'] }}</div>@endif
<table class="masthead"><tr><td style="width:40%"><img src="{{ $logo }}" style="width:72mm; height:auto" alt="IUOAMC"></td>
<td class="issuer"><strong>{{ $payload['issuer']['legal_name'] }}</strong><br>
<span class="muted">{{ $payload['issuer']['jurisdiction'] }}
@if(!empty($payload['issuer']['registration_number']))<br>{{ $labels['registration'] }}: <span dir="ltr">{{ $payload['issuer']['registration_number'] }}</span>@endif
</span></td></tr></table>
<div class="eyebrow">{{ $labels['document'] }}</div>
<div class="title">{{ $payload['certificate_title'] }}</div>
<div class="recipient-label">{{ $labels['recipient'] }}</div>
<div class="recipient">{{ $payload['recipient_name'] }}</div>
<div class="program-label">{{ $labels['program'] }}</div>
<div class="program">{{ $payload['program_title'] }}</div>
<div class="statement">@foreach(explode("\n", $payload['statement']) as $line){{ $line }}@if(!$loop->last)<br>@endif @endforeach</div>
<table class="dates"><tr>
<td><span class="muted">{{ $labels['achievement'] }}</span><br><span dir="ltr">{{ $payload['achievement_date'] }}</span></td>
<td><span class="muted">{{ $labels['issued'] }}</span><br><span dir="ltr">{{ $draft ? '-' : substr($payload['issued_at'],0,10) }}</span></td>
<td><span class="muted">{{ $labels['expiry'] }}</span><br>{{ $payload['expires_on'] ?: $labels['no_expiry'] }}</td>
</tr></table>
<div class="number">{{ $labels['number'] }}: <span class="code" dir="ltr">{{ $payload['certificate_number'] }}</span></div>
<table><tr><td style="width:38%"><div class="signatory">{{ $payload['signatory_name'] }}</div><div class="muted">{{ $payload['signatory_title'] }}</div></td>
<td style="width:24%;text-align:center;color:#aa842c;font-size:8pt"><span dir="ltr">IUOAMC.PRO</span><br><span dir="ltr">{{ $payload['template_version'] }}</span></td>
<td style="width:38%;text-align:center">
@if(!$draft)<barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.8" disableborder="0" /><div class="muted">{{ $labels['verify'] }}</div>@else<div class="muted">{{ $labels['draft'] }}</div>@endif
</td></tr></table>
<div class="foot">{{ $draft ? $labels['draft'] : $labels['footer'] }}</div>
</div></body></html>
