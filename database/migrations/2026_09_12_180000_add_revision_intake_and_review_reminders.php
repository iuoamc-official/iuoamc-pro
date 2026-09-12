<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_submission_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('journal_submission_id')->constrained('journal_submissions')->restrictOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->string('manuscript_path');
            $table->string('original_filename', 255);
            $table->char('file_sha256', 64);
            $table->string('response_letter_path')->nullable();
            $table->string('response_letter_filename', 255)->nullable();
            $table->char('response_letter_sha256', 64)->nullable();
            $table->text('author_note')->nullable();
            $table->string('status', 20)->default('received')->index();
            $table->timestamp('received_at')->index();
            $table->timestamps();

            $table->unique(['journal_submission_id', 'revision_number'], 'journal_submission_revision_unique');
        });

        Schema::table('journal_reviews', function (Blueprint $table): void {
            $table->timestamp('last_reminded_at')->nullable()->after('decline_reason');
            $table->unsignedSmallInteger('reminder_count')->default(0)->after('last_reminded_at');
            $table->index(['status', 'due_at', 'last_reminded_at'], 'journal_review_reminder_due');
        });
    }

    public function down(): void
    {
        Schema::table('journal_reviews', function (Blueprint $table): void {
            $table->dropIndex('journal_review_reminder_due');
            $table->dropColumn(['last_reminded_at', 'reminder_count']);
        });

        Schema::dropIfExists('journal_submission_revisions');
    }
};
