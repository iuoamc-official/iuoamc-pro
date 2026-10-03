<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SensoryLearningContent;
use App\Services\SensoryObjectiveExam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CloseExpiredLearningExams extends Command
{
    protected $signature = 'learning:close-expired-exams';

    protected $description = 'Close expired 30-question sensory exams using only server-saved answers';

    public function handle(SensoryObjectiveExam $exam): int
    {
        $ids = DB::table('learning_enrollments')->where('module_id', SensoryLearningContent::MODULE_ID)
            ->where('exam_format', SensoryObjectiveExam::FORMAT)->where('exam_assessment_hash', $exam->version())
            ->whereNull('exam_submitted_at')->where('exam_deadline_at', '<=', now())
            ->orderBy('id')->limit(500)->pluck('id');
        foreach ($ids as $id) {
            $exam->closeExpired((int) $id);
        }
        $this->info('Expired exams processed: '.$ids->count());

        return self::SUCCESS;
    }
}
