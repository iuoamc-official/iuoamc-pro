<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SensoryLearningContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class SensoryLearningTest extends TestCase
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
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public function test_learning_requires_login_and_verified_active_account(): void
    {
        $this->get('/ar/account/learning')->assertRedirect('/ar/login');
        $user = User::factory()->unverified()->create(['status' => 'active']);
        $this->actingAs($user)->get('/ar/account/learning')->assertRedirect('/ar/verify-email');
        $user->forceFill(['email_verified_at' => now(), 'status' => 'inactive'])->save();
        $this->actingAs($user)->get('/ar/account/learning')->assertForbidden();
    }

    public function test_unenrolled_account_cannot_read_lessons_or_submit_an_exam(): void
    {
        $user = $this->student();
        $this->actingAs($user)->get('/ar/account/learning')->assertOk()->assertSee('التسجيل في الوحدة مطلوب')->assertDontSee('من الخبرة المهنية إلى المعرفة');
        $this->get('/ar/account/learning/lessons/1')->assertNotFound();
        $this->post('/ar/account/learning/exam/start')->assertNotFound();
    }

    public function test_enrolled_student_reads_arabic_lessons_without_answer_keys(): void
    {
        $user = $this->student();
        $this->enroll($user);
        $this->actingAs($user)->get('/ar/account/learning')->assertOk()->assertSee('من الخبرة المهنية إلى المعرفة');
        $this->get('/ar/account/learning/lessons/1')->assertOk()->assertSee('سؤال إتمام الصفحة')->assertDontSee('correct_index');
        $this->get('/ar/account/learning/lessons/30')->assertRedirect('/ar/account/learning/lessons/1');
        $this->get('/ar/account/learning/lessons/31')->assertNotFound();
        $this->get('/ar/account/learning/exam')->assertOk()->assertDontSee('correct_index')->assertDontSee('rationale');
    }

    public function test_progress_is_owned_idempotent_and_required_to_start_exam(): void
    {
        $user = $this->student();
        $other = $this->student();
        $id = $this->enroll($user);
        $otherId = $this->enroll($other);
        $this->actingAs($user)->post('/ar/account/learning/exam/start')->assertForbidden();
        $this->post('/ar/account/learning/lessons/1/complete', ['lesson_answer' => 0])->assertRedirect();
        $this->post('/ar/account/learning/lessons/1/complete')->assertRedirect();
        $this->assertSame([1], json_decode(DB::table('learning_enrollments')->find($id)->completed_sections, true));
        $this->assertNull(DB::table('learning_enrollments')->find($otherId)->completed_sections);
        $this->post('/ar/account/learning/lessons/31/complete')->assertNotFound();
        DB::table('learning_enrollments')->where('id', $id)->update(['completed_sections' => json_encode(range(1, 30))]);
        $this->freezeTime();
        $this->post('/ar/account/learning/exam/start')->assertRedirect();
        $started = DB::table('learning_enrollments')->find($id)->exam_started_at;
        $this->travel(10)->minutes();
        $this->post('/ar/account/learning/exam/start')->assertRedirect();
        $this->assertSame($started, DB::table('learning_enrollments')->find($id)->exam_started_at);
    }

    public function test_server_scores_multiple_choice_and_freezes_submitted_answers(): void
    {
        $user = $this->student();
        $id = $this->enroll($user, ['exam_started_at' => now()]);
        $this->actingAs($user)->post('/ar/account/learning/exam', [
            'action' => 'save', 'answers' => [1 => 1, 2 => 3, 25 => '<script>alert(1)</script>'], 'objective_score' => 48,
        ])->assertRedirect();
        $this->assertNull(DB::table('learning_enrollments')->find($id)->objective_score);
        $this->get('/ar/account/learning/exam')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post('/ar/account/learning/exam', ['action' => 'submit', 'answers' => [1 => 1, 2 => 3, 3 => 0]])->assertRedirect();
        $this->assertSame(4.0, (float) DB::table('learning_enrollments')->find($id)->objective_score);
        $this->post('/ar/account/learning/exam', ['action' => 'submit', 'answers' => [1 => 0]])->assertStatus(409);
        $this->assertSame(1, json_decode(DB::table('learning_enrollments')->find($id)->exam_answers, true)[1]);
        $this->get('/ar/account/learning/exam')->assertOk()->assertDontSee('الإجابة الصحيحة');
    }

    public function test_expired_exam_accepts_only_previous_server_saved_draft(): void
    {
        $this->freezeTime();
        $user = $this->student();
        $id = $this->enroll($user, ['exam_started_at' => now()->subMinutes(121), 'exam_answers' => json_encode([1 => 1])]);
        $this->actingAs($user)->post('/ar/account/learning/exam', ['action' => 'save', 'answers' => [2 => 3]])
            ->assertSessionHasErrors('answers');
        $this->post('/ar/account/learning/exam', ['action' => 'submit', 'answers' => [1 => 0, 2 => 3]])->assertRedirect();
        $record = DB::table('learning_enrollments')->find($id);
        $this->assertSame([1 => 1], json_decode($record->exam_answers, true));
        $this->assertSame(2.0, (float) $record->objective_score);
    }

    public function test_exam_rejects_unknown_question_and_invalid_option(): void
    {
        $user = $this->student();
        $this->enroll($user, ['exam_started_at' => now()]);
        $this->actingAs($user)->post('/ar/account/learning/exam', ['action' => 'submit', 'answers' => [1 => 4]])->assertSessionHasErrors('answers.1');
        $this->post('/ar/account/learning/exam', ['action' => 'submit', 'answers' => [41 => 'spoof']])->assertSessionHasErrors('answers');
    }

    public function test_assignment_requires_ten_entries_and_total_word_range_and_is_immutable(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/assignment', ['lines' => ['مختصر']])->assertSessionHasErrors('lines');
        $this->post('/ar/account/learning/assignment', ['lines' => array_fill(0, 10, 'مختصر')])->assertSessionHasErrors(['lines' => 'يجب أن يكون مجموع الملخص بين 180 و250 كلمة.']);
        $lines = array_fill(0, 10, trim(str_repeat('بحث علمي ', 10)));
        $this->post('/ar/account/learning/assignment', ['lines' => $lines])->assertRedirect();
        $this->assertSame($lines, json_decode(DB::table('learning_enrollments')->find($id)->summary_lines, true));
        $this->post('/ar/account/learning/assignment', ['lines' => $lines])->assertStatus(409);
    }

    public function test_students_cannot_access_control_or_another_enrollment(): void
    {
        $user = $this->student();
        $other = $this->student();
        $id = $this->enroll($other);
        $this->actingAs($user)->get('/ar/control/learning')->assertForbidden();
        $this->get('/ar/control/learning/'.$id)->assertForbidden();
        $this->post('/ar/control/learning/enroll', ['email' => $user->email])->assertForbidden();
        $this->assertDatabaseMissing('learning_enrollments', ['user_id' => $user->id]);
    }

    public function test_admin_enrolls_once_with_audit_and_sees_answers(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $this->actingAs($admin)->post('/ar/control/learning/enroll', ['email' => $student->email])->assertRedirect();
        $this->post('/ar/control/learning/enroll', ['email' => $student->email])->assertRedirect();
        $this->assertSame(1, DB::table('learning_enrollments')->where('user_id', $student->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'learning.enrolled']);
        $id = DB::table('learning_enrollments')->where('user_id', $student->id)->value('id');
        $this->get('/ar/control/learning')->assertOk()->assertSee($student->email);
        $this->get('/ar/control/learning/'.$id)->assertOk()->assertSee('الإجابة الصحيحة');
    }

    public function test_review_calculates_final_score_server_side_and_records_audit(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $id = $this->enroll($student, ['exam_submitted_at' => now(), 'summary_submitted_at' => now(), 'objective_score' => 48]);
        $scores = array_combine(range(25, 40), array_merge(array_fill(0, 8, 3), array_fill(0, 8, 3.5)));
        $this->actingAs($admin)->post('/ar/control/learning/'.$id.'/review', [
            'question_scores' => $scores, 'summary_scores' => [6, 4, 4, 4, 2], 'feedback' => '<script>excellent</script>', 'final_score' => 0,
        ])->assertRedirect();
        $record = DB::table('learning_enrollments')->find($id);
        $this->assertSame(100.0, (float) $record->final_score);
        $this->assertSame($admin->id, $record->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'learning.reviewed']);
        $this->actingAs($student)->get('/ar/account/learning')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>excellent</script>', false)->assertSee('100');
    }

    public function test_review_requires_both_submissions_and_rejects_scores_over_caps(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $id = $this->enroll($student);
        $payload = ['question_scores' => array_combine(range(25, 40), array_fill(0, 16, 0)), 'summary_scores' => [0, 0, 0, 0, 0], 'feedback' => 'needs review'];
        $this->actingAs($admin)->post('/ar/control/learning/'.$id.'/review', $payload)->assertStatus(409);
        $payload['question_scores'][25] = 4;
        $payload['summary_scores'][0] = 7;
        $this->post('/ar/control/learning/'.$id.'/review', $payload)->assertSessionHasErrors(['question_scores.25', 'summary_scores.0']);
        $this->assertNull(DB::table('learning_enrollments')->find($id)->final_score);
    }

    public function test_content_version_change_prevents_mismatched_exam_or_review(): void
    {
        $user = $this->student();
        $this->enroll($user, ['content_version' => str_repeat('0', 64)]);
        $this->actingAs($user)->get('/ar/account/learning/exam')->assertStatus(409);
    }

    public function test_checkpoint_requires_a_correct_answer_and_blocks_direct_skipping(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $this->actingAs($user)->from('/ar/account/learning/lessons/1')
            ->post('/ar/account/learning/lessons/1/complete')
            ->assertSessionHasErrors(['lesson_answer' => 'اختر إجابة قبل إتمام الصفحة.']);
        $this->post('/ar/account/learning/lessons/1/complete', ['lesson_answer' => 2])
            ->assertSessionHasErrors(['lesson_answer' => 'الإجابة غير صحيحة. راجع المادة ثم حاول مجددًا؛ لم تُفتح الصفحة التالية بعد.']);
        $this->assertNull(DB::table('learning_enrollments')->find($id)->completed_sections);
        $this->get('/ar/account/learning/lessons/2')->assertRedirect('/ar/account/learning/lessons/1');
        $this->post('/ar/account/learning/lessons/2/complete', ['lesson_answer' => 1])->assertForbidden();
        $this->post('/ar/account/learning/lessons/1/complete', ['lesson_answer' => [0]])->assertSessionHasErrors('lesson_answer');
        $this->post('/ar/account/learning/lessons/1/complete', ['lesson_answer' => 4])->assertSessionHasErrors('lesson_answer');
        $this->assertNull(DB::table('learning_enrollments')->find($id)->completed_sections);

        $this->post('/ar/account/learning/lessons/1/complete', ['lesson_answer' => 0, 'completed_sections' => range(1, 30)])
            ->assertRedirect('/ar/account/learning/lessons/2');
        $this->assertSame([1], json_decode(DB::table('learning_enrollments')->find($id)->completed_sections, true));
        $this->get('/ar/account/learning/lessons/2')->assertOk()->assertSee('سؤال إتمام الصفحة');
        $this->get('/ar/account/learning/lessons/3')->assertRedirect('/ar/account/learning/lessons/2');
        $this->post('/ar/account/learning/exam/start')->assertForbidden();
    }

    public function test_all_thirty_checkpoints_unlock_in_order_without_leaking_final_exam_answers(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $content = new SensoryLearningContent;
        $this->actingAs($user);
        for ($section = 1; $section <= 30; $section++) {
            $checkpoint = $content->checkpoint($section);
            $response = $this->get('/ar/account/learning/lessons/'.$section)->assertOk()->assertSee($checkpoint['prompt']);
            $response->assertViewHas('checkpoint', fn (array $value): bool => array_keys($value) === ['prompt', 'options']);
            $this->assertCount(4, $checkpoint['options']);
            $destination = $section === 30 ? '/ar/account/learning/exam' : '/ar/account/learning/lessons/'.($section + 1);
            $this->post('/ar/account/learning/lessons/'.$section.'/complete', ['lesson_answer' => $checkpoint['correct_index']])
                ->assertRedirect($destination);
        }
        $this->assertSame(range(1, 30), json_decode(DB::table('learning_enrollments')->find($id)->completed_sections, true));
        $this->assertNull(DB::table('learning_enrollments')->find($id)->exam_started_at);
        $this->get('/ar/account/learning/lessons/30')->assertSee('تم إتمام هذه الصفحة')->assertDontSee('name="lesson_answer"', false);
        $this->post('/ar/account/learning/exam/start')->assertRedirect('/ar/account/learning/exam');
    }

    public function test_existing_completed_pages_remain_available_while_first_missing_page_gates_future_pages(): void
    {
        $user = $this->student();
        $id = $this->enroll($user, ['completed_sections' => '[1,3,30]']);
        $this->actingAs($user)->get('/ar/account/learning/lessons/30')->assertOk()->assertSee('تم إتمام هذه الصفحة');
        $this->get('/ar/account/learning/lessons/2')->assertOk();
        $this->get('/ar/account/learning/lessons/4')->assertRedirect('/ar/account/learning/lessons/2');
        $this->get('/ar/account/learning')->assertSee('مقفلة')->assertDontSee('href="http://localhost/ar/account/learning/lessons/4"', false);
        $this->post('/ar/account/learning/lessons/1/complete')->assertRedirect('/ar/account/learning/lessons/2');
        $this->assertSame([1, 3, 30], json_decode(DB::table('learning_enrollments')->find($id)->completed_sections, true));
        $this->post('/ar/account/learning/lessons/2/complete', ['lesson_answer' => 1])->assertRedirect('/ar/account/learning/lessons/3');
        $this->get('/ar/account/learning/lessons/4')->assertOk();
    }

    private function student(): User
    {
        return User::factory()->create(['status' => 'active', 'must_change_password' => false]);
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

    private function enroll(User $user, array $values = []): int
    {
        return DB::table('learning_enrollments')->insertGetId($values + [
            'user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID,
            'content_version' => (new SensoryLearningContent)->version(), 'enrolled_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
