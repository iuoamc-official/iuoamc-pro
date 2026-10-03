<?php

declare(strict_types=1);

namespace App\Services;

final class SensoryLearningContent
{
    public const MODULE_ID = 'sensory-foundation-01';

    public function student(): array
    {
        $course = $this->coreStudent();
        $support = json_decode(file_get_contents(resource_path('learning/sensory-foundation/study_support.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($course['sections'] as &$section) {
            $section['support'] = $support[$section['id']];
        }

        return $course;
    }

    public function assessment(): array
    {
        return json_decode(file_get_contents(resource_path('learning/sensory-foundation/server_assessment.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function version(): string
    {
        return hash('sha256', json_encode([$this->coreStudent(), $this->assessment()], JSON_THROW_ON_ERROR));
    }

    public function objectiveScore(array $answers): float
    {
        $score = 0.0;
        foreach ($this->assessment()['questions'] as $question) {
            if ($question['type'] === 'single_choice'
                && isset($answers[$question['id']])
                && (int) $answers[$question['id']] === $question['correct_index']) {
                $score += $question['points'];
            }
        }

        return $score;
    }

    public function summaryCaps(): array
    {
        return [6, 4, 4, 4, 2];
    }

    private function coreStudent(): array
    {
        return json_decode(file_get_contents(resource_path('learning/sensory-foundation/student_content.json')), true, 512, JSON_THROW_ON_ERROR);
    }
}
