<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; color: #10283d; font-family: dejavusans; }
        .sheet { position: fixed; top: 3mm; left: 3mm; width: 291mm; height: 204mm; background: #fbfaf6; }
        .outer { position: fixed; top: 3mm; left: 3mm; width: 291mm; height: 204mm; border: 1mm solid #b99536; }
        .inner { position: fixed; top: 7mm; left: 22mm; width: 268mm; height: 196mm; border: .25mm solid #d9c68f; }
        .navy { position: fixed; top: 3mm; left: 3mm; width: 15mm; height: 204mm; background: #10283d; }
        .gold { position: fixed; top: 3mm; left: 18mm; width: 1.2mm; height: 204mm; background: #c7a343; }
        .masthead { position: fixed; top: 11mm; left: 30mm; width: 250mm; height: 23mm; border-bottom: .25mm solid #d9c68f; }
        .masthead table { width: 100%; border-collapse: collapse; }
        .logo { width: 47mm; height: auto; }
        .company { color: #586a7c; text-align: right; font-size: 7pt; line-height: 1.45; }
        .eyebrow { position: fixed; top: 39mm; left: 50mm; width: 210mm; color: #9b751f; text-align: center; font-size: 8pt; font-weight: bold; letter-spacing: 2px; }
        .heading { position: fixed; top: 47mm; left: 43mm; width: 224mm; color: #10283d; text-align: center; font-size: 25pt; font-weight: bold; }
        .rule { position: fixed; top: 60mm; left: 133mm; width: 31mm; border-top: .5mm solid #c7a343; }
        .identity-photo-frame { position: fixed; top: 65mm; left: 47mm; width: 22mm; height: 27mm; overflow: hidden; border: .32mm solid #b99536; border-radius: 50%; background: #fbfaf6; }
        .identity-photo { display: block; width: 22mm; height: 27mm; margin: 0; border: 0; border-radius: 50%; background: #fbfaf6; }
        .lead { position: fixed; top: 65mm; left: 76mm; width: 184mm; color: #607284; text-align: center; font-size: 9pt; }
        .name { position: fixed; top: 72mm; left: 76mm; width: 184mm; color: #10283d; text-align: center; font-size: 22pt; font-weight: bold; line-height: 1.1; }
        .name.long { font-size: 18pt; }
        .title { position: fixed; top: 86mm; left: 76mm; width: 184mm; color: #9b751f; text-align: center; font-size: 11pt; font-weight: bold; }
        .statement { position: fixed; top: 95mm; left: 48mm; width: 212mm; height: 15mm; overflow: hidden; color: #30485d; text-align: center; font-size: 8.5pt; line-height: 1.45; }
        .meta { position: fixed; top: 115mm; left: 38mm; width: 232mm; height: 20mm; padding-top: 3mm; border-top: .25mm solid #d9c68f; border-bottom: .25mm solid #d9c68f; }
        .meta table { width: 100%; border-collapse: collapse; }
        .meta td { text-align: center; }
        .meta-label { color: #758291; font-size: 6.2pt; text-transform: uppercase; }
        .meta-value { margin-top: .7mm; color: #10283d; font-size: 7.5pt; font-weight: bold; }
        .nfc-seal { position: fixed; top: 147mm; left: 61mm; width: 17mm; height: 17mm; padding-top: 2.1mm; border: .4mm solid #b99536; border-radius: 50%; color: #9b751f; text-align: center; font-size: 4.5pt; font-weight: bold; line-height: 1.15; }
        .nfc-seal img { width: 8mm; height: 5.2mm; }
        .signature { position: fixed; top: 148mm; left: 94mm; width: 117mm; padding-top: 2mm; border-top: .25mm solid #b99536; color: #10283d; text-align: center; font-size: 7.5pt; line-height: 1.5; }
        .signature-name { font-size: 10pt; font-weight: bold; }
        .signature-title { color: #687888; font-size: 6pt; }
        .signature-standard { color: #9b751f; font-size: 6pt; font-weight: bold; letter-spacing: .4px; }
        .qr { position: fixed; top: 140mm; left: 231mm; width: 28mm; color: #607284; text-align: center; font-size: 5.5pt; line-height: 1.25; }
        .evidence { position: fixed; top: 177mm; left: 36mm; width: 236mm; height: 16mm; padding: 2mm 3mm; background: #f2eee2; border-left: .65mm solid #b99536; color: #536476; font-size: 5.2pt; line-height: 1.35; }
        .hash { margin-top: .8mm; color: #10283d; font-family: dejavusansmono; font-size: 4.25pt; }
        .footer { position: fixed; top: 198mm; left: 48mm; width: 212mm; color: #74818d; text-align: center; font-size: 5.2pt; }
    </style>
</head>
<body>
@php($displayName = $payload['latin_name'] ?: $payload['full_name'])
@php($validFrom = \Carbon\CarbonImmutable::parse($payload['valid_from'])->format('d-m-Y'))
@php($validUntil = \Carbon\CarbonImmutable::parse($payload['valid_until'])->format('d-m-Y'))
<div class="sheet"></div><div class="outer"></div><div class="inner"></div><div class="navy"></div><div class="gold"></div>
<div class="masthead"><table><tr><td width="50%"><img class="logo" src="{{ $logo }}"></td><td width="50%" class="company"><bdi>{{ $payload['organization']['legal_name'] }}</bdi><br>UK Company Registration No. <bdi dir="ltr">{{ $payload['organization']['registration_number'] }}</bdi></td></tr></table></div>
<div class="eyebrow">OFFICIAL MEMBERSHIP CREDENTIAL</div>
<div class="heading">Certificate of Membership</div>
<div class="rule"></div>
<div class="identity-photo-frame"><img class="identity-photo" src="{{ $photoPath }}"></div>
<div class="lead">This institutional record certifies that</div>
<div class="name {{ mb_strlen($displayName) > 34 ? 'long' : '' }}"><bdi>{{ $displayName }}</bdi></div>
@if($payload['professional_title'])<div class="title"><bdi>{{ $payload['professional_title'] }}</bdi></div>@endif
<div class="statement">is entered in the official IUOAMC membership register for the approved validity period. The professional title displayed is the title recorded for this membership and does not, by itself, confer a regulated qualification.</div>
<div class="meta"><table><tr>
    <td width="28%"><div class="meta-label">Membership class</div><div class="meta-value"><bdi>{{ $payload['membership_type'] }}</bdi></div></td>
    <td width="25%"><div class="meta-label">Membership number</div><div class="meta-value"><bdi dir="ltr">{{ $payload['membership_number'] }}</bdi></div></td>
    <td width="17%"><div class="meta-label">Valid from</div><div class="meta-value"><bdi dir="ltr">{{ $validFrom }}</bdi></div></td>
    <td width="17%"><div class="meta-label">Valid until</div><div class="meta-value"><bdi dir="ltr">{{ $validUntil }}</bdi></div></td>
    <td width="13%"><div class="meta-label">Version</div><div class="meta-value">{{ $payload['version'] }}</div></td>
</tr></table></div>
<div class="nfc-seal"><img src="{{ $nfc }}"><br>NFC<br>ENABLED</div>
<div class="signature">Electronically authorised by<br><span class="signature-name">{{ $payload['electronic_signature']['name'] }}</span><br><span class="signature-title">{{ $payload['electronic_signature']['title'] }}</span><br><span class="signature-standard">CRYPTOGRAPHICALLY SIGNED · {{ $payload['electronic_signature']['standard'] }}</span></div>
<div class="qr"><barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.72" disableborder="0" /><br>SCAN TO VERIFY<br>LIVE REGISTRY STATUS</div>
<div class="evidence"><b>Digital evidence</b> · Issued <bdi dir="ltr">{{ $payload['issued_at'] }}</bdi> · {{ $payload['template_version'] }}<br>CREDENTIAL DATA SHA-256<div class="hash">{{ $payload['credential_data_sha256'] }}</div></div>
<div class="footer">The QR verification record is authoritative for current status. This PDF is protected by a PAdES signature backed by an X.509 certificate.</div>
</body>
</html>
