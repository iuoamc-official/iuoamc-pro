<!doctype html>
<html><head><meta charset="utf-8"><style>
@page { margin:0; }
body { margin:0; color:#0b2942; font-family:dejavusans; }
.card { position:relative; width:85.6mm; height:54mm; overflow:hidden; border:0.7mm solid #b89024; background:#fff; }
.top { height:12mm; background:#071f35; color:#fff; padding:1.6mm 3mm; }
.logo { width:24mm; height:auto; }
.title { text-align:right; font-size:7pt; letter-spacing:.5px; color:#d7b454; }
.body { padding:2.4mm 3mm; }
.photo { width:18mm; height:23mm; object-fit:cover; border:0.5mm solid #b89024; }
.name { font-size:10pt; font-weight:bold; margin-bottom:1mm; }
.type { color:#9a7216; font-size:7.5pt; font-weight:bold; }
.fact { font-size:6.5pt; margin-top:1.3mm; }
.qr { text-align:center; font-size:4.8pt; color:#64748b; }
.footer { position:absolute; left:3mm; right:3mm; bottom:1.8mm; border-top:.2mm solid #d9c48b; padding-top:1mm; font-size:4.8pt; color:#64748b; }
.code { direction:ltr; font-family:dejavusans; }
</style></head><body>
<div class="card">
<table class="top" width="100%"><tr><td><img class="logo" src="{{ $logo }}"></td><td class="title">OFFICIAL MEMBERSHIP CARD<br>بطاقة عضوية رسمية</td></tr></table>
<div class="body">
<table width="100%"><tr>
<td width="22%"><img class="photo" src="{{ $photoPath }}"></td>
<td width="56%" valign="top">
<div class="name"><bdi>{{ $payload['latin_name'] ?: $payload['full_name'] }}</bdi></div>
<div class="type"><bdi>{{ $payload['membership_type'] }}</bdi></div>
@if($payload['professional_title'])<div class="fact"><bdi>{{ $payload['professional_title'] }}</bdi></div>@endif
<div class="fact">No: <span class="code">{{ $payload['membership_number'] }}</span></div>
<div class="fact">Valid: <span class="code">{{ $payload['valid_from'] }} — {{ $payload['valid_until'] }}</span></div>
</td>
<td width="22%" class="qr"><barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.55" disableborder="0" /><br>SCAN TO VERIFY</td>
</tr></table>
</div>
<div class="footer">Cryptographically signed PAdES PDF · X.509 · {{ $payload['template_version'] }}</div>
</div>
</body></html>
