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
        Schema::create('journal_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('converted_article_id')->nullable()->constrained('journal_articles')->restrictOnDelete();
            $table->string('submission_code', 80)->unique();
            $table->string('status', 30)->default('submitted')->index();
            $table->string('type', 40)->index();
            $table->string('primary_locale', 5);
            $table->string('title', 500);
            $table->longText('abstract');
            $table->json('keywords');
            $table->string('manuscript_path');
            $table->string('original_filename', 255);
            $table->char('file_sha256', 64);
            $table->string('author_name');
            $table->text('author_email');
            $table->char('author_email_hash', 64)->index();
            $table->string('affiliation')->nullable();
            $table->string('orcid', 25)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->json('declarations');
            $table->text('editorial_note')->nullable();
            $table->char('tracking_token_hash', 64);
            $table->timestamp('consent_at');
            $table->timestamp('received_at')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['journal_id', 'status', 'received_at']);
        });

        Schema::create('journal_editorial_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_article_id')->constrained('journal_articles')->restrictOnDelete();
            $table->string('decision', 30)->index();
            $table->longText('letter')->nullable();
            $table->text('confidential_note')->nullable();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'Journal Submissions',
            'code' => 'journal.submissions',
            'module' => 'journal',
            'description' => 'Screen submitted manuscripts and convert eligible submissions into editorial records.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('code', 'journal.submissions')->value('id');
        $roleIds = DB::table('roles')->whereIn('slug', [
            'journal-editor-in-chief',
            'journal-managing-editor',
            'journal-section-editor',
        ])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'journal.submissions')->value('id');
        if ($permissionId !== null) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('journal_editorial_decisions');
        Schema::dropIfExists('journal_submissions');
    }
};
