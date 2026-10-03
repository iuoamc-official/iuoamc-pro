<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\SensoryLearningContent;
use App\Services\SensoryLearningProgress;
use App\Services\SensoryObjectiveExam;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use stdClass;

final class SensoryLearningController extends Controller
{
    public function __construct(private SensoryLearningContent $content, private SensoryObjectiveExam $objectiveExam, private SensoryLearningProgress $progress) {}

    public function index(Request $request, string $locale): Response
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', Rule::in(['all', 'studying', 'exam', 'review', 'reviewed', 'project'])],
        ]);
        $stats = DB::table('learning_enrollments')->where('module_id', SensoryLearningContent::MODULE_ID)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN exam_started_at IS NULL THEN 1 ELSE 0 END) as studying, SUM(CASE WHEN exam_started_at IS NOT NULL AND exam_submitted_at IS NULL THEN 1 ELSE 0 END) as exam, SUM(CASE WHEN exam_submitted_at IS NOT NULL AND summary_submitted_at IS NOT NULL AND reviewed_at IS NULL THEN 1 ELSE 0 END) as review, SUM(CASE WHEN reviewed_at IS NOT NULL THEN 1 ELSE 0 END) as reviewed')->first();
        $stats->project_review = DB::table('learning_projects')->join('learning_enrollments', 'learning_enrollments.id', '=', 'learning_projects.enrollment_id')
            ->where('learning_enrollments.module_id', SensoryLearningContent::MODULE_ID)->where('learning_projects.status', 'submitted')->count();
        $query = DB::table('learning_enrollments')->leftJoin('learning_projects', 'learning_projects.enrollment_id', '=', 'learning_enrollments.id')->join('users', 'users.id', '=', 'learning_enrollments.user_id')
            ->where('module_id', SensoryLearningContent::MODULE_ID)
            ->select(['learning_enrollments.id', 'learning_enrollments.completed_sections', 'learning_enrollments.exam_started_at', 'learning_enrollments.exam_submitted_at', 'learning_enrollments.summary_submitted_at', 'learning_enrollments.reviewed_at', 'learning_enrollments.exam_format', 'learning_enrollments.objective_score', 'learning_enrollments.final_score', 'users.name', 'users.email', 'learning_projects.status as project_status']);
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', '%'.$search.'%')->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }
        match ($filters['state'] ?? 'all') {
            'studying' => $query->whereNull('learning_enrollments.exam_started_at'),
            'exam' => $query->whereNotNull('learning_enrollments.exam_started_at')->whereNull('learning_enrollments.exam_submitted_at'),
            'review' => $query->whereNotNull('learning_enrollments.exam_submitted_at')->whereNotNull('learning_enrollments.summary_submitted_at')->whereNull('learning_enrollments.reviewed_at'),
            'reviewed' => $query->whereNotNull('learning_enrollments.reviewed_at'),
            'project' => $query->where('learning_projects.status', 'submitted'),
            default => null,
        };
        $enrollments = $query->orderByDesc('learning_enrollments.id')->paginate(25)->withQueryString();
        foreach ($enrollments as $record) {
            $record->learning_status = $this->progress->status($record);
            $record->completed_count = count($this->decode($record->completed_sections));
        }

        $invitations = DB::table('learning_invitations')->where('module_id', SensoryLearningContent::MODULE_ID)
            ->select('id', 'email', 'expires_at', 'used_at', 'revoked_at', 'student_name', 'student_phone', 'student_country')
            ->orderByDesc('id')->paginate(15, ['*'], 'invitations_page');

        return response()->view('control.learning.index', compact('enrollments', 'invitations', 'stats', 'filters'))->header('Cache-Control', 'no-store, private');
    }

    public function enroll(Request $request, string $locale): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['email' => ['required', 'email', 'exists:users,email']]);
        DB::transaction(function () use ($request, $validated): void {
            $user = DB::table('users')->where('email', $validated['email'])->lockForUpdate()->first();
            abort_unless($user && $user->status === 'active' && $user->email_verified_at, 422, __('learning.active_verified_required'));
            $exists = DB::table('learning_enrollments')->where('user_id', $user->id)->where('module_id', SensoryLearningContent::MODULE_ID)->exists();
            if (! $exists) {
                $id = DB::table('learning_enrollments')->insertGetId([
                    'user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID,
                    'content_version' => $this->content->version(), 'enrolled_by' => $request->user()->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                AuditTrail::record('learning.enrolled', null, [], ['enrollment_id' => $id, 'user_id' => $user->id]);
            }
        });

        return redirect()->route('learning.control.index', ['locale' => $locale])->with('success', __('learning.enrolled'));
    }

    public function show(Request $request, string $locale, int $enrollment): View
    {
        $this->authorizeAdmin($request);
        $record = $this->record($enrollment);
        $isObjective = $record->exam_format === SensoryObjectiveExam::FORMAT;
        if ($isObjective) {
            $this->objectiveExam->assertVersion($record);
            $record = $this->objectiveExam->closeExpired($record->id);
        }
        $student = DB::table('users')->find($record->user_id);

        return view('control.learning.show', [
            'record' => $record, 'student' => $student, 'isObjective' => $isObjective,
            'assessment' => $isObjective ? $this->objectiveExam->assessment() : $this->content->assessment(),
            'answers' => $this->decode($record->exam_answers), 'lines' => $this->decode($record->summary_lines),
            'questionScores' => $this->decode($record->question_scores), 'summaryScores' => $this->decode($record->summary_scores),
            'summaryCaps' => $this->content->summaryCaps(), 'canReport' => $this->progress->canReport($record),
        ]);
    }

    public function report(Request $request, string $locale, int $enrollment): Response
    {
        $this->authorizeAdmin($request);
        $record = $this->record($enrollment);
        abort_unless($this->progress->canReport($record), 409, __('learning.report_not_ready'));

        return response()->view('account.learning.report', [
            'approvedProject' => DB::table('learning_projects')->where('enrollment_id', $record->id)->where('status', 'approved')->first(),
            'returnUrl' => route('learning.control.show', ['locale' => $locale, 'enrollment' => $record->id]),
            'record' => $record, 'student' => DB::table('users')->find($record->user_id), 'course' => $this->content->student(),
            'summaryScores' => $this->decode($record->summary_scores), 'summaryCaps' => $this->content->summaryCaps(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $request, string $locale, int $enrollment): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $initial = $this->record($enrollment);
        $isObjective = $initial->exam_format === SensoryObjectiveExam::FORMAT;
        $rules = [
            'question_scores' => $isObjective ? ['prohibited'] : ['required', 'array:'.implode(',', range(25, 40)), 'size:16'],
            'summary_scores' => ['required', 'array:0,1,2,3,4', 'size:5'],
            'feedback' => ['required', 'string', 'max:6000'],
        ];
        foreach ($this->content->assessment()['questions'] as $question) {
            if (! $isObjective && $question['type'] !== 'single_choice') {
                $rules['question_scores.'.$question['id']] = ['required', 'numeric', 'min:0', 'max:'.$question['points']];
            }
        }
        foreach ($this->content->summaryCaps() as $index => $cap) {
            $rules['summary_scores.'.$index] = ['required', 'numeric', 'min:0', 'max:'.$cap];
        }
        $validated = $request->validate($rules);
        DB::transaction(function () use ($request, $enrollment, $validated, $isObjective): void {
            $record = $this->record($enrollment, true);
            abort_unless($record->exam_submitted_at && $record->summary_submitted_at, 409);
            if ($isObjective) {
                $this->objectiveExam->assertVersion($record);
                abort_unless($record->exam_correct_count !== null, 409);
            }
            $examScore = $isObjective ? $record->exam_correct_count / 30 * 100 : $record->objective_score + array_sum($validated['question_scores']);
            $finalScore = round($examScore * 0.8 + array_sum($validated['summary_scores']), 2);
            DB::table('learning_enrollments')->where('id', $record->id)->update([
                'question_scores' => json_encode($validated['question_scores'] ?? [], JSON_THROW_ON_ERROR),
                'summary_scores' => json_encode($validated['summary_scores'], JSON_THROW_ON_ERROR),
                'final_score' => $finalScore, 'review_feedback' => $validated['feedback'],
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            AuditTrail::record('learning.reviewed', null, ['final_score' => $record->final_score], [
                'enrollment_id' => $record->id, 'final_score' => $finalScore,
            ]);
        });

        return redirect()->route('learning.control.show', ['locale' => $locale, 'enrollment' => $enrollment])->with('success', __('learning.review_saved'));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
    }

    private function record(int $id, bool $lock = false): stdClass
    {
        $query = DB::table('learning_enrollments')->where('id', $id)->where('module_id', SensoryLearningContent::MODULE_ID);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($record, 404);
        abort_unless(hash_equals($record->content_version, $this->content->version()), 409, __('learning.version_changed'));

        return $record;
    }

    private function decode(?string $value): array
    {
        return $value ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
    }
}
