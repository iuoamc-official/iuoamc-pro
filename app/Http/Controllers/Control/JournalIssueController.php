<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalIssue;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class JournalIssueController extends Controller
{
    public function index(): View
    {
        return view('control.journal.issues.index', [
            'issues' => JournalIssue::query()->withCount('articles')->orderByDesc('volume')->orderByDesc('number')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('control.journal.issues.form', ['issue' => new JournalIssue]);
    }

    public function store(Request $request): RedirectResponse
    {
        $journal = Journal::query()->where('status', 'active')->firstOrFail();
        $validated = $request->validate($this->rules($journal));

        $issue = DB::transaction(function () use ($request, $journal, $validated): JournalIssue {
            $issue = JournalIssue::query()->create([
                'journal_id' => $journal->id,
                'volume' => $validated['volume'],
                'number' => $validated['number'],
                'slug' => 'volume-'.$validated['volume'].'-issue-'.$validated['number'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            AuditTrail::record('journal.issue.created', $issue, [], $issue->only(['volume', 'number', 'status']));

            return $issue;
        });

        return redirect()->route('journal.control.issues.index', ['locale' => app()->getLocale()])->with('success', trans('journal.messages.issue_created'));
    }

    public function publish(Request $request, string $locale, JournalIssue $issue): RedirectResponse
    {
        abort_unless($request->user()->canDo('journal.publish'), 403);
        abort_unless($issue->status === 'draft', 409);
        $issue->update(['status' => 'published', 'published_at' => now()->utc()->startOfSecond(), 'updated_by' => $request->user()->id]);
        AuditTrail::record('journal.issue.published', $issue, ['status' => 'draft'], ['status' => 'published']);

        return back()->with('success', trans('journal.messages.issue_published'));
    }

    /** @return array<string, mixed> */
    private function rules(Journal $journal): array
    {
        $rules = [
            'volume' => ['required', 'integer', 'min:1', 'max:9999'],
            'number' => [
                'required', 'integer', 'min:1', 'max:9999',
                Rule::unique('journal_issues', 'number')->where(fn ($query) => $query->where('journal_id', $journal->id)->where('volume', request()->integer('volume'))),
            ],
        ];
        foreach (['ar', 'en', 'fr'] as $locale) {
            $rules["title.{$locale}"] = ['required', 'string', 'max:500'];
            $rules["description.{$locale}"] = ['nullable', 'string', 'max:3000'];
        }

        return $rules;
    }
}
