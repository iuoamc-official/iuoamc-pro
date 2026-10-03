<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_lesson_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->unsignedTinyInteger('section');
            $table->text('body');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['enrollment_id', 'section'], 'learning_note_owner_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_lesson_notes');
    }
};
