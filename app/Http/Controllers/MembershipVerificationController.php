<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MembershipCredential;
use App\Services\MembershipCredentialRegistry;
use App\Services\MembershipRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class MembershipVerificationController extends Controller
{
    private function headers(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; script-src 'none'; connect-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'",
        ];
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
            $credential = app(MembershipCredentialRegistry::class)->publicRecord($token);
            if ($credential !== null) {
                $payload = $credential->payload;
                $newerExists = MembershipCredential::query()
                    ->where('membership_id', $credential->membership_id)
                    ->where('version', '>', $credential->version)
                    ->exists();
                $public = [
                    'number' => $credential->membership_number,
                    'full_name' => $payload['latin_name'] ?: $payload['full_name'],
                    'membership_type' => $payload['membership_type'],
                    'professional_title' => $payload['professional_title'],
                    'organization' => data_get($payload, 'organization.display_name'),
                    'valid_from' => $payload['valid_from'],
                    'valid_until' => $payload['valid_until'],
                    'issued_at' => substr((string) $payload['issued_at'], 0, 10),
                    'status' => $newerExists ? 'superseded' : $credential->membership->effectiveStatus(),
                    'version' => (int) $credential->version,
                    'card_pdf_sha256' => $credential->card_pdf_sha256,
                    'certificate_pdf_sha256' => $credential->certificate_pdf_sha256,
                    'signing_certificate_sha256' => $credential->signing_certificate_sha256,
                    'pades_profile' => $credential->pades_profile,
                    'verified_at' => now()->utc()->format('Y-m-d H:i:s').' UTC',
                ];
            }
        } catch (Throwable $error) {
            report($error);
        }

        return response()->view(
            'membership_credentials.verify',
            compact('public', 'locale', 'token'),
            $public ? 200 : 404,
            $this->headers()
        );
    }

    public function proof(Request $request): JsonResponse
    {
        $this->locale($request);
        $token = (string) ($request->route('token') ?? $request->route('invalidToken'));
        try {
            $credential = app(MembershipCredentialRegistry::class)->publicRecord($token);
            if ($credential !== null) {
                return response()->json([
                    'schema' => 'iuoamc-membership-public-proof-v1',
                    'membership_number' => $credential->membership_number,
                    'version' => (int) $credential->version,
                    'payload_sha256' => $credential->payload_sha256,
                    'card_pdf_sha256' => $credential->card_pdf_sha256,
                    'certificate_pdf_sha256' => $credential->certificate_pdf_sha256,
                    'pades_profile' => $credential->pades_profile,
                    'pades_status' => $credential->pades_status,
                    'signing_certificate_sha256' => $credential->signing_certificate_sha256,
                    'record_hash' => $credential->record_hash,
                    'verified_at' => now()->utc()->format('Y-m-d H:i:s').' UTC',
                ], 200, $this->headers(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } catch (Throwable $error) {
            report($error);
        }

        return response()->json(['message' => trans('memberships.verification_unavailable')], 404, $this->headers());
    }
}
