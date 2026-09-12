<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #10283d; font-family: dejavusans; background: #fff; }
        .sheet { position: relative; height: 192mm; overflow: hidden; border: 1mm solid #b99536; background: #fbfaf6; }
        .navy { position: absolute; top: 0; left: 0; width: 16mm; height: 192mm; background: #10283d; }
        .gold { position: absolute; top: 0; left: 16mm; width: 1.2mm; height: 192mm; background: #c7a343; }
        .inner { margin: 5mm 6mm 5mm 22mm; height: 180mm; border: .25mm solid #d9c68f; padding: 6mm 9mm; text-align: center; }
        .logo { width: 47mm; height: auto; }
        .company { color: #586a7c; text-align: right; font-size: 7pt; line-height: 1.45; }
        .eyebrow { margin-top: 5mm; color: #9b751f; font-size: 8pt; font-weight: bold; letter-spacing: 2px; }
        h1 { margin: 2mm 0 1.2mm; color: #10283d; font-size: 25pt; font-weight: bold; }
        .rule { margin: 0 auto 3.5mm; width: 30mm; border-top: .5mm solid #c7a343; }
        .lead { color: #607284; font-size: 9.5pt; }
        .name { margin: 3.4mm 0 1.2mm; color: #10283d; font-size: 22pt; font-weight: bold; line-height: 1.1; }
        .name.long { font-size: 18pt; }
        .title { color: #9b751f; font-size: 11pt; font-weight: bold; }
        .statement { width: 86%; margin: 3.2mm auto 3mm; color: #30485d; font-size: 9pt; line-height: 1.7; }
        .meta { margin: 0 auto 4mm; padding: 2.4mm 0; border-top: .25mm solid #d9c68f; border-bottom: .25mm solid #d9c68f; font-size: 7.5pt; }
        .meta-label { color: #758291; font-size: 6.3pt; text-transform: uppercase; }
        .meta-value { margin-top: .8mm; color: #10283d; font-weight: bold; }
        .photo-frame { display: inline-block; padding: .7mm; border: .35mm solid #b99536; background: #fff; }
        .photo { width: 20mm; height: 25mm; object-fit: cover; }
        .signature { padding: 1.8mm 3mm; border-top: .25mm solid #b99536; color: #10283d; font-size: 8pt; line-height: 1.5; }
        .signature-name { color: #10283d; font-size: 10pt; font-weight: bold; }
        .signature-title { color: #687888; font-size: 6pt; }
        .signature-standard { margin-top: 1.4mm; color: #9b751f; font-size: 6pt; font-weight: bold; letter-spacing: .5px; }
        .qr { color: #607284; text-align: center; font-size: 6pt; line-height: 1.3; }
        .evidence { margin-top: 4mm; padding: 2mm 2.5mm; background: #f2eee2; border-left: .65mm solid #b99536; color: #536476; text-align: left; font-size: 5.3pt; line-height: 1.45; }
        .hash { margin-top: 1mm; color: #10283d; font-family: dejavusansmono; font-size: 4.35pt; word-spacing: -.2px; }
        .footer { position: absolute; right: 16mm; bottom: 7mm; left: 32mm; color: #74818d; text-align: center; font-size: 5.3pt; }
    </style>
</head>
<body>
@php($displayName = $payload['latin_name'] ?: $payload['full_name'])
<div class="sheet">
    <div class="navy"></div><div class="gold"></div>
    <div class="inner">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="50%" align="left"><img class="logo" src="{{ $logo }}"></td>
                <td width="50%" class="company"><bdi>{{ $payload['organization']['legal_name'] }}</bdi><br>UK Company Registration No. <bdi dir="ltr">{{ $payload['organization']['registration_number'] }}</bdi></td>
            </tr>
        </table>
        <div class="eyebrow">OFFICIAL MEMBERSHIP CREDENTIAL</div>
        <h1>Certificate of Membership</h1>
        <div class="rule"></div>
        <div class="lead">This institutional record certifies that</div>
        <div class="name {{ mb_strlen($displayName) > 34 ? 'long' : '' }}"><bdi>{{ $displayName }}</bdi></div>
        @if($payload['professional_title'])<div class="title"><bdi>{{ $payload['professional_title'] }}</bdi></div>@endif
        <div class="statement">is entered in the official IUOAMC membership register in the class shown below for the approved validity period. The professional title displayed is the title recorded for this membership and does not, by itself, confer a regulated qualification.</div>
        <table class="meta" width="92%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="28%"><div class="meta-label">Membership class</div><div class="meta-value"><bdi>{{ $payload['membership_type'] }}</bdi></div></td>
                <td width="25%"><div class="meta-label">Membership number</div><div class="meta-value"><bdi dir="ltr">{{ $payload['membership_number'] }}</bdi></div></td>
                <td width="17%"><div class="meta-label">Valid from</div><div class="meta-value"><bdi dir="ltr">{{ $payload['valid_from'] }}</bdi></div></td>
                <td width="17%"><div class="meta-label">Valid until</div><div class="meta-value"><bdi dir="ltr">{{ $payload['valid_until'] }}</bdi></div></td>
                <td width="13%"><div class="meta-label">Version</div><div class="meta-value">{{ $payload['version'] }}</div></td>
            </tr>
        </table>
        <table width="88%" align="center" cellpadding="0" cellspacing="0">
            <tr>
                <td width="24%" align="center"><div class="photo-frame"><img class="photo" src="{{ $photoPath }}"></div></td>
                <td width="52%" valign="middle">
                    <div class="signature">
                        Electronically authorised by<br>
                        <span class="signature-name">{{ $payload['electronic_signature']['name'] }}</span><br>
                        <span class="signature-title">{{ $payload['electronic_signature']['title'] }}</span><br>
                        <span class="signature-standard">CRYPTOGRAPHICALLY SIGNED · {{ $payload['electronic_signature']['standard'] }}</span>
                    </div>
                </td>
                <td width="24%" class="qr" valign="middle">
                    <barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.72" disableborder="0" /><br>SCAN TO VERIFY<br>LIVE REGISTRY STATUS
                </td>
            </tr>
        </table>
        <div class="evidence">
            <b>Digital evidence</b> · Issued <bdi dir="ltr">{{ $payload['issued_at'] }}</bdi> · {{ $payload['template_version'] }}<br>
            CREDENTIAL DATA SHA-256
            <div class="hash">{{ $payload['credential_data_sha256'] }}</div>
        </div>
    </div>
    <div class="footer">The QR verification record is authoritative for current status. The signed PDF is protected by a PAdES signature backed by an X.509 certificate.</div>
</div>
</body>
</html>
