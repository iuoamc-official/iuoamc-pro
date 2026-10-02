<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\SensoryLearningContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

final class SensoryLearningController extends Controller
{
    public function __construct(private SensoryLearningContent $content) {}

    public function index(Request $request, string $locale): View
    {
        $this->authorizeAdmin($request);
        $enrollments = DB::table('learning_enrollments')->join('users', 'users.id', '=', 'learning_enrollments.user_id')
            ->where('module_id', SensoryLearningContent::MODULE_ID)
            ->select('learning_enrollments.*', 'users.name', 'users.email')->orderByDesc('learning_enrollments.id')->paginate(25);

        return view('control.learning.index', compact('enrollments'));
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
        $student = DB::table('users')->find($record->user_id);

        return view('control.learning.show', [
            'record' => $record, 'student' => $student, 'assessment' => $this->content->assessment(),
            'answers' => $this->decode($record->exam_answers), 'lines' => $this->decode($record->summary_lines),
            'questionScores' => $this->decode($record->question_scores), 'summaryScores' => $this->decode($record->summary_scores),
            'summaryCaps' => $this->content->summaryCaps(),
        ]);
    }

    public function review(Request $request, string $locale, int $enrollment): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $rules = [
            'question_scores' => ['required', 'array:'.implode(',', range(25, 40)), 'size:16'],
            'summary_scores' => ['required', 'array:0,1,2,3,4', 'size:5'],
            'feedback' => ['required', 'string', 'max:6000'],
        ];
        foreach ($this->content->assessment()['questions'] as $question) {
            if ($question['type'] !== 'single_choice') {
                $rules['question_scores.'.$question['id']] = ['required', 'numeric', 'min:0', 'max:'.$question['points']];
            }
        }
        foreach ($this->content->summaryCaps() as $index => $cap) {
            $rules['summary_scores.'.$index] = ['required', 'numeric', 'min:0', 'max:'.$cap];
        }
        $validated = $request->validate($rules);
        DB::transaction(function () use ($request, $enrollment, $validated): void {
            $record = $this->record($enrollment, true);
            abort_unless($record->exam_submitted_at && $record->summary_submitted_at, 409);
            $examScore = $record->objective_score + array_sum($validated['question_scores']);
            $finalScore = round($examScore * 0.8 + array_sum($validated['summary_scores']), 2);
            DB::table('learning_enrollments')->where('id', $record->id)->update([
                'question_scores' => json_encode($validated['question_scores'], JSON_THROW_ON_ERROR),
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
