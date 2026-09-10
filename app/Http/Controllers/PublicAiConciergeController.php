<?php

namespace App\Http\Controllers;

use App\Services\PublicAiConcierge;
use App\Services\PublicAiKnowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class PublicAiConciergeController extends Controller
{
    public function ask(
        Request $request,
        string $locale,
        PublicAiKnowledge $knowledge,
        PublicAiConcierge $concierge,
    ): JsonResponse {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $source = $knowledge->forQuestion($locale, trim($validated['question']));

        try {
            $result = $concierge->answer($locale, trim($validated['question']), $source['context']);
        } catch (RuntimeException $exception) {
            Log::warning('Public AI concierge request unavailable.', ['reason' => $exception->getMessage()]);

            return response()->json(['message' => trans('public_site.ai.unavailable')], 503);
        }

        return response()->json([
            'answer' => $result['answer'],
            'sources' => $source['sources'],
            'request_id' => $result['request_id'],
        ]);
    }
}
