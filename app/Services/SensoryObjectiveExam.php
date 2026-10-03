<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class SensoryObjectiveExam
{
    public const FORMAT = 'mcq-30-v1';

    public const MINUTES = 30;

    public function assessment(): array
    {
        return json_decode(file_get_contents(resource_path('learning/sensory-foundation/objective_exam_v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function version(): string
    {
        return hash('sha256', json_encode($this->assessment(), JSON_THROW_ON_ERROR));
    }

    public function studentQuestions(?string $order): array
    {
        $questions = collect($this->assessment()['questions'])->keyBy('id');
        $ids = $order ? json_decode($order, true, 512, JSON_THROW_ON_ERROR) : range(1, 30);

        return array_map(function (int $id) use ($questions): array {
            $question = $questions[$id];

            return ['id' => $id, 'type' => 'single_choice', 'prompt' => $question['prompt'], 'options' => $question['options'], 'points' => 1];
        }, $ids);
    }

    public function correctCount(array $answers): int
    {
        $count = 0;
        foreach ($this->assessment()['questions'] as $question) {
            if (isset($answers[$question['id']]) && in_array((string) $answers[$question['id']], ['0', '1', '2', '3'], true) && (int) $answers[$question['id']] === $question['correct_index']) {
                $count++;
            }
        }

        return $count;
    }

    public function deadline(stdClass $record): CarbonImmutable
    {
        abort_unless($record->exam_deadline_at, 409, __('learning.version_changed'));

        return CarbonImmutable::parse($record->exam_deadline_at);
    }

    public function assertVersion(stdClass $record): void
    {
        abort_unless($record->exam_assessment_hash && hash_equals($record->exam_assessment_hash, $this->version()), 409, __('learning.version_changed'));
    }

    /** The caller must hold the enrollment row lock inside a transaction. */
    public function finish(stdClass $record, array $answers, bool $expired): void
    {
        $this->assertVersion($record);
        $correct = $this->correctCount($answers);
        DB::table('learning_enrollments')->where('id', $record->id)->update([
            'exam_answers' => json_encode($answers, JSON_THROW_ON_ERROR),
            'exam_correct_count' => $correct, 'objective_score' => round($correct / 30 * 100, 2),
            'exam_submitted_at' => $expired ? $this->deadline($record) : now(), 'updated_at' => now(),
        ]);
    }

    public function closeExpired(int $enrollment): stdClass
    {
        return DB::transaction(function () use ($enrollment): stdClass {
            $record = DB::table('learning_enrollments')->where('id', $enrollment)
                ->where('module_id', SensoryLearningContent::MODULE_ID)->lockForUpdate()->first();
            abort_unless($record, 404);
            if ($record->exam_format === self::FORMAT && ! $record->exam_submitted_at && now()->gte($this->deadline($record))) {
                $this->finish($record, $record->exam_answers ? json_decode($record->exam_answers, true, 512, JSON_THROW_ON_ERROR) : [], true);
                $record = DB::table('learning_enrollments')->find($record->id);
            }

            return $record;
        });
    }
}
