<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
                'public_enabled' => (bool) config('broadcast_tv.public_enabled', false),
            ],
        ]);
    }
}
