<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('module_id', 80);
            $table->char('content_version', 64);
            $table->foreignId('enrolled_by')->constrained('users')->restrictOnDelete();
            $table->json('completed_sections')->nullable();
            $table->json('exam_answers')->nullable();
            $table->timestamp('exam_started_at')->nullable();
            $table->timestamp('exam_submitted_at')->nullable();
            $table->decimal('objective_score', 5, 2)->nullable();
            $table->json('summary_lines')->nullable();
            $table->timestamp('summary_submitted_at')->nullable();
            $table->json('question_scores')->nullable();
            $table->json('summary_scores')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->text('review_feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_enrollments');
    }
};
