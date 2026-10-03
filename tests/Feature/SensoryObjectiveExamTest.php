<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SensoryLearningContent;
use App\Services\SensoryObjectiveExam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class SensoryObjectiveExamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_09_07_160000_create_iuoamc_core_foundation.php',
            '2026_09_07_230000_add_integrity_to_audit_logs.php',
            '2026_10_02_100000_create_learning_enrollments.php',
            '2026_10_03_093415_create_learning_invitations_table.php',
            '2026_10_03_101349_add_objective_exam_timing_to_learning_enrollments.php',
            '2026_10_03_110905_create_learning_lesson_notes_table.php',
            '2026_10_03_180351_create_learning_projects_and_events_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public function test_exam_start_requires_every_lesson_and_last_lesson_opens_exam_page(): void
    {
        $user = $this->student();
        $id = $this->enroll($user, ['completed_sections' => json_encode(range(1, 29))]);
        $this->actingAs($user)->post('/ar/account/learning/exam/start')->assertForbidden();
        $this->assertNull(DB::table('learning_enrollments')->find($id)->exam_started_at);
        $this->post('/ar/account/learning/lessons/30/complete', ['lesson_answer' => 1])->assertRedirect('/ar/account/learning/exam');
        $this->assertSame(range(1, 30), json_decode(DB::table('learning_enrollments')->find($id)->completed_sections, true));
    }

    public function test_start_pins_thirty_minutes_and_question_order_without_restart(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->student();
        $id = $this->enroll($user, ['completed_sections' => json_encode(range(1, 30))]);
        $this->actingAs($user)->post('/ar/account/learning/exam/start')->assertRedirect('/ar/account/learning/exam');
        $record = DB::table('learning_enrollments')->find($id);
        $this->assertSame(SensoryObjectiveExam::FORMAT, $record->exam_format);
        $this->assertSame(now()->addMinutes(30)->format('Y-m-d H:i:s'), $record->exam_deadline_at);
        $ids = json_decode($record->exam_question_order, true);
        sort($ids);
        $this->assertSame(range(1, 30), $ids);
        $this->travel(12)->minutes();
        $this->post('/ar/account/learning/exam/start')->assertRedirect();
        $after = DB::table('learning_enrollments')->find($id);
        $this->assertSame($record->exam_started_at, $after->exam_started_at);
        $this->assertSame($record->exam_deadline_at, $after->exam_deadline_at);
        $this->assertSame($record->exam_question_order, $after->exam_question_order);
        $this->get('/ar/account/learning/exam')->assertSee('data-remaining-ms="1080000"', false);
    }

    public function test_student_sees_thirty_single_choice_questions_without_answer_keys(): void
    {
        $user = $this->student();
        $this->attempt($user);
        $response = $this->actingAs($user)->get('/ar/account/learning/exam');
        $response->assertOk()->assertDontSee('correct_index')->assertDontSee('rationale')->assertDontSee('<textarea', false)
            ->assertSee('data-question-count="30"', false)->assertSee('data-objective-exam', false);
        $this->assertSame(120, substr_count($response->getContent(), 'type="radio"'));
        foreach ((new SensoryObjectiveExam)->assessment()['questions'] as $question) {
            $response->assertSee($question['prompt']);
        }
    }

    public function test_autosave_returns_revision_and_stale_draft_cannot_overwrite(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->student();
        $id = $this->attempt($user);
        $this->actingAs($user)->postJson('/ar/account/learning/exam', ['action' => 'save', 'answers' => ['1' => 1], 'draft_revision' => 0])
            ->assertOk()->assertJsonPath('revision', 1)->assertJsonPath('submitted', false)->assertJsonPath('remaining_ms', 1800000);
        $this->postJson('/ar/account/learning/exam', ['action' => 'save', 'answers' => ['1' => 0], 'draft_revision' => 0])->assertStatus(409);
        $record = DB::table('learning_enrollments')->find($id);
        $this->assertSame(['1' => 1], json_decode($record->exam_answers, true));
        $this->assertSame(1, $record->exam_draft_revision);
        $this->assertNull($record->objective_score);
        $this->assertNull($record->exam_submitted_at);
    }

    public function test_all_correct_answers_are_marked_automatically_and_submission_is_immutable(): void
    {
        $user = $this->student();
        $id = $this->attempt($user);
        $this->actingAs($user)->postJson('/ar/account/learning/exam', ['action' => 'submit', 'answers' => $this->correctAnswers(), 'draft_revision' => 0, 'objective_score' => 0])
            ->assertOk()->assertJsonPath('submitted', true);
        $record = DB::table('learning_enrollments')->find($id);
        $this->assertSame(30, $record->exam_correct_count);
        $this->assertSame(100.0, (float) $record->objective_score);
        $this->get('/ar/account/learning/exam')->assertSee('100.00 / 100')->assertSee('30 / 30')->assertSee('اجتاز حد الاختبار');
        $this->postJson('/ar/account/learning/exam', ['action' => 'submit', 'answers' => [], 'draft_revision' => 0])->assertStatus(409);
        $this->assertSame($record->exam_answers, DB::table('learning_enrollments')->find($id)->exam_answers);
    }

    public function test_empty_submission_receives_zero_without_manual_grading(): void
    {
        $user = $this->student();
        $id = $this->attempt($user);
        $this->actingAs($user)->postJson('/ar/account/learning/exam', ['action' => 'submit', 'answers' => [], 'draft_revision' => 0])->assertOk();
        $this->assertDatabaseHas('learning_enrollments', ['id' => $id, 'objective_score' => 0, 'exam_correct_count' => 0]);
    }

    public function test_deadline_rejects_incoming_answers_and_marks_only_previous_server_saved_draft(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->student();
        $id = $this->attempt($user);
        $this->actingAs($user)->postJson('/ar/account/learning/exam', ['action' => 'save', 'answers' => ['1' => 1], 'draft_revision' => 0])->assertOk();
        $deadline = now()->addMinutes(30)->format('Y-m-d H:i:s');
        $this->travel(30)->minutes();
        $this->postJson('/ar/account/learning/exam', ['action' => 'save', 'answers' => $this->correctAnswers(), 'draft_revision' => 1, 'exam_deadline_at' => now()->addHours(4), 'duration_minutes' => 240])->assertOk()->assertJsonPath('submitted', true);
        $record = DB::table('learning_enrollments')->find($id);
        $this->assertSame(['1' => 1], json_decode($record->exam_answers, true));
        $this->assertSame(1, $record->exam_correct_count);
        $this->assertSame(3.33, (float) $record->objective_score);
        $this->assertSame($deadline, $record->exam_submitted_at);
    }

    public function test_response_just_before_deadline_is_accepted(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->student();
        $id = $this->attempt($user);
        $this->travel(1799)->seconds();
        $this->actingAs($user)->postJson('/ar/account/learning/exam', ['action' => 'submit', 'answers' => $this->correctAnswers(), 'draft_revision' => 0])->assertOk();
        $this->assertDatabaseHas('learning_enrollments', ['id' => $id, 'exam_correct_count' => 30]);
    }

    public function test_early_client_timeout_does_not_erase_saved_answers_or_end_attempt(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->student();
        $id = $this->attempt($user, ['exam_answers' => '{"1":1}']);
        $this->actingAs($user)->postJson('/ar/account/learning/exam', ['action' => 'expire', 'answers' => [], 'draft_revision' => 0])
            ->assertOk()->assertJsonPath('submitted', false);
        $this->assertDatabaseHas('learning_enrollments', ['id' => $id, 'exam_answers' => '{"1":1}', 'exam_submitted_at' => null]);
    }

    public function test_reopening_after_deadline_closes_and_marks_the_attempt(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->student();
        $id = $this->attempt($user, ['exam_answers' => '{"1":1}']);
        $deadline = now()->addMinutes(30)->format('Y-m-d H:i:s');
        $this->travel(32)->minutes();
        $this->actingAs($user)->get('/ar/account/learning/exam')->assertOk()->assertSee('3.33 / 100')->assertDontSee('data-objective-exam', false);
        $this->assertDatabaseHas('learning_enrollments', ['id' => $id, 'exam_submitted_at' => $deadline, 'exam_correct_count' => 1]);
    }

    public function test_scheduled_command_closes_expired_attempts_without_touching_active_or_legacy_exams(): void
    {
        $this->travelTo(now()->startOfSecond());
        $expiredUser = $this->student();
        $expired = $this->attempt($expiredUser, ['exam_started_at' => now()->subMinutes(40), 'exam_deadline_at' => now()->subMinutes(10), 'exam_answers' => '{"1":1}']);
        $active = $this->attempt($this->student());
        $legacy = $this->enroll($this->student(), ['exam_started_at' => now()->subHours(3)]);
        $this->artisan('learning:close-expired-exams')->assertSuccessful();
        $record = DB::table('learning_enrollments')->find($expired);
        $this->assertSame(1, $record->exam_correct_count);
        $this->assertSame(now()->subMinutes(10)->format('Y-m-d H:i:s'), $record->exam_submitted_at);
        $this->assertNull(DB::table('learning_enrollments')->find($active)->exam_submitted_at);
        $this->assertNull(DB::table('learning_enrollments')->find($legacy)->exam_submitted_at);
        $this->artisan('learning:close-expired-exams')->assertSuccessful();
        $this->assertSame($record->exam_submitted_at, DB::table('learning_enrollments')->find($expired)->exam_submitted_at);
    }

    public function test_invalid_choice_extra_question_and_missing_revision_are_rejected(): void
    {
        $user = $this->student();
        $id = $this->attempt($user);
        $this->actingAs($user)->postJson('/ar/account/learning/exam', ['action' => 'save', 'answers' => ['1' => 4], 'draft_revision' => 0])->assertJsonValidationErrors('answers.1');
        $this->postJson('/ar/account/learning/exam', ['action' => 'save', 'answers' => ['31' => 1], 'draft_revision' => 0])->assertJsonValidationErrors('answers');
        $this->postJson('/ar/account/learning/exam', ['action' => 'save', 'answers' => ['1' => 1]])->assertJsonValidationErrors('draft_revision');
        $this->assertNull(DB::table('learning_enrollments')->find($id)->exam_answers);
    }

    public function test_other_student_cannot_write_to_or_read_an_attempt(): void
    {
        $owner = $this->student();
        $id = $this->attempt($owner);
        $this->actingAs($this->student())->get('/ar/account/learning/exam')->assertNotFound();
        $this->postJson('/ar/account/learning/exam', ['action' => 'submit', 'answers' => $this->correctAnswers(), 'draft_revision' => 0, 'enrollment_id' => $id])->assertNotFound();
        $this->assertNull(DB::table('learning_enrollments')->find($id)->exam_submitted_at);
    }

    public function test_assessment_hash_mismatch_blocks_new_attempt_grading(): void
    {
        $user = $this->student();
        $id = $this->attempt($user, ['exam_assessment_hash' => str_repeat('0', 64)]);
        $this->actingAs($user)->get('/ar/account/learning/exam')->assertStatus(409);
        $this->postJson('/ar/account/learning/exam', ['action' => 'submit', 'answers' => $this->correctAnswers(), 'draft_revision' => 0])->assertStatus(409);
        $this->assertNull(DB::table('learning_enrollments')->find($id)->exam_submitted_at);
    }

    public function test_admin_reviews_only_summary_and_uses_unrounded_exam_fraction(): void
    {
        $user = $this->student();
        $id = $this->attempt($user, ['summary_lines' => '["ملخص"]', 'summary_submitted_at' => now()]);
        $this->actingAs($user)->postJson('/ar/account/learning/exam', ['action' => 'submit', 'answers' => array_slice($this->correctAnswers(), 0, 23, true), 'draft_revision' => 0])->assertOk();
        $this->assertDatabaseHas('learning_enrollments', ['id' => $id, 'objective_score' => 76.67, 'exam_correct_count' => 23]);
        $admin = $this->admin();
        $this->actingAs($admin)->get('/ar/control/learning/'.$id)->assertOk()->assertDontSee('name="question_scores[', false);
        $this->post('/ar/control/learning/'.$id.'/review', ['summary_scores' => [6, 4, 4, 4, 2], 'feedback' => 'مراجعة ملخص البحث'])
            ->assertRedirect('/ar/control/learning/'.$id);
        $this->assertDatabaseHas('learning_enrollments', ['id' => $id, 'final_score' => 81.33, 'reviewed_by' => $admin->id]);
    }

    public function test_admin_cannot_add_manual_exam_points_to_automatic_result(): void
    {
        $user = $this->student();
        $id = $this->attempt($user, ['exam_submitted_at' => now(), 'exam_correct_count' => 0, 'objective_score' => 0, 'summary_submitted_at' => now()]);
        $this->actingAs($this->admin())->post('/ar/control/learning/'.$id.'/review', ['question_scores' => ['25' => 100], 'summary_scores' => [6, 4, 4, 4, 2], 'feedback' => 'تقييم'])
            ->assertSessionHasErrors('question_scores');
        $this->assertNull(DB::table('learning_enrollments')->find($id)->final_score);
    }

    private function correctAnswers(): array
    {
        $answers = [];
        foreach ((new SensoryObjectiveExam)->assessment()['questions'] as $question) {
            $answers[$question['id']] = $question['correct_index'];
        }

        return $answers;
    }

    private function student(): User
    {
        return User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    }

    private function enroll(User $user, array $values = []): int
    {
        return DB::table('learning_enrollments')->insertGetId($values + [
            'user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID,
            'content_version' => (new SensoryLearningContent)->version(), 'enrolled_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function attempt(User $user, array $values = []): int
    {
        return $this->enroll($user, $values + [
            'completed_sections' => json_encode(range(1, 30)), 'exam_format' => SensoryObjectiveExam::FORMAT,
            'exam_started_at' => now(), 'exam_deadline_at' => now()->addMinutes(30),
            'exam_assessment_hash' => (new SensoryObjectiveExam)->version(), 'exam_question_order' => json_encode(range(1, 30)),
        ]);
    }

    private function admin(): User
    {
        $directory = storage_path('app/secure/integrity');
        File::ensureDirectoryExists($directory);
        $pair = sodium_crypto_sign_seed_keypair(str_repeat('t', 32));
        file_put_contents($directory.'/ed25519.secret', base64_encode(sodium_crypto_sign_secretkey($pair)));
        file_put_contents($directory.'/ed25519.public', base64_encode(sodium_crypto_sign_publickey($pair)));
        $user = $this->student();
        $role = Role::query()->create(['name' => 'Super admin', 'slug' => 'super-admin']);
        $user->roles()->attach($role);

        return $user;
    }
}
