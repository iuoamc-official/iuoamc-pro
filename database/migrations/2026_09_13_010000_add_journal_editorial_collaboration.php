<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_submission_contributors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_submission_id')->constrained('journal_submissions')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_corresponding')->default(false);
            $table->string('name');
            $table->string('latin_name')->nullable();
            $table->text('email');
            $table->char('email_hash', 64)->index();
            $table->string('orcid', 25)->nullable()->index();
            $table->string('affiliation_name')->nullable();
            $table->string('affiliation_ror')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->json('contribution_roles');
            $table->timestamp('consent_confirmed_at');
            $table->timestamps();

            $table->unique(['journal_submission_id', 'position']);
        });

        Schema::table('journal_article_author', function (Blueprint $table): void {
            $table->json('contribution_roles')->nullable()->after('contribution');
        });

        Schema::table('journal_reviews', function (Blueprint $table): void {
            $table->string('conflict_status', 20)->nullable()->after('status')->index();
            $table->text('conflict_statement')->nullable()->after('decline_reason');
            $table->timestamp('conflict_declared_at')->nullable()->after('responded_at');
            $table->timestamp('independence_confirmed_at')->nullable()->after('conflict_declared_at');
        });

        Schema::create('journal_editorial_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('journal_submission_id')->constrained('journal_submissions')->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->string('sender_role', 20);
            $table->text('body');
            $table->timestamp('sent_at')->index();
            $table->timestamps();

            $table->index(['journal_submission_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_editorial_messages');
        Schema::table('journal_reviews', function (Blueprint $table): void {
            $table->dropColumn(['conflict_status', 'conflict_statement', 'conflict_declared_at', 'independence_confirmed_at']);
        });
        Schema::table('journal_article_author', function (Blueprint $table): void {
            $table->dropColumn('contribution_roles');
        });
        Schema::dropIfExists('journal_submission_contributors');
    }
};
