<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SensoryLearningContent;
use App\Services\SensoryObjectiveExam;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LearningExperienceTest extends TestCase
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
            '2026_10_04_094115_add_summary_draft_revision_to_learning_enrollments.php',
            '2026_10_04_100941_add_archived_at_to_learning_invitations.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public function test_notes_are_private_persisted_escaped_and_available_after_completion(): void
    {
        $user = $this->student();
        $other = $this->student();
        $id = $this->enroll($user);
        $otherId = $this->enroll($other);
        $this->actingAs($user)->post('/ar/account/learning/lessons/1/notes', [
            'note_body' => '<script>alert("note")</script>', 'note_revision' => 0, 'enrollment_id' => $otherId,
        ])->assertRedirect('/ar/account/learning/lessons/1#lesson-notes');
        $this->assertDatabaseHas('learning_lesson_notes', ['enrollment_id' => $id, 'section' => 1, 'revision' => 1, 'body' => '<script>alert("note")</script>']);
        $this->assertDatabaseMissing('learning_lesson_notes', ['enrollment_id' => $otherId]);
        $this->get('/ar/account/learning/lessons/1')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("note")</script>', false);
        $this->actingAs($other)->get('/ar/account/learning/lessons/1')->assertDontSee('alert(&quot;note&quot;)', false);
        DB::table('learning_enrollments')->where('id', $id)->update(['completed_sections' => json_encode(range(1, 30))]);
        $this->actingAs($user)->post('/ar/account/learning/lessons/1/notes', ['note_body' => 'ملاحظة بعد إتمام المادة', 'note_revision' => 1])->assertRedirect();
        $this->get('/ar/account/learning/lessons/1')->assertSee('ملاحظة بعد إتمام المادة');
    }

    public function test_stale_note_revision_cannot_overwrite_a_saved_note_or_change_progress(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/lessons/1/notes', ['note_body' => 'First version', 'note_revision' => 0])->assertRedirect();
        $this->from('/ar/account/learning/lessons/1')->post('/ar/account/learning/lessons/1/notes', ['note_body' => 'Stale version', 'note_revision' => 0])->assertSessionHasErrors('note_revision')->assertSessionHasInput('note_body', 'Stale version');
        $this->assertDatabaseHas('learning_lesson_notes', ['enrollment_id' => $id, 'body' => 'First version', 'revision' => 1]);
        $this->assertNull(DB::table('learning_enrollments')->find($id)->completed_sections);
        $this->assertNull(DB::table('learning_enrollments')->find($id)->exam_started_at);
    }

    public function test_note_validation_and_locked_pages_do_not_create_notes(): void
    {
        $user = $this->student();
        $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/lessons/1/notes', [])->assertSessionHasErrors(['note_body', 'note_revision']);
        $this->post('/ar/account/learning/lessons/1/notes', ['note_body' => str_repeat('x', 12001), 'note_revision' => 0])->assertSessionHasErrors('note_body');
        $this->post('/ar/account/learning/lessons/1/notes', ['note_body' => ['invalid'], 'note_revision' => -1])->assertSessionHasErrors(['note_body', 'note_revision']);
        $this->post('/ar/account/learning/lessons/2/notes', ['note_body' => 'Skip', 'note_revision' => 0])->assertForbidden();
        $this->post('/ar/account/learning/lessons/31/notes', ['note_body' => '', 'note_revision' => 0])->assertNotFound();
        $this->assertDatabaseCount('learning_lesson_notes', 0);
    }

    public function test_note_and_report_routes_require_an_enrolled_verified_active_account(): void
    {
        $this->post('/ar/account/learning/lessons/1/notes', [])->assertRedirect('/ar/login');
        $this->get('/ar/account/learning/report')->assertRedirect('/ar/login');
        $user = $this->student();
        $this->actingAs($user)->post('/ar/account/learning/lessons/1/notes', ['note_body' => '', 'note_revision' => 0])->assertNotFound();
        $this->get('/ar/account/learning/report')->assertNotFound();
        $this->enroll($user);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->post('/ar/account/learning/lessons/1/notes', [])->assertRedirect('/ar/verify-email');
        $user->forceFill(['email_verified_at' => now(), 'status' => 'inactive'])->save();
        $this->get('/ar/account/learning/report')->assertForbidden();
    }

    public function test_report_requires_complete_material_both_submissions_and_approved_review(): void
    {
        $user = $this->student();
        $id = $this->enroll($user, ['completed_sections' => json_encode(range(1, 30)), 'exam_submitted_at' => now(), 'summary_submitted_at' => now()]);
        $this->actingAs($user)->get('/ar/account/learning/report')->assertStatus(409);
        DB::table('learning_enrollments')->where('id', $id)->update(['reviewed_at' => now(), 'final_score' => 86]);
        $this->get('/ar/account/learning/report')->assertOk()->assertSee('IUOAMC-LRN-'.$id);
        DB::table('learning_enrollments')->where('id', $id)->update(['completed_sections' => '[1]']);
        $this->get('/ar/account/learning/report')->assertStatus(409);
    }

    public function test_report_is_owned_printable_and_escapes_student_identity_and_feedback(): void
    {
        $user = $this->student(['name' => '<img src=x onerror=alert(1)>']);
        $other = $this->student();
        $this->enroll($other, $this->reviewed());
        $id = $this->enroll($user, $this->reviewed() + ['review_feedback' => '<script>secretFeedback()</script>']);
        $response = $this->actingAs($user)->get('/ar/account/learning/report')->assertOk()
            ->assertSee('IUOAMC-LRN-'.$id)->assertSee('86.00 / 100')->assertSee('18 / 20')
            ->assertSee('data-learning-print', false)->assertSee('لا يُعد بذاته شهادة تخرج')
            ->assertDontSee('<img src=x onerror=alert(1)>', false)->assertSee('&lt;script&gt;', false);
        $response->assertViewHas('record', fn ($record): bool => $record->user_id === $user->id);
        $this->actingAs($other)->get('/ar/account/learning/report')->assertDontSee('secretFeedback');
        $this->get('/ar/control/learning/'.$id.'/report')->assertForbidden();
    }

    public function test_dashboard_and_assignment_show_progress_rubric_and_reviewed_feedback(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $this->actingAs($user)->get('/ar/account/learning')->assertSee('مسارك التعليمي')->assertSee('يدرس المادة');
        $this->get('/ar/account/learning/assignment')->assertSee('معايير تقييم ملخص البحث')->assertSee('دقة عرض البحث')->assertDontSee('الدرجة الممنوحة');
        DB::table('learning_enrollments')->where('id', $id)->update($this->reviewed() + ['review_feedback' => 'نقد منهجي جيد']);
        $this->get('/ar/account/learning')->assertSee('تقرير الإنجاز متاح')->assertSee('86.00 / 100');
        $this->get('/ar/account/learning/assignment')->assertSee('الدرجة الممنوحة')->assertSee('نقد منهجي جيد');
    }

    public function test_completion_explains_the_concept_without_exposing_final_exam_keys(): void
    {
        $user = $this->student();
        $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/lessons/1/complete', ['lesson_answer' => 0])
            ->assertSessionHas('checkpoint_feedback.section', 1);
        $this->get('/ar/account/learning/lessons/2')->assertSee('صفحة 1 · تثبيت المفهوم')->assertSee('افصل ما لاحظته عن السبب الذي تقترحه.')
            ->assertDontSee('correct_index')->assertSee('data-reader-action="night"', false);
    }

    public function test_supervisor_dashboard_search_filters_and_report_permissions(): void
    {
        $studying = $this->student(['name' => 'SearchingLearner']);
        $reviewed = $this->student(['name' => 'ReviewedLearner']);
        $this->enroll($studying);
        $reviewedId = $this->enroll($reviewed, $this->reviewed());
        $admin = $this->student();
        $role = Role::query()->create(['name' => 'Super admin', 'slug' => 'super-admin']);
        $admin->roles()->attach($role);
        $this->actingAs($admin)->get('/ar/control/learning?state=reviewed')->assertSee('ReviewedLearner')->assertDontSee('SearchingLearner');
        $this->get('/ar/control/learning?search=SearchingLearner')->assertSee('SearchingLearner')->assertDontSee('ReviewedLearner');
        $this->get('/ar/control/learning?search=none')->assertSee('لا توجد نتائج مطابقة.');
        $this->get('/ar/control/learning?state=invalid')->assertSessionHasErrors('state');
        $this->get('/ar/control/learning/'.$reviewedId.'/report')->assertOk()->assertSee('ReviewedLearner');
        $this->get('/ar/control/learning/'.$reviewedId)->assertSee('تقرير إنجاز الوحدة التعليمية');
    }

    public function test_dashboard_closes_expired_exam_and_shows_its_saved_result(): void
    {
        $user = $this->student();
        $question = (new SensoryObjectiveExam)->assessment()['questions'][0];
        $id = $this->enroll($user, ['completed_sections' => json_encode(range(1, 30)),
            'exam_format' => SensoryObjectiveExam::FORMAT, 'exam_assessment_hash' => (new SensoryObjectiveExam)->version(),
            'exam_started_at' => now()->subHour(), 'exam_deadline_at' => now()->subMinutes(30),
            'exam_answers' => json_encode([$question['id'] => $question['correct_index']])]);
        $this->actingAs($user)->get('/ar/account/learning')->assertOk()->assertSee('3.33 / 100');
        $record = DB::table('learning_enrollments')->find($id);
        $this->assertSame(1, $record->exam_correct_count);
        $this->assertSame($record->exam_deadline_at, $record->exam_submitted_at);
    }

    private function student(array $values = []): User
    {
        return User::factory()->create($values + ['status' => 'active', 'must_change_password' => false]);
    }

    private function enroll(User $user, array $values = []): int
    {
        return DB::table('learning_enrollments')->insertGetId($values + [
            'user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID,
            'content_version' => (new SensoryLearningContent)->version(), 'enrolled_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function reviewed(): array
    {
        return ['completed_sections' => json_encode(range(1, 30)), 'exam_format' => SensoryObjectiveExam::FORMAT,
            'exam_assessment_hash' => (new SensoryObjectiveExam)->version(), 'objective_score' => 85,
            'exam_started_at' => now()->subHour(), 'exam_deadline_at' => now()->subMinutes(30), 'exam_submitted_at' => now()->subMinutes(35),
            'summary_submitted_at' => now(), 'summary_scores' => '[6,4,3,3,2]', 'reviewed_at' => now(), 'final_score' => 86];
    }
}
