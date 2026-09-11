<!doctype html>
<html><head><meta charset="utf-8"><style>
@page { margin:0; }
body { margin:0; color:#0b2942; font-family:dejavusans; }
.frame { height:187mm; border:1.2mm solid #b89024; padding:7mm 10mm; text-align:center; }
.inner { height:171mm; border:.25mm solid #d9c48b; padding:5mm 9mm; }
.logo { width:48mm; height:auto; }
.eyebrow { margin-top:2mm; font-size:9pt; color:#9a7216; letter-spacing:1.5px; }
h1 { margin:3mm 0 2mm; font-size:24pt; color:#071f35; }
.lead { color:#64748b; font-size:10pt; }
.name { margin:4mm 0 2mm; font-size:22pt; font-weight:bold; color:#071f35; }
.type { font-size:14pt; color:#9a7216; font-weight:bold; }
.statement { width:78%; margin:5mm auto; font-size:10pt; line-height:1.7; }
.meta { margin:5mm auto 3mm; border-top:.3mm solid #d9c48b; border-bottom:.3mm solid #d9c48b; padding:3mm; font-size:9pt; }
.photo { width:20mm; height:25mm; object-fit:cover; border:.5mm solid #b89024; }
.qr { font-size:7pt; color:#64748b; }
.number { font-size:9pt; direction:ltr; }
.footer { margin-top:3mm; font-size:7pt; color:#64748b; }
</style></head><body>
<div class="frame"><div class="inner">
<img class="logo" src="{{ $logo }}">
<div class="eyebrow">OFFICIAL CERTIFICATE OF MEMBERSHIP · شهادة عضوية رسمية</div>
<h1>Certificate of Membership</h1>
<div class="lead">This institutional certificate confirms that</div>
<div class="name"><bdi>{{ $payload['latin_name'] ?: $payload['full_name'] }}</bdi></div>
<div class="type"><bdi>{{ $payload['membership_type'] }}</bdi></div>
<div class="statement">is recorded as an approved member of <bdi>{{ $payload['organization']['display_name'] }}</bdi> for the validity period shown below. The current status is verified through the secure QR record.</div>
<table class="meta" width="88%"><tr>
<td>Valid from<br><bdi dir="ltr">{{ $payload['valid_from'] }}</bdi></td>
<td>Valid until<br><bdi dir="ltr">{{ $payload['valid_until'] }}</bdi></td>
<td>Membership number<br><bdi dir="ltr">{{ $payload['membership_number'] }}</bdi></td>
</tr></table>
<table width="88%" align="center"><"><tr>
<td width="25%"><img class="photo" src="{{ $photoPath }}"></td>
<td width="50%" class="number">Issued: {{ substr($payload['issued_at'],0,10) }}<br>{{ $payload['template_version'] }}<br>PAdES / X.509 digitally signed</td>
<td width="25%" class="qr"><barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.7" disableborder="0" /><br>Scan to verify</td>
</tr></table>
<div class="footer">The verification page is authoritative for the current membership status. Private application data is not published.</div>
</div></div>
</body></html>
