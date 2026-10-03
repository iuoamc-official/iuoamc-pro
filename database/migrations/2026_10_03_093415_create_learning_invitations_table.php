<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 254);
            $table->string('module_id', 80);
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_name')->nullable();
            $table->string('student_phone', 40)->nullable();
            $table->string('student_country', 100)->nullable();
            $table->timestamps();
            $table->index(['email', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_invitations');
    }
};
