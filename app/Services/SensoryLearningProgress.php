<?php

declare(strict_types=1);

namespace App\Services;

use stdClass;

final class SensoryLearningProgress
{
    public function status(stdClass $record): string
    {
        if ($record->reviewed_at) {
            return 'reviewed';
        }
        if ($record->exam_submitted_at && $record->summary_submitted_at) {
            return 'ready_for_review';
        }
        if ($record->exam_submitted_at) {
            return 'summary_pending';
        }
        if ($record->exam_started_at) {
            return 'exam_in_progress';
        }

        return 'studying';
    }

    public function canReport(stdClass $record): bool
    {
        $completed = json_decode($record->completed_sections ?? '[]', true, 512, JSON_THROW_ON_ERROR);

        return $record->reviewed_at && $record->exam_submitted_at && $record->summary_submitted_at
            && $record->final_score !== null && count($completed) === 30 && array_diff(range(1, 30), $completed) === [];
    }
}
