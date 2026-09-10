<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WicpRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class WicpVerificationController extends Controller
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
            $registry = app(WicpRegistry::class);
            $record = preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1 ? $registry->publicRecord($token) : null;
            if ($record) {
                // Deliberate allowlist: no recipient, certificate payload, internal ID or personal data.
                $program = (array) data_get($record->registration_payload, 'program', []);
                $public = ['reference' => $record->reference, 'kind' => $record->kind,
                    'program_code' => $program['code'] ?? null, 'program_title' => $program['title'] ?? null,
                    'program_version' => $program['version'] ?? null, 'status' => $registry->effectiveStatus($record),
                    'registered_at' => $record->registered_at?->format('Y-m-d'),
                    'parent_reference' => $record->kind === 'certificate' ? ($program['reference'] ?? null) : null];
            }
        } catch (Throwable $exception) { report($exception); }
        return response()->view('wicp.verify', compact('public', 'locale', 'token'), $public ? 200 : 404, $this->headers());
    }

    public function proof(Request $request): JsonResponse
    {
        $this->locale($request);
        try {
            $registry = app(WicpRegistry::class);
            $token = (string) ($request->route('token') ?? $request->route('invalidToken'));
            $record = preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1 ? $registry->publicRecord($token) : null;
            if ($record) {
                $name = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $record->reference);
                return response()->json($registry->proof($record), 200, $this->headers() +
                    ['Content-Disposition' => 'attachment; filename="'.$name.'-proof.json"'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } catch (Throwable $exception) { report($exception); }
        return response()->json(['message' => __('wicp.unavailable')], 404, $this->headers());
    }
}
