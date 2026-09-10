@php
    $typeName = $payload['catalog_snapshot']['names'][$language];
    $longName = mb_strlen($payload['recipient_name']) > 42;
    $longTitle = mb_strlen($payload['certificate_title']) > 72;
@endphp
<!doctype html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
@page { margin:7mm; }
body { margin:0; padding:0; color:#102b43; font-family:dejavusans; font-size:9pt; }
.sheet { position:fixed; top:0; left:0; width:280.5mm; height:193mm; overflow:hidden; }
.outer { position:fixed; top:0; left:0; width:280.5mm; height:193mm; border:1.1mm solid #b89943; }
.inner { position:fixed; top:3mm; left:3mm; width:274mm; height:186.5mm; border:0.2mm solid #e6dac1; }
.draft { position:fixed; top:2.5mm; left:70mm; width:140mm; text-align:center; color:#84691f; font-size:7pt; letter-spacing:.3pt; }
.masthead { position:fixed; top:7mm; left:9mm; width:262mm; height:30mm; border-bottom:.2mm solid #ded1b4; }
.masthead table { border-collapse:collapse; width:100%; height:29mm; }
.masthead td { vertical-align:middle; }
.brand-cell { width:19%; text-align:center; }
.issuer-cell { width:43%; text-align:{{ $language === 'ar' ? 'left' : 'right' }}; font-size:7.5pt; line-height:1.4; }
.muted { color:#63788b; font-size:6.7pt; }
.eyebrow { position:fixed; top:40mm; left:35mm; width:210mm; text-align:center; color:#8e712a; font-size:7.5pt; }
.title { position:fixed; top:46mm; left:20mm; width:240mm; text-align:center; font-weight:bold; font-size:{{ $longTitle ? '15' : '18' }}pt; line-height:1.05; }
.recipient-label { position:fixed; top:65mm; left:60mm; width:160mm; text-align:center; color:#63788b; font-size:7pt; }
.recipient { position:fixed; top:71mm; left:25mm; width:230mm; text-align:center; font-weight:bold; font-size:{{ $longName ? '16' : '20' }}pt; line-height:1.05; }
.program { position:fixed; top:84mm; left:25mm; width:230mm; text-align:center; font-weight:bold; font-size:9pt; line-height:1.1; }
.specialization { position:fixed; top:93mm; left:30mm; width:220mm; text-align:center; color:#8e712a; font-size:7.5pt; line-height:1.1; }
.statement { position:fixed; top:102mm; left:20mm; width:240mm; height:24mm; text-align:center; font-size:7.2pt; line-height:1.18; overflow:hidden; }
.dates { position:fixed; top:129mm; left:13mm; width:254mm; height:14mm; border-top:.2mm solid #e6dac1; border-bottom:.2mm solid #e6dac1; }
.dates table { width:100%; border-collapse:collapse; }
.dates td { width:33.333%; padding-top:2mm; text-align:center; font-size:7pt; }
.qr { position:fixed; top:148mm; left:13mm; width:32mm; text-align:center; }
.registry { position:fixed; top:149mm; left:49mm; width:83mm; text-align:center; font-size:7pt; line-height:1.45; }
.signatory-box { position:fixed; top:151mm; left:138mm; width:67mm; text-align:center; }
.signatory { font-size:9pt; font-weight:bold; border-top:.3mm solid #b89943; padding-top:1.5mm; }
.security-zone { position:fixed; top:146mm; left:214mm; width:57mm; height:40mm; }
.security-zone table { border-collapse:collapse; width:57mm; height:40mm; }
.nfc { width:15mm; text-align:center; color:#b58b24; font-size:6pt; line-height:1.1; vertical-align:middle; }
.nfc-mark { font-size:18pt; line-height:.8; font-weight:bold; }
.physical-seal { width:40mm; height:40mm; border:.25mm dashed #c7a23e; border-radius:20mm; }
.foot { position:fixed; bottom:3.5mm; left:48mm; width:158mm; text-align:center; color:#63788b; font-size:5.8pt; line-height:1.15; }
.code { direction:ltr; font-family:dejavusans; }
</style>
</head>
<body>
<div class="sheet"></div>
<div class="outer"></div>
<div class="inner"></div>
@if($draft)<div class="draft">{{ $labels['draft'] }}</div>@endif

<div class="masthead"><table><tr>
<td class="brand-cell"><img src="{{ $arbitrationLogo }}" style="width:26mm;height:26mm" alt="ICGA"></td>
<td class="brand-cell"><img src="{{ $authorityLogo }}" style="width:27mm;height:27mm" alt="WSA-CA"></td>
<td class="brand-cell"><img src="{{ $logo }}" style="width:31mm;height:auto" alt="IUOAMC"></td>
<td class="issuer-cell"><strong>{{ $payload['issuer']['legal_name'] }}</strong><br>
<span class="muted">{{ $payload['issuer']['jurisdiction'] }}
@if(!empty($payload['issuer']['registration_number']))<br>{{ $labels['registration'] }}: <span dir="ltr">{{ $payload['issuer']['registration_number'] }}</span>@endif
</span></td>
</tr></table></div>

<div class="eyebrow">{{ $typeName }}</div>
<div class="title">{{ $payload['certificate_title'] }}</div>
<div class="recipient-label">{{ $labels['recipient'] }}</div>
<div class="recipient">{{ $payload['recipient_name'] }}</div>
<div class="program">{{ $payload['program_title'] }}</div>
@if(!empty($payload['specialization']))<div class="specialization">{{ $labels['specialization'] }}: {{ $payload['specialization'] }}</div>@endif
<div class="statement">@foreach(explode("\n", $payload['statement']) as $line){{ $line }}@if(!$loop->last)<br>@endif @endforeach</div>

<div class="dates"><table><tr>
<td><span class="muted">{{ $labels['achievement'] }}</span><br><span dir="ltr">{{ $payload['achievement_date'] }}</span></td>
<td><span class="muted">{{ $labels['issued'] }}</span><br><span dir="ltr">{{ $draft ? '-' : substr($payload['issued_at'],0,10) }}</span></td>
<td>@if($payload['expires_on'])<span class="muted">{{ $labels['expiry'] }}</span><br><span dir="ltr">{{ $payload['expires_on'] }}</span>@else{{ $labels['no_expiry'] }}@endif</td>
</tr></table></div>

<div class="qr">@if(!$draft)<barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size=".64" disableborder="0" /><div class="muted">{{ $labels['verify'] }}</div>@else<div class="muted">{{ $labels['draft'] }}</div>@endif</div>

<div class="registry">
<span class="muted">{{ $labels['number'] }}</span><br>
<span class="code" dir="ltr">{{ $payload['certificate_number'] }}</span><br>
<span class="muted">{{ $labels['type_code'] }}: <span dir="ltr">{{ $payload['catalog_snapshot']['code'] }}</span></span>
</div>

<div class="signatory-box"><div class="signatory">{{ $payload['signatory_name'] }}</div><div class="muted">{{ $payload['signatory_title'] }}</div></div>

<div class="security-zone"><table><tr>
<td class="nfc"><div class="nfc-mark">◉)))</div><strong>NFC</strong><br>SECURED</td>
<td><svg xmlns="http://www.w3.org/2000/svg" width="40mm" height="40mm" viewBox="0 0 40 40" aria-label="40 mm physical gold seal placement">
<circle cx="20" cy="20" r="19.3" fill="none" stroke="#c7a23e" stroke-width=".28" stroke-dasharray="1.2 1.2"/>
<circle cx="20" cy="20" r="17.8" fill="none" stroke="#e6dac1" stroke-width=".18"/>
<text x="20" y="18.5" text-anchor="middle" font-family="DejaVu Sans" font-size="2.5" fill="#9d7b26">PHYSICAL GOLD SEAL</text>
<text x="20" y="22.5" text-anchor="middle" font-family="DejaVu Sans" font-size="3" font-weight="bold" fill="#9d7b26">40 mm</text>
</svg></td>
</tr></table></div>

<div class="foot">{{ $draft ? $labels['draft'] : $labels['footer'] }}</div>
</body>
</html>
