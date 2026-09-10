<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pro_certificate_resets')) {
            return;
        }

        Schema::create('pro_certificate_resets', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->unsignedInteger('preserved_student_count');
            $table->unsignedInteger('removed_certificate_count');
            $table->unsignedInteger('quarantined_pdf_count');
            $table->string('backup_reference', 255);
            $table->string('quarantine_path', 255);
            $table->char('manifest_sha256', 64);
            $table->timestamp('executed_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Certificate reset receipts are permanent recovery evidence and cannot be rolled back automatically.'
        );
    }
};
