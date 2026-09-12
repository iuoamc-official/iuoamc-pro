<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; background: #ffffff; color: #132947; font-family: dejavusans; }
        .canvas { position: fixed; top: 0; left: 0; width: 85.6mm; height: 54mm; background: #fbfaf6; }
        .top { position: fixed; top: 0; left: 0; width: 85.6mm; height: 11.7mm; background: #172e57; }
        .gold-line { position: fixed; top: 11.7mm; left: 0; width: 85.6mm; height: .75mm; background: #c6a13c; }
        .bottom { position: fixed; top: 48.8mm; left: 0; width: 85.6mm; height: 5.2mm; background: #172e57; }
        .bottom-gold { position: fixed; top: 48.05mm; left: 0; width: 85.6mm; height: .75mm; background: #c6a13c; }
        .brand { position: fixed; top: 1.7mm; left: 3.2mm; width: 25.2mm; height: 8.2mm; }
        .brand img { width: 25.2mm; height: auto; }
        .card-label { position: fixed; top: 2.25mm; left: 30mm; width: 52.3mm; color: #ffffff; text-align: right; font-size: 6pt; font-weight: bold; letter-spacing: .55px; }
        .registration { position: fixed; top: 6.4mm; left: 30mm; width: 52.3mm; color: #e3c768; text-align: right; font-size: 4.25pt; }
        .photo-frame { position: fixed; top: 15.8mm; left: 4mm; width: 16.6mm; height: 20.6mm; overflow: hidden; padding: 0; border: .32mm solid #c6a13c; border-radius: 50%; background: #fbfaf6; }
        .photo { display: block; width: 16.6mm; height: 20.6mm; margin: 0; border: 0; border-radius: 50%; background: #fbfaf6; }
        .identity { position: fixed; top: 14.7mm; left: 22.5mm; width: 45.2mm; height: 27.5mm; }
        .name { color: #132947; font-size: 9pt; line-height: 1.12; font-weight: bold; }
        .name.long { font-size: 7.6pt; }
        .title { margin-top: 1.15mm; color: #9a731e; font-size: 5.7pt; line-height: 1.23; font-weight: bold; }
        .class-label { margin-top: 1.35mm; color: #687687; font-size: 3.8pt; letter-spacing: .45px; }
        .class-value { margin-top: .35mm; color: #132947; font-size: 5.2pt; line-height: 1.15; font-weight: bold; }
        .number { margin-top: 1.05mm; color: #132947; font-family: dejavusansmono; font-size: 6.25pt; font-weight: bold; }
        .dates { margin-top: .85mm; color: #536273; font-size: 4.35pt; }
        .qr { position: fixed; top: 15.1mm; left: 69.2mm; width: 13.1mm; color: #536273; text-align: center; font-size: 3.45pt; line-height: 1.15; }
        .verification-rule { position: fixed; top: 37.2mm; left: 24.5mm; width: 57.7mm; border-top: .22mm solid #d7c588; }
        .nationality { position: fixed; top: 38.25mm; left: 24.5mm; width: 18mm; color: #536273; font-size: 3.8pt; font-weight: bold; }
        .edition { position: fixed; top: 40.15mm; left: 24.5mm; width: 29mm; color: #657384; font-size: 3.35pt; }
        .trust-right { position: fixed; top: 38.25mm; left: 45mm; width: 37.2mm; color: #8e6918; text-align: right; font-size: 3.7pt; font-weight: bold; }
        .nfc { position: fixed; top: 49.55mm; left: 31.2mm; width: 23.2mm; height: 3.7mm; color: #e3c768; text-align: center; font-size: 3.7pt; font-weight: bold; line-height: 3.7mm; }
        .nfc img { width: 4.8mm; height: 3.2mm; vertical-align: middle; }
        .hash-label { position: fixed; top: 43.55mm; left: 3.2mm; color: #7b8794; font-size: 3pt; }
        .hash { position: fixed; top: 45.05mm; left: 3.2mm; width: 79.2mm; color: #344b61; font-family: dejavusansmono; font-size: 2.65pt; letter-spacing: -.08px; white-space: nowrap; }
        .footer-left { position: fixed; top: 50.25mm; left: 3.2mm; width: 27.5mm; color: #ffffff; font-size: 3.45pt; font-weight: bold; }
        .footer-right { position: fixed; top: 50.25mm; left: 55mm; width: 27.4mm; color: #e3c768; text-align: right; font-size: 3.45pt; font-weight: bold; }
        .preview-mark { position: fixed; top: 12.65mm; left: 2mm; width: 81.6mm; color: #9b3a2e; text-align: center; font-size: 3.5pt; font-weight: bold; letter-spacing: .5px; }
        .back-canvas { position: fixed; top: 0; left: 0; width: 85.6mm; height: 54mm; background: #172e57; }
        .back-panel { position: fixed; top: 3.2mm; left: 3.2mm; width: 79.2mm; height: 47.6mm; background: #fbfaf6; border: .35mm solid #c6a13c; }
        .back-brand { position: fixed; top: 5mm; left: 6mm; width: 27mm; height: 8.5mm; }
        .back-brand img { width: 27mm; height: auto; }
        .back-heading { position: fixed; top: 6mm; left: 35mm; width: 44mm; color: #132947; text-align: right; font-size: 6.2pt; font-weight: bold; }
        .back-registration { position: fixed; top: 10.1mm; left: 35mm; width: 44mm; color: #9a731e; text-align: right; font-size: 4pt; }
        .back-rule { position: fixed; top: 14.4mm; left: 6mm; width: 73.6mm; border-top: .25mm solid #c6a13c; }
        .back-qr { position: fixed; top: 17.1mm; left: 6.4mm; width: 16.4mm; color: #536273; text-align: center; font-size: 3.6pt; line-height: 1.15; }
        .back-details { position: fixed; top: 17.1mm; left: 25.8mm; width: 53.4mm; color: #132947; font-size: 4.25pt; line-height: 1.38; }
        .back-number-label { color: #687687; font-size: 3.5pt; letter-spacing: .35px; }
        .back-number { margin-top: .6mm; color: #132947; font-family: dejavusansmono; font-size: 6pt; font-weight: bold; }
        .back-notice { margin-top: 2.1mm; color: #536273; font-size: 4pt; line-height: 1.42; }
        .back-nfc { margin-top: 2mm; color: #9a731e; font-size: 4.3pt; font-weight: bold; }
        .back-nfc img { width: 5.5mm; height: 3.7mm; vertical-align: middle; }
        .back-trust { position: fixed; top: 39.7mm; left: 6mm; width: 73.6mm; padding-top: 1.25mm; border-top: .22mm solid #d7c588; color: #536273; text-align: center; font-size: 3.55pt; line-height: 1.35; }
        .back-contact { position: fixed; top: 47.35mm; left: 6mm; width: 73.6mm; color: #132947; text-align: center; font-size: 3.8pt; font-weight: bold; }
    </style>
</head>
<body>
@php($displayName = $payload['latin_name'] ?: $payload['full_name'])
@php($validFrom = \Carbon\CarbonImmutable::parse($payload['valid_from'])->format('d-m-Y'))
@php($validUntil = \Carbon\CarbonImmutable::parse($payload['valid_until'])->format('d-m-Y'))
<div class="canvas"></div>
<div class="top"></div>
<div class="gold-line"></div>
@if($preview)<div class="preview-mark">DRAFT PREVIEW · NOT VALID FOR USE</div>@endif
<div class="bottom-gold"></div>
<div class="bottom"></div>
<div class="brand"><img src="{{ $logo }}"></div>
<div class="card-label">OFFICIAL MEMBERSHIP CARD</div>
<div class="registration">INTERNATIONAL UNION OF ARAB MASTER CHEFS LTD · UK REG. {{ $payload['organization']['registration_number'] }}</div>
<div class="photo-frame"><img class="photo" src="{{ $photoPath }}"></div>
<div class="identity">
    <div class="name {{ mb_strlen($displayName) > 28 ? 'long' : '' }}"><bdi>{{ $displayName }}</bdi></div>
    @if($payload['professional_title'])<div class="title"><bdi>{{ $payload['professional_title'] }}</bdi></div>@endif
    <div class="class-label">MEMBERSHIP CLASS</div>
    <div class="class-value"><bdi>{{ $payload['membership_type'] }}</bdi></div>
    <div class="number"><bdi dir="ltr">{{ $payload['membership_number'] }}</bdi></div>
    <div class="dates">VALID FROM&nbsp; <bdi dir="ltr">{{ $validFrom }}</bdi>&nbsp; - &nbsp;VALID UNTIL&nbsp; <bdi dir="ltr">{{ $validUntil }}</bdi></div>
</div>
<div class="qr"><barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.43" disableborder="0" /><br>SCAN TO VERIFY<br>LIVE STATUS</div>
<div class="verification-rule"></div>
<div class="nationality">NATIONALITY · <bdi dir="ltr">{{ $payload['nationality_code'] }}</bdi></div>
<div class="edition">VERSION {{ $payload['version'] }} · {{ $payload['template_version'] }}</div>
<div class="trust-right">PAdES/X.509 ELECTRONIC SIGNATURE</div>
<div class="nfc"><img src="{{ $nfc }}">&nbsp; NFC ENABLED</div>
<div class="hash-label">CREDENTIAL DATA SHA-256</div>
<div class="hash">{{ $payload['credential_data_sha256'] }}</div>
<div class="footer-left">PVC ID-1 · 85.60 × 54.00 MM</div>
<div class="footer-right">SECURE DIGITAL CREDENTIAL</div>
<pagebreak />
<div class="back-canvas"></div>
<div class="back-panel"></div>
<div class="back-brand"><img src="{{ $logo }}"></div>
<div class="back-heading">OFFICIAL MEMBERSHIP CREDENTIAL</div>
<div class="back-registration">UK COMPANY REGISTRATION {{ $payload['organization']['registration_number'] }}</div>
<div class="back-rule"></div>
<div class="back-qr"><barcode code="{{ $payload['verification_url'] }}" type="QR" error="M" size="0.54" disableborder="0" /><br>VERIFY LIVE STATUS</div>
<div class="back-details">
    <div class="back-number-label">MEMBERSHIP NUMBER</div>
    <div class="back-number"><bdi dir="ltr">{{ $payload['membership_number'] }}</bdi></div>
    <div class="back-notice">This card remains the property of International Union of Arab Master Chefs Ltd. Its validity and current status must be confirmed through the secure QR verification record. If found, please return it to the issuing body.</div>
    <div class="back-nfc"><img src="{{ $nfc }}"> NFC ENABLED · TAP WITH A COMPATIBLE DEVICE</div>
</div>
<div class="back-trust">CRYPTOGRAPHICALLY BOUND TO THE MEMBERSHIP RECORD · PAdES/X.509 ELECTRONIC SIGNATURE · SHA-256 INTEGRITY PROTECTION<br>Credential version {{ $payload['version'] }} · Template {{ $payload['template_version'] }}</div>
<div class="back-contact">iuoamc.pro · info@iuoamc.uk</div>
</body>
</html>
