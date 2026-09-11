<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Journal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureJournalLaunched
{
    public function handle(Request $request, Closure $next): Response
    {
        $journal = Journal::query()->where('status', 'active')->firstOrFail();
        $canPreview = $request->user()?->canDo('journal.view') === true;

        abort_unless($journal->isPubliclyLaunched() || $canPreview, 404);

        return $next($request);
    }
}
