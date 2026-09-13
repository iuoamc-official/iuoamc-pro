<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_submission_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_submission_id')->unique()->constrained('journal_submissions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('link_method', 40);
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->index(['user_id', 'linked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_submission_accounts');
    }
};
