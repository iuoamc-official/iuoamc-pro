<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

final class SensoryLearningProject
{
    public const FIELDS = ['question', 'hypothesis', 'design', 'participants', 'controls', 'measures', 'analysis', 'ethics'];

    public function __construct(private SensoryLearningContent $content) {}

    public function enrollment(int $id, bool $lock = false): stdClass
    {
        $query = DB::table('learning_enrollments')->where('id', $id)->where('module_id', SensoryLearningContent::MODULE_ID);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($record, 404);
        abort_unless(hash_equals($record->content_version, $this->content->version()), 409, __('learning.version_changed'));

        return $record;
    }

    public function enrollmentForUser(int $userId): stdClass
    {
        $id = DB::table('learning_enrollments')->where('user_id', $userId)->where('module_id', SensoryLearningContent::MODULE_ID)->value('id');
        abort_unless($id, 404);

        return $this->enrollment((int) $id);
    }

    public function ready(stdClass $record): bool
    {
        $completed = $this->decode($record->completed_sections);

        return count($completed) === 30 && array_diff(range(1, 30), $completed) === [];
    }

    public function viewData(stdClass $record): array
    {
        $project = DB::table('learning_projects')->where('enrollment_id', $record->id)->first();
        $history = DB::table('learning_project_events')->where('project_id', $project?->id ?? 0)->orderByDesc('id')->paginate(5, ['*'], 'history_page');
        foreach ($history as $event) {
            $event->details = $this->decode($event->payload);
        }

        return ['record' => $record, 'project' => $project, 'ready' => $this->ready($record),
            'fields' => $this->decode($project?->fields), 'scores' => $this->decode($project?->rubric_scores), 'history' => $history];
    }

    public function save(stdClass $record, int $userId, array $data): void
    {
        DB::transaction(function () use ($record, $userId, $data): void {
            $record = $this->enrollment($record->id, true);
            abort_unless((int) $record->user_id === $userId && $this->ready($record), 403);
            $project = DB::table('learning_projects')->where('enrollment_id', $record->id)->lockForUpdate()->first();
            abort_if($project && ! in_array($project->status, ['draft', 'revision_requested'], true), 409, __('learning.project_locked'));
            if ((int) $data['revision'] !== (int) ($project?->revision ?? 0)) {
                throw ValidationException::withMessages(['revision' => __('learning.project_conflict')]);
            }
            $fields = array_fill_keys(self::FIELDS, '');
            foreach (self::FIELDS as $key) {
                $fields[$key] = $data['fields'][$key] ?? '';
            }
            $values = ['fields' => json_encode($fields, JSON_THROW_ON_ERROR), 'revision' => ($project?->revision ?? 0) + 1, 'updated_at' => now()];
            $submission = ($project?->submission_number ?? 0) + 1;
            if ($data['action'] === 'submit') {
                $values += ['status' => 'submitted', 'submission_number' => $submission, 'submitted_at' => now(),
                    'rubric_scores' => null, 'score' => null, 'feedback' => null, 'reviewed_by' => null, 'reviewed_at' => null];
            }
            if ($project) {
                DB::table('learning_projects')->where('id', $project->id)->update($values);
                $projectId = $project->id;
            } else {
                $projectId = DB::table('learning_projects')->insertGetId($values + ['enrollment_id' => $record->id, 'created_at' => now()]);
            }
            if ($data['action'] === 'submit') {
                $this->event($projectId, $submission, 'submitted', ['fields' => $fields, 'originality_confirmed' => true, 'data_truth_confirmed' => true], $userId);
                AuditTrail::record('learning.project_submitted', null, [], ['project_id' => $projectId, 'submission_number' => $submission], [], $userId);
            }
        });
    }

    public function review(stdClass $record, int $adminId, array $data): void
    {
        DB::transaction(function () use ($record, $adminId, $data): void {
            $record = $this->enrollment($record->id, true);
            $project = DB::table('learning_projects')->where('enrollment_id', $record->id)->lockForUpdate()->first();
            abort_unless($project, 404);
            abort_unless($project->status === 'submitted', 409, __('learning.project_locked'));
            if ((int) $data['revision'] !== (int) $project->revision) {
                throw ValidationException::withMessages(['revision' => __('learning.project_conflict')]);
            }
            $score = array_sum($data['rubric_scores']);
            if ($data['action'] === 'approve' && ($score < 15 || min($data['rubric_scores']) < 2)) {
                throw ValidationException::withMessages(['rubric_scores' => __('learning.project_approval_threshold')]);
            }
            $status = $data['action'] === 'approve' ? 'approved' : 'revision_requested';
            DB::table('learning_projects')->where('id', $project->id)->update([
                'status' => $status, 'revision' => $project->revision + 1, 'rubric_scores' => json_encode($data['rubric_scores'], JSON_THROW_ON_ERROR),
                'score' => $score, 'feedback' => $data['feedback'], 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($project->id, $project->submission_number, $status, ['rubric_scores' => $data['rubric_scores'], 'score' => $score, 'feedback' => $data['feedback']], $adminId);
            AuditTrail::record('learning.project_reviewed', null, [], ['project_id' => $project->id, 'submission_number' => $project->submission_number, 'status' => $status, 'score' => $score], [], $adminId);
        });
    }

    private function event(int $projectId, int $submission, string $event, array $payload, int $actorId): void
    {
        DB::table('learning_project_events')->insert(['project_id' => $projectId, 'submission_number' => $submission, 'event' => $event,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'actor_id' => $actorId, 'occurred_at' => now()]);
    }

    private function decode(?string $value): array
    {
        return $value ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
    }
}
