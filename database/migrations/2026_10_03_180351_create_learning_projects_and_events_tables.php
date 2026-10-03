<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained('learning_enrollments')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->json('fields')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('submission_number')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->json('rubric_scores')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'submitted_at'], 'learning_project_review_queue_idx');
        });
        Schema::create('learning_project_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('learning_projects')->restrictOnDelete();
            $table->unsignedInteger('submission_number');
            $table->string('event', 30);
            $table->json('payload');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->unique(['project_id', 'submission_number', 'event'], 'learning_project_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_project_events');
        Schema::dropIfExists('learning_projects');
    }
};
