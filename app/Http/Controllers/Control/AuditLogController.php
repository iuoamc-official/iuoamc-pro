<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\IntegrityService;
use App\Services\InstitutionalAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'event' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $logs = app(InstitutionalAccess::class)
            ->constrainAudit(AuditLog::query(), $request->user())
            ->with('actor:id,name,email')
            ->when($validated['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('event', 'like', "%{$term}%")
                        ->orWhere('ip_address', 'like', "%{$term}%")
                        ->orWhereHas('actor', function ($actor) use ($term): void {
                            $actor->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                });
            })
            ->when($validated['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($validated['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', '<=', $date))
            ->latest('occurred_at')
            ->paginate(40)
            ->withQueryString();

        $events = app(InstitutionalAccess::class)
            ->constrainAudit(AuditLog::query(), $request->user())
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        return view('control.audit.index', compact('logs', 'events'));
    }

    public function show(AuditLog $auditLog): View
    {
        app(InstitutionalAccess::class)->authorizeAudit(auth()->user(), $auditLog);
        $auditLog->load('actor:id,name,email');
        $integrity = app(IntegrityService::class)->verifyAuditLog($auditLog);

        return view('control.audit.show', compact('auditLog', 'integrity'));
    }
}
