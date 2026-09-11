<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AccountPortalSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable(); $table->string('password');
            $table->string('status')->default('active'); $table->string('preferred_locale')->default('ar');
            $table->boolean('must_change_password')->default(false); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('slug')->unique();
            $table->boolean('is_system')->default(false); $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('code')->unique(); $table->string('module'); $table->timestamps();
        });
        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id'); $table->unsignedBigInteger('user_id'); $table->timestamps();
            $table->primary(['role_id', 'user_id']);
        });
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id'); $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('account_record_links', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('record_type');
            $table->unsignedBigInteger('record_id'); $table->char('email_hmac', 64); $table->timestamp('matched_at'); $table->timestamps();
        });
    }

    public function test_verified_account_without_an_admin_role_cannot_open_control(): void
    {
        $user = $this->user(true);

        $this->actingAs($user)->get('/ar/control')->assertForbidden();
        self::assertFalse($user->canAccessControl());
    }

    public function test_unverified_account_is_redirected_to_email_verification(): void
    {
        $user = $this->user(false);

        $this->actingAs($user)->get('/ar/account')
            ->assertRedirect(route('verification.notice', ['locale' => 'ar']));
    }

    public function test_account_cannot_download_a_document_without_its_ownership_link(): void
    {
        $owner = $this->user(true, 'owner@example.test');
        $intruder = $this->user(true, 'intruder@example.test');
        DB::table('account_record_links')->insert([
            'user_id' => $owner->id, 'record_type' => 'account_document', 'record_id' => 91,
            'email_hmac' => str_repeat('a', 64), 'matched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($intruder)->get('/ar/account/documents/91/download')->assertNotFound();
    }

    private function user(bool $verified, string $email = 'member@example.test'): User
    {
        $user = User::query()->create([
            'name' => 'Portal Member', 'email' => $email, 'password' => Hash::make(bin2hex(random_bytes(32))),
            'status' => 'active', 'preferred_locale' => 'ar', 'must_change_password' => false,
        ]);
        $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return $user;
    }
}
