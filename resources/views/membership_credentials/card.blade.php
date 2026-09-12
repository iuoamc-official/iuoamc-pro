<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #10283d; font-family: dejavusans; }
        .card { position: relative; width: 85.6mm; height: 54mm; overflow: hidden; background: #fbfaf6; border: .45mm solid #b99536; }
        .rail { position: absolute; top: 0; left: 0; width: 5mm; height: 54mm; background: #10283d; }
        .gold-rail { position: absolute; top: 0; left: 5mm; width: .7mm; height: 54mm; background: #c7a343; }
        .header { height: 12.3mm; padding: 1.8mm 3.2mm 1.3mm 8.3mm; border-bottom: .25mm solid #d9c68f; }
        .logo { width: 27mm; height: auto; }
        .issuer { text-align: right; color: #10283d; font-size: 5.8pt; font-weight: bold; letter-spacing: .45px; }
        .issuer small { color: #8c6a1f; font-size: 4.4pt; font-weight: normal; letter-spacing: .2px; }
        .body { padding: 2.7mm 3.1mm 1.8mm 8.3mm; }
        .photo-frame { width: 18.5mm; height: 24.5mm; padding: .55mm; border: .35mm solid #b99536; background: #fff; }
        .photo { width: 17.4mm; height: 23.4mm; object-fit: cover; }
        .name { margin-top: .2mm; color: #10283d; font-size: 9.2pt; line-height: 1.12; font-weight: bold; }
        .name.long { font-size: 7.8pt; }
        .title { margin-top: 1.1mm; color: #9b751f; font-size: 6.1pt; font-weight: bold; }
        .class { margin-top: 1.2mm; font-size: 5.4pt; letter-spacing: .35px; text-transform: uppercase; }
        .number { margin-top: .7mm; color: #10283d; font-size: 7.4pt; font-weight: bold; font-family: dejavusansmono; }
        .dates { margin-top: 1.35mm; font-size: 4.8pt; color: #516274; }
        .qr { padding-top: .1mm; text-align: center; color: #516274; font-size: 4.2pt; line-height: 1.15; }
        .trust { position: absolute; right: 3.1mm; bottom: 2mm; left: 8.3mm; padding-top: 1.15mm; border-top: .22mm solid #d9c68f; }
        .trust-left { color: #536476; font-size: 4.05pt; line-height: 1.35; }
        .trust-right { color: #8c6a1f; text-align: right; font-size: 4.05pt; font-weight: bold; }
        .hash { position: absolute; right: 3.1mm; bottom: .45mm; left: 8.3mm; color: #7d8790; font-family: dejavusansmono; font-size: 2.45pt; letter-spacing: -.05px; white-space: nowrap; }
    </style>
</head>
<body>
@php($displayName = $payload['latin_name'] ?: $payload['full_name'])
<div class="card">
    <div class="rail"></div><div class="gold-rail"></div>
    <table class="header" width="100%">
        <tr>
            <td width="42%"><img class="logo" src="{{ $logo }}"></td>
            <td class="issuer">OFFICIAL MEMBERSHIP CARD<br><small>UK Company Registration No. {{ $payload['organization']['registration_number'] }}</small></td>
        </tr>
    </table>
    <div class="body">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="25%" valign="top"><div class="photo-frame"><img class="photo" src="{{ $photoPath }}"></div></td>
                <td width="54%" valign="top">
                    <div class="name {{ mb_strlen($displayName) > 28 ? 'long' : '' }}"><bdi>{{ $displayName }}</bdi></div>
                    @if($payload['professional_title'])<div class="title"><bdi>{{ $payload['professional_title'] }}</bdi></div>@endif
                    <div class="class"><bdi>{{ $payload['membership_type'] }}</bdi></div>
                    <div class="number"><bdi dir="ltr">{{ $payload['membership_number'] }}</bdi></div>
                    <div class="dates">VALID <bdi dir="ltr">{{ $payload['valid_from'] }}</bdi> — <bdi dir="ltr">{{ $payload['valid_until'] }}</bdi></div>
                </td>
                <td width="21%" class="qr" valign="top">
                    <barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.49" disableborder="0" /><br>SCAN TO VERIFY<br>LIVE STATUS
                </td>
            </tr>
        </table>
    </div>
    <table class="trust" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="trust-left">Record version {{ $payload['version'] }} · {{ $payload['template_version'] }}</td>
            <td class="trust-right">ELECTRONICALLY SIGNED · PAdES/X.509</td>
        </tr>
    </table>
    <div class="hash">CREDENTIAL DATA SHA-256 · {{ $payload['credential_data_sha256'] }}</div>
</div>
</body>
</html>
