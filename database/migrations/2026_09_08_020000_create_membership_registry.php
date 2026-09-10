<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('membership_number', 80)->nullable()->unique();
            $table->string('full_name');
            $table->string('latin_name')->nullable();
            $table->string('membership_type', 120);
            $table->string('professional_title', 160)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('preferred_locale', 5)->default('ar');
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('private_notes')->nullable();
            $table->text('last_reason')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('lock_version')->default(1);
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'full_name']);
        });
        Schema::create('membership_periods', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('period_uuid')->unique();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->longText('payload');
            $table->char('payload_sha256', 64);
            $table->foreignId('audit_log_id')->constrained('audit_logs')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['membership_id', 'version']);
            $table->index(['valid_from', 'valid_until']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Membership history requires a controlled restore; automatic destructive rollback is disabled.');
    }
};
