<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SensoryLearningContent;
use App\Services\SensoryObjectiveExam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class CheckLearningReadiness extends Command
{
    protected $signature = 'learning:check-readiness';

    protected $description = 'Read-only checks of learning launch configuration; does not certify delivery, backups or cron';

    public function handle(SensoryLearningContent $content, SensoryObjectiveExam $exam): int
    {
        $checks = [
            'Production environment' => app()->environment('production'),
            'Debug disabled' => config('app.debug') === false,
            'Application key configured' => filled(config('app.key')),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'Secure session cookies' => config('session.secure') === true,
            'HTTP-only session cookies' => config('session.http_only') === true,
            'Persistent sessions' => ! in_array(config('session.driver'), ['array', null], true),
            'Mail transport configured (delivery still needs a test)' => ! in_array(config('mail.default'), ['log', 'array', null], true),
            'Writable storage and cache' => is_writable(storage_path('framework')) && is_writable(storage_path('logs')) && is_writable(base_path('bootstrap/cache')),
        ];
        try {
            foreach (['learning_enrollments', 'learning_invitations', 'learning_lesson_notes', 'learning_projects', 'learning_project_events'] as $table) {
                $checks['Database table: '.$table] = Schema::hasTable($table);
            }
            $checks['Invitation archive column'] = Schema::hasColumn('learning_invitations', 'archived_at');
            $checks['Summary draft revision column'] = Schema::hasColumn('learning_enrollments', 'summary_draft_revision');
            $checks['Exam deadline column'] = Schema::hasColumn('learning_enrollments', 'exam_deadline_at');
            $expired = DB::table('learning_enrollments')->where('module_id', SensoryLearningContent::MODULE_ID)
                ->where('exam_format', SensoryObjectiveExam::FORMAT)->whereNull('exam_submitted_at')
                ->where('exam_deadline_at', '<=', now()->subMinutes(2))->count();
            $checks['No unclosed exams overdue by more than two minutes'] = $expired === 0;
        } catch (Throwable) {
            $checks['Database checks completed'] = false;
        }
        try {
            $checks['Thirty learning pages readable'] = count($content->student()['sections']) === 30;
            $checks['Assessment version readable'] = strlen($exam->version()) === 64;
            foreach (range(1, 30) as $section) {
                $content->checkpoint($section);
            }
            $checks['Thirty lesson checkpoints readable'] = true;
        } catch (Throwable) {
            $checks['Learning content checks completed'] = false;
        }
        foreach (['account.learning.index', 'account.learning.exam.start', 'account.learning.assignment.submit', 'account.learning.project', 'learning.control.index'] as $name) {
            $checks['Route: '.$name] = Route::has($name);
        }
        $this->table(['Check', 'Result'], array_map(fn (string $name, bool $passed): array => [$name, $passed ? 'OK' : 'CHECK REQUIRED'], array_keys($checks), array_values($checks)));
        $this->line('Manual launch checks: email verification and password reset delivery; cron execution; database backup and restore; a full student/reviewer pilot on mobile.');
        $this->line('No records were changed. Passing these checks alone does not prove launch readiness.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
