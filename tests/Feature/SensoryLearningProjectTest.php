<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SensoryLearningContent;
use App\Services\SensoryLearningProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class SensoryLearningProjectTest extends TestCase
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

    public function test_project_requires_login_verified_active_account_and_enrollment(): void
    {
        $this->get('/ar/account/learning/project')->assertRedirect('/ar/login');
        $this->post('/ar/account/learning/project', $this->submission())->assertRedirect('/ar/login');
        $user = $this->student();
        $this->actingAs($user)->get('/ar/account/learning/project')->assertNotFound();
        $this->post('/ar/account/learning/project', $this->submission())->assertNotFound();
        $this->enroll($user);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->get('/ar/account/learning/project')->assertRedirect('/ar/verify-email');
        $user->forceFill(['email_verified_at' => now(), 'status' => 'inactive'])->save();
        $this->post('/ar/account/learning/project', $this->submission())->assertForbidden();
        $this->assertDatabaseCount('learning_projects', 0);
    }

    public function test_project_unlocks_only_after_all_material_pages(): void
    {
        $user = $this->student();
        $id = $this->enroll($user, ['completed_sections' => json_encode(range(1, 29))]);
        $this->actingAs($user)->get('/ar/account/learning/project')->assertSee('يُفتح المشروع بعد إتمام صفحات المادة الثلاثين')->assertDontSee('data-learning-project', false);
        $this->post('/ar/account/learning/project', $this->submission())->assertForbidden();
        DB::table('learning_enrollments')->where('id', $id)->update(['completed_sections' => json_encode(range(1, 30))]);
        $this->get('/ar/account/learning/project')->assertSee('data-learning-project', false);
        $this->assertDatabaseCount('learning_projects', 0);
    }

    public function test_partial_draft_is_private_and_does_not_create_submission_or_change_unit_grades(): void
    {
        $user = $this->student();
        $other = $this->student();
        $id = $this->enroll($user, ['final_score' => 88, 'objective_score' => 90, 'exam_answers' => '{"1":2}']);
        $otherId = $this->enroll($other);
        $this->actingAs($user)->post('/ar/account/learning/project', ['action' => 'save', 'revision' => 0, 'fields' => ['question' => 'Private first draft'], 'enrollment_id' => $otherId])->assertRedirect();
        $project = $this->project($id);
        $this->assertSame('draft', $project->status);
        $this->assertSame(1, $project->revision);
        $this->assertSame(0, $project->submission_number);
        $this->assertSame('Private first draft', json_decode($project->fields, true)['question']);
        $this->assertDatabaseMissing('learning_projects', ['enrollment_id' => $otherId]);
        $this->assertDatabaseCount('learning_project_events', 0);
        $this->assertSame(88.0, (float) DB::table('learning_enrollments')->find($id)->final_score);
        $this->assertSame('{"1":2}', DB::table('learning_enrollments')->find($id)->exam_answers);
        $this->actingAs($other)->get('/ar/account/learning/project?project='.$project->id)->assertDontSee('Private first draft');
        $this->actingAs($this->admin())->get('/ar/control/learning/'.$id.'/project')->assertDontSee('Private first draft');
    }

    public function test_submission_requires_all_sections_and_truthfulness_declarations(): void
    {
        $user = $this->student();
        $this->enroll($user);
        $payload = $this->submission();
        $payload['fields']['question'] = 'Short';
        unset($payload['originality'], $payload['data_truth'], $payload['fields']['ethics']);
        $this->actingAs($user)->post('/ar/account/learning/project', $payload)->assertSessionHasErrors(['fields.question', 'fields.ethics', 'originality', 'data_truth']);
        $this->assertDatabaseCount('learning_projects', 0);
        $this->assertDatabaseCount('learning_project_events', 0);
    }

    public function test_unknown_fields_invalid_action_and_oversized_drafts_are_rejected(): void
    {
        $user = $this->student();
        $this->enroll($user);
        $payload = $this->submission();
        $payload['action'] = 'approve';
        $payload['fields']['unknown'] = 'Injected';
        $payload['revision'] = -1;
        $this->actingAs($user)->post('/ar/account/learning/project', $payload)->assertSessionHasErrors(['action', 'fields', 'revision']);
        $payload = ['action' => 'save', 'revision' => 0, 'fields' => ['analysis' => str_repeat('x', 2001)]];
        $this->post('/ar/account/learning/project', $payload)->assertSessionHasErrors('fields.analysis');
        $this->assertDatabaseCount('learning_projects', 0);
    }

    public function test_submitted_version_is_snapshotted_and_locked_against_student_edits(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/project', $this->submission())->assertRedirect('/ar/account/learning/project');
        $project = $this->project($id);
        $this->assertSame('submitted', $project->status);
        $this->assertSame(1, $project->submission_number);
        $event = DB::table('learning_project_events')->first();
        $snapshot = json_decode($event->payload, true);
        $this->assertSame($this->fields(), $snapshot['fields']);
        $this->assertTrue($snapshot['data_truth_confirmed']);
        $this->assertSame($user->id, $event->actor_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'learning.project_submitted', 'actor_id' => $user->id]);
        $this->post('/ar/account/learning/project', ['action' => 'save', 'revision' => 1, 'fields' => []])->assertStatus(409);
        $this->assertSame($project->fields, $this->project($id)->fields);
        $this->assertDatabaseCount('learning_project_events', 1);
        $this->get('/ar/account/learning/project')->assertDontSee('data-learning-project', false)->assertSee('المشرف يراجع هذه النسخة');
    }

    public function test_revision_cycle_keeps_both_versions_reviews_and_existing_grade(): void
    {
        $user = $this->student();
        $admin = $this->admin();
        $id = $this->enroll($user, ['final_score' => 88]);
        $this->actingAs($user)->post('/ar/account/learning/project', $this->submission())->assertRedirect();
        $this->actingAs($admin)->post('/ar/control/learning/'.$id.'/project/review', $this->review('revise', 1))->assertRedirect();
        $this->assertSame('revision_requested', $this->project($id)->status);
        $this->actingAs($user)->get('/ar/account/learning/project')->assertSee('data-learning-project', false)->assertSee('ملاحظات تطوير المشروع');
        $fields = $this->fields();
        $fields['design'] = 'Improved randomized blinded design with balanced sample order and a reference condition.';
        $this->post('/ar/account/learning/project', ['action' => 'save', 'revision' => 2, 'fields' => $fields])->assertRedirect();
        $this->assertSame('revision_requested', $this->project($id)->status);
        $payload = $this->submission(3);
        $payload['fields'] = $fields;
        $this->post('/ar/account/learning/project', $payload)->assertRedirect();
        $this->assertSame(2, $this->project($id)->submission_number);
        $this->assertNull($this->project($id)->score);
        $this->actingAs($admin)->post('/ar/control/learning/'.$id.'/project/review', $this->review('approve', 4))->assertRedirect();
        $project = $this->project($id);
        $this->assertSame('approved', $project->status);
        $this->assertSame(18.0, (float) $project->score);
        $this->assertSame(88.0, (float) DB::table('learning_enrollments')->find($id)->final_score);
        $events = DB::table('learning_project_events')->orderBy('id')->get();
        $this->assertSame(['submitted', 'revision_requested', 'submitted', 'approved'], $events->pluck('event')->all());
        $this->assertSame($this->fields()['design'], json_decode($events[0]->payload, true)['fields']['design']);
        $this->assertSame($fields['design'], json_decode($events[2]->payload, true)['fields']['design']);
        $this->actingAs($user)->post('/ar/account/learning/project', $this->submission(5))->assertStatus(409);
        $this->actingAs($admin)->post('/ar/control/learning/'.$id.'/project/review', $this->review('revise', 5))->assertStatus(409);
        $this->assertDatabaseCount('learning_project_events', 4);
    }

    public function test_stale_student_revision_preserves_unsaved_input_without_overwriting(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/project', ['action' => 'save', 'revision' => 0, 'fields' => ['question' => 'Saved draft']])->assertRedirect();
        $this->from('/ar/account/learning/project')->post('/ar/account/learning/project', ['action' => 'save', 'revision' => 0, 'fields' => ['question' => 'Unsaved stale draft']])
            ->assertSessionHasErrors('revision')->assertSessionHasInput('fields.question', 'Unsaved stale draft');
        $this->assertSame('Saved draft', json_decode($this->project($id)->fields, true)['question']);
        $this->assertSame(1, $this->project($id)->revision);
    }

    public function test_stale_supervisor_review_cannot_approve_a_later_version(): void
    {
        $user = $this->student();
        $admin = $this->admin();
        $id = $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/project', $this->submission())->assertRedirect();
        $this->actingAs($admin)->post('/ar/control/learning/'.$id.'/project/review', $this->review('revise', 1))->assertRedirect();
        $this->actingAs($user)->post('/ar/account/learning/project', $this->submission(2))->assertRedirect();
        $this->actingAs($admin)->post('/ar/control/learning/'.$id.'/project/review', $this->review('approve', 1))->assertSessionHasErrors('revision');
        $this->assertSame('submitted', $this->project($id)->status);
        $this->assertSame(2, $this->project($id)->submission_number);
        $this->assertDatabaseCount('learning_project_events', 3);
    }

    public function test_approval_requires_total_threshold_and_a_minimum_in_every_criterion(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/project', $this->submission())->assertRedirect();
        $this->actingAs($this->admin());
        foreach ([[2, 2, 2, 2, 2], [4, 4, 4, 4, 0]] as $scores) {
            $payload = $this->review('approve', 1);
            $payload['rubric_scores'] = $scores;
            $this->post('/ar/control/learning/'.$id.'/project/review', $payload)->assertSessionHasErrors(['rubric_scores' => 'الاعتماد يتطلب 15/20 على الأقل، ودرجتين على الأقل في كل معيار.']);
        }
        $this->assertSame('submitted', $this->project($id)->status);
        $this->assertDatabaseCount('learning_project_events', 1);
        $this->post('/ar/control/learning/'.$id.'/project/review', $this->review('approve', 1))->assertRedirect();
        $this->assertSame('approved', $this->project($id)->status);
    }

    public function test_only_super_admin_can_review_and_invalid_reviews_are_rejected(): void
    {
        $user = $this->student();
        $id = $this->enroll($user);
        $this->actingAs($user)->post('/ar/account/learning/project', $this->submission())->assertRedirect();
        $this->get('/ar/control/learning/'.$id.'/project')->assertForbidden();
        $this->post('/ar/control/learning/'.$id.'/project/review', $this->review('approve', 1))->assertForbidden();
        $this->actingAs($this->admin());
        $payload = $this->review('approve', 1);
        $payload['rubric_scores'] = [5, -1, 2, 2];
        $payload['feedback'] = 'Short';
        $this->post('/ar/control/learning/'.$id.'/project/review', $payload)->assertSessionHasErrors(['rubric_scores', 'rubric_scores.0', 'rubric_scores.1', 'feedback']);
        $this->post('/ar/control/learning/999/project/review', $this->review('approve', 1))->assertNotFound();
        $this->assertSame('submitted', $this->project($id)->status);
    }

    public function test_project_text_and_history_are_escaped_and_supervisor_queue_is_filterable(): void
    {
        $user = $this->student();
        $other = $this->student();
        $id = $this->enroll($user);
        $this->enroll($other);
        $payload = $this->submission();
        $payload['fields']['question'] = '<script>privateProjectPayload()</script>';
        $this->actingAs($user)->post('/ar/account/learning/project', $payload)->assertRedirect();
        $this->get('/ar/account/learning/project')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>privateProjectPayload()</script>', false);
        $this->actingAs($other)->get('/ar/account/learning/project')->assertDontSee('privateProjectPayload');
        $this->actingAs($this->admin())->get('/ar/control/learning?state=project')->assertSee($user->email)->assertDontSee($other->email);
        $this->get('/ar/control/learning/'.$id.'/project')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>privateProjectPayload()</script>', false);
        $review = $this->review('revise', 1);
        $review['feedback'] = '<script>reviewPayload()</script> Clarify the study design.';
        $this->post('/ar/control/learning/'.$id.'/project/review', $review)->assertRedirect();
        $this->actingAs($user)->get('/ar/account/learning/project')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>reviewPayload()</script>', false);
    }

    public function test_approved_project_appears_separately_on_report_without_changing_unit_score(): void
    {
        $user = $this->student();
        $id = $this->enroll($user, ['exam_submitted_at' => now(), 'summary_submitted_at' => now(), 'reviewed_at' => now(), 'final_score' => 88, 'objective_score' => 40, 'summary_scores' => '[6,4,4,4,2]']);
        $this->actingAs($user)->post('/ar/account/learning/project', $this->submission())->assertRedirect();
        $this->get('/ar/account/learning/report')->assertDontSee('درجة المشروع التطبيقي');
        $this->actingAs($this->admin())->post('/ar/control/learning/'.$id.'/project/review', $this->review('approve', 1))->assertRedirect();
        $this->actingAs($user)->get('/ar/account/learning/report')->assertSee('درجة المشروع التطبيقي')->assertSee('88.00 / 100')->assertSee('18.00 / 20');
        $this->assertSame(88.0, (float) DB::table('learning_enrollments')->find($id)->final_score);
    }

    public function test_project_rejects_other_modules_and_mismatched_material_version(): void
    {
        $user = $this->student();
        $id = $this->enroll($user, ['content_version' => str_repeat('0', 64)]);
        $this->actingAs($user)->get('/ar/account/learning/project')->assertStatus(409);
        DB::table('learning_enrollments')->where('id', $id)->update(['module_id' => 'other-module']);
        $this->get('/ar/account/learning/project')->assertNotFound();
        $this->actingAs($this->admin())->get('/ar/control/learning/'.$id.'/project')->assertNotFound();
    }

    private function student(): User
    {
        return User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    }

    private function admin(): User
    {
        $user = $this->student();
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super admin']);
        $user->roles()->attach($role);

        return $user;
    }

    private function enroll(User $user, array $values = []): int
    {
        $directory = storage_path('app/secure/integrity');
        File::ensureDirectoryExists($directory);
        $pair = sodium_crypto_sign_seed_keypair(str_repeat('t', 32));
        file_put_contents($directory.'/ed25519.secret', base64_encode(sodium_crypto_sign_secretkey($pair)));
        file_put_contents($directory.'/ed25519.public', base64_encode(sodium_crypto_sign_publickey($pair)));

        return DB::table('learning_enrollments')->insertGetId($values + [
            'user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID, 'content_version' => (new SensoryLearningContent)->version(),
            'enrolled_by' => $user->id, 'completed_sections' => json_encode(range(1, 30)), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fields(): array
    {
        return array_fill_keys(SensoryLearningProject::FIELDS, 'A clearly justified protocol section describing a controlled sensory study with reproducible measurement.');
    }

    private function submission(int $revision = 0): array
    {
        return ['action' => 'submit', 'revision' => $revision, 'fields' => $this->fields(), 'originality' => 1, 'data_truth' => 1];
    }

    private function review(string $action, int $revision): array
    {
        return ['action' => $action, 'revision' => $revision, 'rubric_scores' => [4, 4, 4, 3, 3], 'feedback' => 'Clarify recruitment and balanced presentation order, and document the reference condition and all measures.'];
    }

    private function project(int $enrollment): object
    {
        return DB::table('learning_projects')->where('enrollment_id', $enrollment)->first();
    }
}
