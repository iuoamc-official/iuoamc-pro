<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ProCertificateRegistry;
use App\Services\ProMasterCertificatePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class ProCertificateVerificationController extends Controller
{
    private function headers(): array
    {
        return ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; script-src 'none'; connect-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'"];
    }

    private function locale(Request $request): string
    {
        $locale = $request->query('lang', 'ar');
        $locale = is_string($locale) && in_array($locale, ['ar', 'en', 'fr'], true) ? $locale : 'ar';
        app()->setLocale($locale);
        return $locale;
    }

    public function show(Request $request): Response
    {
        $locale = $this->locale($request);
        $token = (string) ($request->route('token') ?? $request->route('invalidToken'));
        $public = null;
        try {
            $registry = app(ProCertificateRegistry::class);
            $record = preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1 ? $registry->publicRecord($token) : null;
            if ($record) {
                // Explicit allowlist: never send a model or its private issued payload to this public view.
                $public = ['number' => $record->certificate_number, 'public_name' => $record->public_name,
                    'title' => $record->certificate_title, 'program' => $record->program_title,
                    'type' => $record->certificate_type, 'status' => $registry->effectiveStatus($record),
                    'achievement_date' => $record->achievement_date?->format('Y-m-d'),
                    'expires_on' => $record->expires_on?->format('Y-m-d'),
                    'issued_at' => $record->issued_at?->format('Y-m-d'),
                    'issuer' => data_get($record->issued_payload, 'issuer.display_name'),
                    'verified_at' => now()->utc()->startOfSecond()->format('Y-m-d H:i:s').' UTC',
                    'record_uuid' => $record->record_uuid,
                    'signing_key_id' => $record->signing_key_id,
                    'pdf_sha256' => $record->pdf_sha256,
                    'payload_sha256' => $record->payload_sha256];
                if ($record->credential_basis !== null) {
                    $public['credential_basis'] = $record->credential_basis;
                    $public['accreditation_reference'] = $record->accreditation_reference;
                    $public['accreditation_date'] = $record->accreditation_date?->format('Y-m-d');
                }
                if ((int) $record->schema_version >= 2) {
                    $public['type_name'] = data_get($record->issued_payload, 'catalog_snapshot.names.'.$locale);
                    $public['type_code'] = data_get($record->issued_payload, 'catalog_snapshot.code');
                    $public['specialization'] = $record->specialization;
                    $public['program_ip_code'] = ProMasterCertificatePdf::programIpCodeFromStatement(
                        data_get($record->issued_payload, 'statement')
                    );
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
        return response()->view('pro_certificates.verify', compact('public', 'locale', 'token'), $public ? 200 : 404, $this->headers());
    }

    public function proof(Request $request): JsonResponse
    {
        $this->locale($request);
        try {
            $registry = app(ProCertificateRegistry::class);
            $token = (string) ($request->route('token') ?? $request->route('invalidToken'));
            $record = preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1 ? $registry->publicRecord($token) : null;
            if ($record) {
                return response()->json($registry->proof($record), 200, $this->headers(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
        return response()->json(['message' => __('certificates.public.unavailable')], 404, $this->headers());
    }
}
