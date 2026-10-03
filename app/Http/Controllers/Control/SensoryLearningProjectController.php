<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Services\SensoryLearningProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SensoryLearningProjectController extends Controller
{
    public function __construct(private SensoryLearningProject $projects) {}

    public function show(Request $request, string $locale, int $enrollment): Response
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $record = $this->projects->enrollment($enrollment);

        return response()->view('control.learning.project', $this->projects->viewData($record) + ['student' => DB::table('users')->find($record->user_id)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $request, string $locale, int $enrollment): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $record = $this->projects->enrollment($enrollment);
        $data = $request->validate(['action' => ['required', Rule::in(['revise', 'approve'])], 'revision' => ['required', 'integer', 'min:0'],
            'rubric_scores' => ['required', 'array:0,1,2,3,4', 'size:5'], 'rubric_scores.*' => ['required', 'integer', 'min:0', 'max:4'],
            'feedback' => ['required', 'string', 'min:30', 'max:5000']]);
        $this->projects->review($record, $request->user()->id, $data);

        return redirect()->route('learning.control.project', ['locale' => $locale, 'enrollment' => $enrollment])->with('success', __('learning.project_review_saved'));
    }
}
