<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SensoryLearningContent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LearningAccessTest extends TestCase
{
    private const CODE = 'IUOAMC-0123456789ABCDEF0123';

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
        $directory = storage_path('app/secure/integrity');
        File::ensureDirectoryExists($directory);
        $pair = sodium_crypto_sign_seed_keypair(str_repeat('t', 32));
        file_put_contents($directory.'/ed25519.secret', base64_encode(sodium_crypto_sign_secretkey($pair)));
        file_put_contents($directory.'/ed25519.public', base64_encode(sodium_crypto_sign_publickey($pair)));
    }

    public function test_student_form_is_public_localized_and_does_not_cache_private_data(): void
    {
        foreach (['ar', 'en', 'fr'] as $locale) {
            $this->get('/'.$locale.'/learning')->assertOk()->assertSee('name="access_code"', false)
                ->assertSee('name="phone"', false)->assertSee('assets/images/learning/sensory-pathways.svg', false)
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_only_super_admin_can_issue_student_codes(): void
    {
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $this->actingAs($user)->post('/ar/control/learning/invitations', ['email' => 'student@example.test'])->assertForbidden();
        $this->assertDatabaseCount('learning_invitations', 0);
    }

    public function test_admin_issues_email_bound_code_and_keeps_plaintext_out_of_database_and_audit(): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        $response = $this->actingAs($admin)->post('/ar/control/learning/invitations', ['email' => ' Student@Example.Test ']);
        $response->assertRedirect('/ar/control/learning')->assertSessionHas('learning_invitation.email', 'student@example.test');
        $code = session('learning_invitation.code');
        $this->assertMatchesRegularExpression('/^IUOAMC-[A-F0-9]{20}$/', $code);
        $this->assertDatabaseHas('learning_invitations', ['email' => 'student@example.test', 'code_hash' => hash('sha256', $code), 'created_by' => $admin->id]);
        $this->assertStringNotContainsString($code, json_encode(DB::table('learning_invitations')->get()));
        $this->assertStringNotContainsString($code, json_encode(DB::table('audit_logs')->get()));
        $this->get('/ar/control/learning')->assertSee($code)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_replacement_code_revokes_previous_unused_invitation(): void
    {
        $this->freezeTime();
        $id = $this->invitation();
        $this->actingAs($this->admin())->post('/ar/control/learning/invitations', ['email' => 'student@example.test'])->assertRedirect();
        $this->assertNotNull(DB::table('learning_invitations')->find($id)->revoked_at);
        $this->assertDatabaseCount('learning_invitations', 2);
    }

    public function test_admin_can_revoke_unused_code(): void
    {
        $id = $this->invitation();
        $this->actingAs($this->admin())->delete('/ar/control/learning/invitations/'.$id)->assertRedirect('/ar/control/learning');
        $this->assertNotNull(DB::table('learning_invitations')->find($id)->revoked_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'learning.code_revoked']);
    }

    public function test_student_cannot_revoke_codes(): void
    {
        $id = $this->invitation();
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $this->actingAs($user)->delete('/ar/control/learning/invitations/'.$id)->assertForbidden();
        $this->assertNull(DB::table('learning_invitations')->find($id)->revoked_at);
    }

    public function test_admin_cannot_revoke_a_code_already_redeemed_by_a_student(): void
    {
        $id = $this->invitation(['used_at' => now()]);
        $this->actingAs($this->admin())->delete('/ar/control/learning/invitations/'.$id)->assertStatus(409);
        $this->assertNull(DB::table('learning_invitations')->find($id)->revoked_at);
    }

    public function test_new_student_redeems_once_without_receiving_admin_roles_or_automatic_email_verification(): void
    {
        $this->freezeTime();
        $id = $this->invitation();
        Event::fake([Registered::class]);
        $this->post('/ar/learning/activate', $this->payload() + ['status' => 'inactive', 'email_verified_at' => now(), 'roles' => ['super-admin']])
            ->assertRedirect('/ar/verify-email');
        $user = User::query()->where('email', 'student@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('active', $user->status);
        $this->assertSame(0, $user->roles()->count());
        $this->assertTrue(Hash::check('StudentPass12345', $user->password));
        $this->assertDatabaseHas('learning_enrollments', ['user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID]);
        $this->assertDatabaseHas('learning_invitations', ['id' => $id, 'used_by' => $user->id, 'student_phone' => '+213 555 123456', 'student_country' => 'الجزائر']);
        Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->id === $user->id);
        $this->get('/ar/account/learning/lessons/1')->assertRedirect('/ar/verify-email');
        $this->post('/ar/learning/activate', $this->payload())->assertSessionHasErrors('access_code');
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('learning_enrollments', 1);
    }

    public function test_existing_student_uses_current_password_and_keeps_identity_and_progress(): void
    {
        $this->invitation();
        $user = User::factory()->create(['email' => 'student@example.test', 'name' => 'Original name', 'password' => 'StudentPass12345', 'status' => 'active']);
        $hash = $user->password;
        DB::table('learning_enrollments')->insert(['user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID, 'content_version' => (new SensoryLearningContent)->version(), 'enrolled_by' => $user->id, 'completed_sections' => '[1,2]', 'created_at' => now(), 'updated_at' => now()]);
        Event::fake([Registered::class]);
        $this->post('/ar/learning/activate', $this->payload())->assertRedirect('/ar/account/learning');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Original name', $user->fresh()->name);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('learning_enrollments', 1);
        $this->assertDatabaseHas('learning_enrollments', ['user_id' => $user->id, 'completed_sections' => '[1,2]']);
        Event::assertNotDispatched(Registered::class);
    }

    public function test_invitation_cannot_take_over_existing_account_with_another_password(): void
    {
        $id = $this->invitation();
        $user = User::factory()->create(['email' => 'student@example.test', 'password' => 'OriginalPass12345', 'status' => 'active']);
        $hash = $user->password;
        $this->post('/ar/learning/activate', $this->payload())->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertNull(DB::table('learning_invitations')->find($id)->used_at);
        $this->assertDatabaseCount('learning_enrollments', 0);
    }

    public static function unavailableCodes(): array
    {
        return [
            'unknown' => ['access_code', 'IUOAMC-FFFFFFFFFFFFFFFFFFFF', []],
            'other email' => ['email', 'someone@example.test', []],
            'expired' => ['access_code', self::CODE, ['expires_at' => '2026-01-01 00:00:00']],
            'revoked' => ['access_code', self::CODE, ['revoked_at' => '2026-01-01 00:00:00']],
            'used' => ['access_code', self::CODE, ['used_at' => '2026-01-01 00:00:00']],
        ];
    }

    #[DataProvider('unavailableCodes')]
    public function test_invalid_code_never_creates_an_account_or_enrollment(string $field, string $value, array $state): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3));
        $id = $this->invitation($state);
        $this->post('/ar/learning/activate', array_replace($this->payload(), [$field => $value]))
            ->assertSessionHasErrors(['access_code' => 'الكود غير صالح لهذا البريد، أو انتهت صلاحيته، أو استُخدم أو أُلغي. راجع إدارة البرنامج.']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('learning_enrollments', 0);
        $this->assertNull(DB::table('learning_invitations')->find($id)->used_by);
        $this->assertNull(session('_old_input.access_code'));
    }

    public function test_activation_validates_profile_confirmation_and_consent_without_consuming_code(): void
    {
        $id = $this->invitation();
        $this->post('/ar/learning/activate', [])->assertSessionHasErrors(['name', 'email', 'phone', 'country', 'access_code', 'password', 'terms']);
        $this->post('/ar/learning/activate', array_replace($this->payload(), ['phone' => '<script>alert(1)</script>']))->assertSessionHasErrors('phone');
        $this->post('/ar/learning/activate', array_replace($this->payload(), ['password_confirmation' => 'OtherPass12345']))->assertSessionHasErrors('password');
        $this->post('/ar/learning/activate', array_replace($this->payload(), ['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/ar/learning/activate', array_replace($this->payload(), ['terms' => '0']))->assertSessionHasErrors('terms');
        $this->assertNull(DB::table('learning_invitations')->find($id)->used_at);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_returning_student_login_redirects_to_learning(): void
    {
        $user = User::factory()->create(['email' => 'student@example.test', 'password' => 'StudentPass12345', 'status' => 'active']);
        $this->post('/ar/learning/login', ['email' => ' Student@Example.Test ', 'password' => 'StudentPass12345'])->assertRedirect('/ar/account/learning');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['event' => 'learning.login', 'actor_id' => $user->id]);
    }

    public function test_inactive_account_cannot_log_in_or_redeem_invitation(): void
    {
        $id = $this->invitation();
        User::factory()->create(['email' => 'student@example.test', 'password' => 'StudentPass12345', 'status' => 'inactive']);
        $this->post('/ar/learning/login', ['email' => 'student@example.test', 'password' => 'StudentPass12345'])->assertSessionHasErrors('email');
        $this->post('/ar/learning/activate', $this->payload())->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertNull(DB::table('learning_invitations')->find($id)->used_at);
    }

    public function test_activation_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/ar/learning/activate', $this->payload())->assertSessionHasErrors('access_code');
        }
        $this->post('/ar/learning/activate', $this->payload())->assertTooManyRequests();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_richer_lessons_keep_the_original_assessment_version_and_show_all_diagrams(): void
    {
        $core = json_decode(file_get_contents(resource_path('learning/sensory-foundation/student_content.json')), true);
        $assessment = json_decode(file_get_contents(resource_path('learning/sensory-foundation/server_assessment.json')), true);
        $this->assertSame(hash('sha256', json_encode([$core, $assessment], JSON_THROW_ON_ERROR)), (new SensoryLearningContent)->version());
        $user = User::factory()->create(['status' => 'active']);
        DB::table('learning_enrollments')->insert(['user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID, 'content_version' => (new SensoryLearningContent)->version(), 'enrolled_by' => $user->id, 'completed_sections' => json_encode(range(1, 30)), 'created_at' => now(), 'updated_at' => now()]);
        foreach ((new SensoryLearningContent)->student()['sections'] as $lesson) {
            $this->actingAs($user)->get('/ar/account/learning/lessons/'.$lesson['id'])->assertOk()
                ->assertSee($lesson['support']['goal'])->assertSee($lesson['support']['example'])
                ->assertSee('assets/images/learning/'.$lesson['support']['figure'].'.svg', false);
        }
        $this->assertDatabaseHas('learning_enrollments', ['user_id' => $user->id, 'completed_sections' => json_encode(range(1, 30))]);
    }

    public function test_admin_escapes_profile_details_in_invitation_list(): void
    {
        $this->invitation(['used_at' => now(), 'student_name' => '<script>alert(1)</script>', 'student_country' => '<img src=x onerror=alert(1)>']);
        $this->actingAs($this->admin())->get('/ar/control/learning')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    private function invitation(array $state = []): int
    {
        return DB::table('learning_invitations')->insertGetId($state + [
            'email' => 'student@example.test', 'module_id' => SensoryLearningContent::MODULE_ID,
            'created_by' => $this->admin()->id, 'code_hash' => hash('sha256', self::CODE), 'expires_at' => now()->addDays(30),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function payload(): array
    {
        return ['name' => 'طالب التذوق', 'email' => 'student@example.test', 'phone' => '+213 555 123456', 'country' => 'الجزائر', 'access_code' => self::CODE, 'password' => 'StudentPass12345', 'password_confirmation' => 'StudentPass12345', 'terms' => '1'];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super admin']);
        $user->roles()->attach($role);

        return $user;
    }
}
