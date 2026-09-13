<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

final class TvControlController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        return view('control.tv.index', [
            'locale' => $locale,
            'channel' => [
                'name' => 'IUOAMC TV',
                'timezone' => config('broadcast_tv.timezone', 'Europe/London'),
                'legacy_url' => config('broadcast_tv.legacy_url', 'https://platform-iuoamc.uk/tv'),
                'preview_url' => config('broadcast_tv.preview_url'),
                'hls_url' => config('broadcast_tv.hls_url'),
                'status_url' => config('broadcast_tv.status_url'),
                'public_enabled' => (bool) config('broadcast_tv.public_enabled', false),
            ],
        ]);
    }

    public function status(Request $request, string $locale): JsonResponse
    {
        $statusUrl = config('broadcast_tv.status_url');

        $payload = [
            'checked_at' => now()->toIso8601String(),
            'mode' => config('broadcast_tv.public_enabled', false) ? 'public' : 'safe',
            'preview_configured' => filled(config('broadcast_tv.preview_url')),
            'hls_configured' => filled(config('broadcast_tv.hls_url')),
            'nexus' => [
                'configured' => filled($statusUrl),
                'online' => false,
                'state' => filled($statusUrl) ? 'unreachable' : 'not_configured',
            ],
            'now' => null,
            'next' => null,
            'epg' => [],
        ];

        if (blank($statusUrl)) {
            return response()->json($payload);
        }

        try {
            $response = Http::acceptJson()
                ->timeout((float) config('broadcast_tv.status_timeout', 2.5))
                ->get($statusUrl);

            if (! $response->successful()) {
                $payload['nexus']['state'] = 'http_'.$response->status();
                return response()->json($payload);
            }

            $remote = $response->json();
            $payload['nexus']['online'] = true;
            $payload['nexus']['state'] = 'online';

            if (is_array($remote)) {
                $payload['now'] = $this->safeProgram(data_get($remote, 'now'));
                $payload['next'] = $this->safeProgram(data_get($remote, 'next'));

                $epg = data_get($remote, 'epg', []);
                if (is_array($epg)) {
                    $payload['epg'] = collect($epg)
                        ->take(6)
                        ->map(fn ($item) => $this->safeProgram($item))
                        ->filter()
                        ->values()
                        ->all();
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json($payload);
    }

    private function safeProgram(mixed $program): ?array
    {
        if (! is_array($program)) {
            return null;
        }

        $title = trim((string) ($program['title'] ?? $program['name'] ?? ''));
        if ($title === '') {
            return null;
        }

        return [
            'title' => mb_substr($title, 0, 160),
            'subtitle' => mb_substr(trim((string) ($program['subtitle'] ?? $program['description'] ?? '')), 0, 240),
            'start' => mb_substr(trim((string) ($program['start'] ?? $program['starts_at'] ?? '')), 0, 64),
            'status' => mb_substr(trim((string) ($program['status'] ?? '')), 0, 40),
        ];
    }
}
