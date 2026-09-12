<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\JournalSubmission;
use App\Services\AuditTrail;
use App\Services\JournalLaunchReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class JournalOperationsController extends Controller
{
    public function index(JournalLaunchReadiness $readiness): View
    {
        $journal = Journal::query()->where('status', 'active')->firstOrFail();
        $outboxCounts = $journal->notificationOutbox()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $publicationMetrics = [
            'published_articles' => $journal->articles()->published()->count(),
            'html_views' => $journal->articles()->published()->sum('html_views_count'),
            'pdf_downloads' => $journal->articles()->published()->sum('pdf_downloads_count'),
            'citation_downloads' => $journal->articles()->published()->sum('citation_downloads_count'),
            'jats_downloads' => $journal->articles()->published()->sum('jats_downloads_count'),
            'active_submissions' => JournalSubmission::query()->where('journal_id', $journal->id)->whereIn('status', ['submitted', 'screening'])->count(),
            'under_review' => JournalArticle::query()->where('journal_id', $journal->id)->where('status', 'under_review')->count(),
        ];

        return view('control.journal.operations.index', [
            'journal' => $journal,
            'checks' => $readiness->checks($journal),
            'outboxCounts' => $outboxCounts,
            'publicationMetrics' => $publicationMetrics,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_email' => ['required', 'email:rfc', 'max:254'],
            'publication_frequency' => ['required', Rule::in(JournalLaunchReadiness::FREQUENCIES)],
            'fee_policy' => ['required', Rule::in(JournalLaunchReadiness::FEE_POLICIES)],
            'publisher_person_name' => ['required', 'string', 'max:255'],
            'publisher_title.ar' => ['required', 'string', 'max:255'],
            'publisher_title.en' => ['required', 'string', 'max:255'],
            'publisher_title.fr' => ['required', 'string', 'max:255'],
            'publisher_biography.ar' => ['required', 'string', 'max:3000'],
            'publisher_biography.en' => ['required', 'string', 'max:3000'],
            'publisher_biography.fr' => ['required', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $journal = Journal::query()->where('status', 'active')->lockForUpdate()->firstOrFail();
            $before = $journal->settings ?? [];
            $after = array_merge($before, [
                'contact_email' => mb_strtolower(trim($validated['contact_email'])),
                'publication_frequency' => $validated['publication_frequency'],
                'fee_policy' => $validated['fee_policy'],
                'publisher_person_name' => trim($validated['publisher_person_name']),
                'publisher_title' => collect($validated['publisher_title'])->map(fn ($value) => trim($value))->all(),
                'publisher_biography' => collect($validated['publisher_biography'])->map(fn ($value) => trim($value))->all(),
            ]);
            $journal->update(['settings' => $after, 'updated_by' => $request->user()->id]);
            AuditTrail::record('journal.operations.settings_updated', $journal, [
                'contact_email' => $before['contact_email'] ?? null,
                'publication_frequency' => $before['publication_frequency'] ?? null,
                'fee_policy' => $before['fee_policy'] ?? null,
                'publisher_person_name' => $before['publisher_person_name'] ?? null,
            ], [
                'contact_email' => $after['contact_email'],
                'publication_frequency' => $after['publication_frequency'],
                'fee_policy' => $after['fee_policy'],
                'publisher_person_name' => $after['publisher_person_name'],
            ]);
        }, 5);

        return back()->with('success', trans('journal.messages.operations_updated'));
    }

    public function confirmBackup(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('journal.publish'), 403);
        $validated = $request->validate([
            'backup_reference' => ['required', 'string', 'max:255'],
            'backup_confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $journal = Journal::query()->where('status', 'active')->lockForUpdate()->firstOrFail();
            $settings = array_merge($journal->settings ?? [], [
                'backup_verified_at' => now()->utc()->startOfSecond()->toIso8601String(),
                'backup_reference' => trim($validated['backup_reference']),
            ]);
            $journal->update(['settings' => $settings, 'updated_by' => $request->user()->id]);
            AuditTrail::record('journal.operations.backup_verified', $journal, [], [
                'backup_verified_at' => $settings['backup_verified_at'],
                'backup_reference' => $settings['backup_reference'],
            ]);
        }, 5);

        return back()->with('success', trans('journal.messages.backup_verified'));
    }

    public function launch(Request $request, JournalLaunchReadiness $readiness): RedirectResponse
    {
        abort_unless($request->user()->canDo('journal.publish') && $request->user()->canDo('journal.governance'), 403);

        DB::transaction(function () use ($request, $readiness): void {
            $journal = Journal::query()->where('status', 'active')->lockForUpdate()->firstOrFail();
            abort_if($journal->isPubliclyLaunched(), 409);
            $checks = $readiness->checks($journal);
            $failed = collect($checks)->filter(fn (array $check): bool => ! $check['passed'])->keys()->all();
            if ($failed !== []) {
                throw ValidationException::withMessages([
                    'launch' => trans('journal.errors.launch_blocked', ['checks' => implode(', ', $failed)]),
                ]);
            }

            $settings = array_merge($journal->settings ?? [], [
                'public_launch_enabled' => true,
                'launched_at' => now()->utc()->startOfSecond()->toIso8601String(),
                'launched_by' => $request->user()->id,
            ]);
            $journal->update(['settings' => $settings, 'updated_by' => $request->user()->id]);
            AuditTrail::record('journal.operations.launched', $journal, ['public_launch_enabled' => false], [
                'public_launch_enabled' => true,
                'launched_at' => $settings['launched_at'],
            ]);
        }, 5);

        return back()->with('success', trans('journal.messages.journal_launched'));
    }

    public function unlaunch(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('journal.publish') && $request->user()->canDo('journal.governance'), 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $validated): void {
            $journal = Journal::query()->where('status', 'active')->lockForUpdate()->firstOrFail();
            abort_unless($journal->isPubliclyLaunched(), 409);
            $settings = array_merge($journal->settings ?? [], ['public_launch_enabled' => false]);
            $journal->update(['settings' => $settings, 'updated_by' => $request->user()->id]);
            AuditTrail::record('journal.operations.public_access_suspended', $journal, [
                'public_launch_enabled' => true,
            ], ['public_launch_enabled' => false], ['reason' => trim($validated['reason'])]);
        }, 5);

        return back()->with('success', trans('journal.messages.journal_unlaunched'));
    }
}
