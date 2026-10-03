<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_enrollments', function (Blueprint $table): void {
            $table->string('exam_format', 30)->nullable();
            $table->timestamp('exam_deadline_at')->nullable();
            $table->char('exam_assessment_hash', 64)->nullable();
            $table->json('exam_question_order')->nullable();
            $table->unsignedInteger('exam_draft_revision')->default(0);
            $table->unsignedTinyInteger('exam_correct_count')->nullable();
            $table->index(['exam_format', 'exam_submitted_at', 'exam_deadline_at'], 'learning_exam_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('learning_enrollments', function (Blueprint $table): void {
            $table->dropIndex('learning_exam_expiry_idx');
            $table->dropColumn(['exam_format', 'exam_deadline_at', 'exam_assessment_hash', 'exam_question_order', 'exam_draft_revision', 'exam_correct_count']);
        });
    }
};
