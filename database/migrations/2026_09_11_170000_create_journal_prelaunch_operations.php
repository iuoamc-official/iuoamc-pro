<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_editorial_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('role', 40)->index();
            $table->json('title')->nullable();
            $table->json('affiliation')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('orcid', 25)->nullable();
            $table->json('biography')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamp('consented_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['journal_id', 'status', 'sort_order'], 'journal_board_public_index');
        });

        Schema::create('journal_notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->string('event', 80);
            $table->longText('recipient');
            $table->char('recipient_hash', 64)->index();
            $table->string('locale', 5);
            $table->string('subject', 500);
            $table->string('template', 100)->default('journal-workflow');
            $table->longText('payload');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at', 'id'], 'journal_outbox_dispatch_index');
            $table->index(['subject_type', 'subject_id'], 'journal_outbox_subject_index');
        });

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'Journal Governance',
            'code' => 'journal.governance',
            'module' => 'journal',
            'description' => 'Manage verified editorial appointments, launch settings and operational attestations.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('code', 'journal.governance')->value('id');
        $roleIds = DB::table('roles')->whereIn('slug', [
            'journal-editor-in-chief',
            'journal-managing-editor',
        ])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $journal = DB::table('journals')->where('code', 'MCIJ')->first();
        if ($journal !== null) {
            $settings = json_decode((string) $journal->settings, true) ?: [];
            DB::table('journals')->where('id', $journal->id)->update([
                'settings' => json_encode($settings + [
                    'public_launch_enabled' => false,
                    'contact_email' => null,
                    'publication_frequency' => null,
                    'fee_policy' => null,
                    'backup_verified_at' => null,
                    'backup_reference' => null,
                    'launched_at' => null,
                    'launched_by' => null,
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_notification_outbox');
        Schema::dropIfExists('journal_editorial_members');

        $permissionId = DB::table('permissions')->where('code', 'journal.governance')->value('id');
        if ($permissionId !== null) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
