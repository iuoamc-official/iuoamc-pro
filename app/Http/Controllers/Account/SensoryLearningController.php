<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\SensoryLearningContent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use stdClass;

final class SensoryLearningController extends Controller
{
    public function __construct(private SensoryLearningContent $content) {}

    public function index(Request $request, string $locale): View
    {
        $enrollment = DB::table('learning_enrollments')->where('user_id', $request->user()->id)
            ->where('module_id', SensoryLearningContent::MODULE_ID)->first();

        return view('account.learning.index', [
            'course' => $this->content->student(),
            'enrollment' => $enrollment,
            'completed' => $this->decode($enrollment?->completed_sections),
        ]);
    }

    public function lesson(Request $request, string $locale, int $section): View
    {
        $enrollment = $this->enrollment($request);
        $course = $this->content->student();
        $lesson = collect($course['sections'])->firstWhere('id', $section);
        abort_unless($lesson, 404);

        return view('account.learning.lesson', compact('course', 'lesson', 'enrollment'));
    }

    public function complete(Request $request, string $locale, int $section): RedirectResponse
    {
        abort_unless($section >= 1 && $section <= 30, 404);
        DB::transaction(function () use ($request, $section): void {
            $enrollment = $this->enrollment($request, true);
            $completed = $this->decode($enrollment->completed_sections);
            $completed[] = $section;
            $this->update($enrollment->id, ['completed_sections' => json_encode(array_values(array_unique($completed)), JSON_THROW_ON_ERROR)]);
        });

        return redirect()->route('account.learning.lesson', ['locale' => $locale, 'section' => min(30, $section + 1)])
            ->with('success', __('learning.progress_saved'));
    }

    public function exam(Request $request, string $locale): View
    {
        $enrollment = $this->enrollment($request);

        return view('account.learning.exam', [
            'course' => $this->content->student(),
            'enrollment' => $enrollment,
            'answers' => $this->decode($enrollment->exam_answers),
            'deadline' => $enrollment->exam_started_at ? CarbonImmutable::parse($enrollment->exam_started_at)->addMinutes(120) : null,
        ]);
    }

    public function startExam(Request $request, string $locale): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $enrollment = $this->enrollment($request, true);
            abort_unless(count($this->decode($enrollment->completed_sections)) === 30, 403);
            if (! $enrollment->exam_started_at) {
                $this->update($enrollment->id, ['exam_started_at' => now()]);
            }
        });

        return redirect()->route('account.learning.exam', ['locale' => $locale]);
    }

    public function saveExam(Request $request, string $locale): RedirectResponse
    {
        $rules = ['action' => ['required', Rule::in(['save', 'submit'])], 'answers' => ['required', 'array:'.implode(',', range(1, 40))]];
        foreach ($this->content->student()['questions'] as $question) {
            $rules['answers.'.$question['id']] = $question['type'] === 'single_choice'
                ? ['nullable', 'integer', Rule::in([0, 1, 2, 3])]
                : ['nullable', 'string', 'max:6000'];
        }
        $validated = $request->validate($rules);
        DB::transaction(function () use ($request, $validated): void {
            $enrollment = $this->enrollment($request, true);
            abort_unless($enrollment->exam_started_at && ! $enrollment->exam_submitted_at, 409);
            $expired = CarbonImmutable::parse($enrollment->exam_started_at)->addMinutes(120)->isPast();
            if ($expired && $validated['action'] === 'save') {
                throw ValidationException::withMessages(['answers' => __('learning.time_expired')]);
            }
            $answers = $expired ? $this->decode($enrollment->exam_answers) : $validated['answers'];
            $values = ['exam_answers' => json_encode($answers, JSON_THROW_ON_ERROR)];
            if ($validated['action'] === 'submit') {
                $values['exam_submitted_at'] = now();
                $values['objective_score'] = $this->content->objectiveScore($answers);
            }
            $this->update($enrollment->id, $values);
        });

        return redirect()->route('account.learning.exam', ['locale' => $locale])->with('success', __('learning.exam_saved'));
    }

    public function assignment(Request $request, string $locale): View
    {
        $enrollment = $this->enrollment($request);

        return view('account.learning.assignment', [
            'course' => $this->content->student(), 'enrollment' => $enrollment,
            'lines' => $this->decode($enrollment->summary_lines),
        ]);
    }

    public function submitAssignment(Request $request, string $locale): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array:0,1,2,3,4,5,6,7,8,9', 'size:10'],
            'lines.*' => ['required', 'string', 'max:1500', 'not_regex:/[\r\n]/u'],
        ]);
        $wordCount = count(preg_split('/\s+/u', trim(implode(' ', $validated['lines'])), -1, PREG_SPLIT_NO_EMPTY));
        if ($wordCount < 180 || $wordCount > 250) {
            throw ValidationException::withMessages(['lines' => __('learning.word_range')]);
        }
        DB::transaction(function () use ($request, $validated): void {
            $enrollment = $this->enrollment($request, true);
            abort_if($enrollment->summary_submitted_at, 409);
            $this->update($enrollment->id, ['summary_lines' => json_encode(array_values($validated['lines']), JSON_THROW_ON_ERROR), 'summary_submitted_at' => now()]);
        });

        return redirect()->route('account.learning.assignment', ['locale' => $locale])->with('success', __('learning.assignment_submitted'));
    }

    private function enrollment(Request $request, bool $lock = false): stdClass
    {
        $query = DB::table('learning_enrollments')->where('user_id', $request->user()->id)
            ->where('module_id', SensoryLearningContent::MODULE_ID);
        $enrollment = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($enrollment, 404);
        abort_unless(hash_equals($enrollment->content_version, $this->content->version()), 409, __('learning.version_changed'));

        return $enrollment;
    }

    private function decode(?string $value): array
    {
        return $value ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    private function update(int $id, array $values): void
    {
        DB::table('learning_enrollments')->where('id', $id)->update($values + ['updated_at' => now()]);
    }
}
