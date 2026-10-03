<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\SensoryLearningProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class SensoryLearningProjectController extends Controller
{
    public function __construct(private SensoryLearningProject $projects) {}

    public function show(Request $request, string $locale): Response
    {
        $record = $this->projects->enrollmentForUser($request->user()->id);

        return response()->view('account.learning.project', $this->projects->viewData($record))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $record = $this->projects->enrollmentForUser($request->user()->id);
        abort_unless($this->projects->ready($record), 403, __('learning.project_requires_material'));
        $rules = ['action' => ['required', Rule::in(['save', 'submit'])], 'revision' => ['required', 'integer', 'min:0'],
            'fields' => ['present', 'array:'.implode(',', SensoryLearningProject::FIELDS)]];
        $submitting = $request->input('action') === 'submit';
        foreach (SensoryLearningProject::FIELDS as $key) {
            $rules['fields.'.$key] = $submitting ? ['required', 'string', 'min:25', 'max:2000'] : ['nullable', 'string', 'max:2000'];
        }
        if ($submitting) {
            $rules['originality'] = ['accepted'];
            $rules['data_truth'] = ['accepted'];
        }
        $data = $request->validate($rules);
        $this->projects->save($record, $request->user()->id, $data);

        return redirect()->route('account.learning.project', ['locale' => $locale])
            ->with('success', __('learning.'.($submitting ? 'project_submitted_notice' : 'project_draft_saved')));
    }
}
