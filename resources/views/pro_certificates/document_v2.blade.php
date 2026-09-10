@php
    $diploma = $payload['catalog_snapshot']['layout'] === 'diploma';
    $typeName = $payload['catalog_snapshot']['names'][$language];
    $longName = mb_strlen($payload['recipient_name']) > 65;
@endphp
<!doctype html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><style>
body { color:#112b43; font-family:dejavusans; font-size:10pt; }
.frame { border:{{ $diploma ? '1.1' : '0.5' }}mm solid #b89943; padding:{{ $diploma ? '3mm 5mm' : '5mm 7mm' }}; }
.inside { border:0.2mm solid #e6dac1; padding:{{ $diploma ? '2.5mm 4mm' : '4mm 5mm' }}; }
table { border-collapse:collapse; width:100%; }
td { vertical-align:middle; }
.brand { width:45%; }
.arbitration-brand { width:22%; text-align:{{ $language === 'ar' ? 'right' : 'left' }}; }
.authority-brand { width:18%; text-align:center; }
.system-brand { width:22%; text-align:center; }
.issuer { width:38%; text-align:{{ $language === 'ar' ? 'left' : 'right' }}; font-size:9pt; line-height:1.45; }
.muted { color:#63788b; font-size:7.5pt; }
.masthead { border-bottom:0.2mm solid #e6dac1; }
.masthead td { padding-bottom:{{ $diploma ? '1.5mm' : '3mm' }}; }
.eyebrow { margin:{{ $diploma ? '1.5mm 0 0.5mm' : '3mm 0 1mm' }}; text-align:center; color:#8e712a; font-size:8.5pt; }
.title { margin:{{ $diploma ? '0.5mm 0 1mm' : '1mm 0 2mm' }}; text-align:center; font-weight:bold; font-size:{{ mb_strlen($payload['certificate_title']) > 65 ? ($diploma ? 18 : 20) : ($diploma ? 24 : 25) }}pt; line-height:1.15; }
.recipient-label { text-align:center; color:#63788b; font-size:{{ $diploma ? '8' : '9' }}pt; margin:{{ $diploma ? '1mm 0 0.5mm' : '2mm 0 1mm' }}; }
.recipient { text-align:center; font-size:{{ $longName ? 18 : ($diploma ? 21 : 23) }}pt; font-weight:bold; margin:{{ $diploma ? '0.5mm 0 1mm' : '1mm 0 2mm' }}; line-height:1.2; }
.program { text-align:center; font-size:{{ $diploma ? '10.5' : '11.5' }}pt; font-weight:bold; margin:{{ $diploma ? '1mm 0 0.5mm' : '2mm 0 1mm' }}; line-height:1.25; }
.specialization { text-align:center; color:#8e712a; font-size:{{ $diploma ? '9.5' : '10.5' }}pt; margin:{{ $diploma ? '0.5mm 0 1mm' : '1mm 0 2mm' }}; line-height:1.25; }
.statement { text-align:center; font-size:{{ $diploma ? '8.5' : '10' }}pt; line-height:{{ $diploma ? '1.25' : '1.4' }}; margin:{{ $diploma ? '1mm 3mm 1.5mm' : '2mm 5mm 3mm' }}; }
.dates { border-top:0.2mm solid #e6dac1; border-bottom:0.2mm solid #e6dac1; }
.dates td { padding:{{ $diploma ? '1.2mm' : '2mm' }}; text-align:center; font-size:8pt; width:33.333%; }
.footer-grid { margin-top:{{ $diploma ? '1.5mm' : '3mm' }}; }
.footer-grid td { width:33.333%; }
.signatory { font-size:10pt; font-weight:bold; border-top:0.3mm solid #b89943; padding-top:2mm; }
.number { text-align:center; font-size:8.5pt; line-height:1.6; }
.foot { font-size:{{ $diploma ? '6.4' : '6.8' }}pt; line-height:1.3; color:#63788b; margin-top:{{ $diploma ? '1mm' : '2mm' }}; text-align:center; }
.draft { text-align:center; color:#805e16; font-size:8pt; margin-bottom:2mm; }
.code { direction:ltr; font-family:dejavusans; }
</style></head>
<body><div class="frame"><div class="inside">
@if($draft)<div class="draft">{{ $labels['draft'] }}</div>@endif
<table class="masthead"><tr>
@if($diploma && $arbitrationLogo !== null && $authorityLogo !== null)
<td class="arbitration-brand"><img src="{{ $arbitrationLogo }}" style="width:40mm;height:auto" alt="ICGA — International Culinary &amp; Gastronomy Arbitration"></td>
<td class="authority-brand"><img src="{{ $authorityLogo }}" style="width:32mm;height:auto" alt="WSA-CA — World Supreme Authority for Culinary Arbitration"></td>
<td class="system-brand"><img src="{{ $logo }}" style="width:42mm;height:auto" alt="IUOAMC"></td>
@else
<td class="brand"><img src="{{ $logo }}" style="width:65mm;height:auto" alt="IUOAMC"></td>
@endif
<td class="issuer"><strong>{{ $payload['issuer']['legal_name'] }}</strong><br>
<span class="muted">{{ $payload['issuer']['jurisdiction'] }}
@if(!empty($payload['issuer']['registration_number']))<br>{{ $labels['registration'] }}: <span dir="ltr">{{ $payload['issuer']['registration_number'] }}</span>@endif
</span></td></tr></table>
<div class="eyebrow">{{ $typeName }}</div>
<div class="title">{{ $payload['certificate_title'] }}</div>
<div class="recipient-label">{{ $labels['recipient'] }}</div>
<div class="recipient">{{ $payload['recipient_name'] }}</div>
<div class="program">{{ $payload['program_title'] }}</div>
@if(!empty($payload['specialization']))<div class="specialization">{{ $labels['specialization'] }}: {{ $payload['specialization'] }}</div>@endif
<div class="statement">@foreach(explode("\n", $payload['statement']) as $line){{ $line }}@if(!$loop->last)<br>@endif @endforeach</div>
<table class="dates"><tr>
<td><span class="muted">{{ $labels['achievement'] }}</span><br><span dir="ltr">{{ $payload['achievement_date'] }}</span></td>
<td><span class="muted">{{ $labels['issued'] }}</span><br><span dir="ltr">{{ $draft ? '-' : substr($payload['issued_at'],0,10) }}</span></td>
<td>@if($payload['expires_on'])<span class="muted">{{ $labels['expiry'] }}</span><br><span dir="ltr">{{ $payload['expires_on'] }}</span>@else{{ $labels['no_expiry'] }}@endif</td>
</tr></table>
<table class="footer-grid"><tr>
<td><div class="signatory">{{ $payload['signatory_name'] }}</div><div class="muted">{{ $payload['signatory_title'] }}</div></td>
<td class="number"><span class="muted">{{ $labels['number'] }}</span><br><span class="code" dir="ltr">{{ $payload['certificate_number'] }}</span><br><span class="muted">{{ $labels['type_code'] }}: <span dir="ltr">{{ $payload['catalog_snapshot']['code'] }}</span></span></td>
<td style="text-align:center">@if(!$draft)<barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.75" disableborder="0" /><div class="muted">{{ $labels['verify'] }}</div>@else<div class="muted">{{ $labels['draft'] }}</div>@endif</td>
</tr></table>
<div class="foot">{{ $draft ? $labels['draft'] : $labels['footer'] }}</div>
</div></div></body></html>
