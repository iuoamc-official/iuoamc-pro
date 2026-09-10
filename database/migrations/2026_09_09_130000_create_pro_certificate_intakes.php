<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Never adopt an unrecognised pre-existing table after an interrupted deployment.
        foreach (['pro_certificate_intakes', 'pro_certificate_intake_programs', 'pro_certificate_intake_responses'] as $name) {
            if (Schema::hasTable($name)) { throw new RuntimeException('Intake table exists without a completed migration; controlled recovery required.'); }
        }
        Schema::create('pro_certificate_intakes', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('source_system', 80)->collation('utf8mb4_bin');
            $table->string('source_id', 80)->collation('utf8mb4_bin');
            $table->string('source_certificate_number', 120)->nullable()->collation('utf8mb4_bin');
            $table->longText('source_snapshot');
            $table->string('status', 16)->default('draft');
            $table->char('token_hash', 64)->nullable()->collation('utf8mb4_bin')->unique('pc_intake_token_unique');
            $table->text('token_encrypted')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->longText('response_payload')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['source_system', 'source_id', 'organization_id'], 'pc_intake_source_unique');
            $table->index(['organization_id', 'status'], 'pc_intake_organization_status');
        });
        Schema::create('pro_certificate_intake_programs', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            // The public URL identifies a program by code alone: its ownership must be unambiguous.
            $table->string('code', 80)->collation('utf8mb4_bin')->unique('pc_intake_program_code_unique');
            $table->string('program_name_ar', 255);
            $table->string('program_name_en', 255);
            $table->string('program_name_fr', 255)->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status'], 'pc_intake_program_org_status');
        });
        Schema::create('pro_certificate_intake_responses', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('program_id')->constrained('pro_certificate_intake_programs')->restrictOnDelete();
            $table->longText('response_payload');
            $table->char('nonce_hash', 64)->collation('utf8mb4_bin');
            $table->string('status', 16)->default('submitted');
            $table->foreignId('matched_intake_id')->nullable()->constrained('pro_certificate_intakes')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['program_id', 'nonce_hash'], 'pc_intake_response_nonce_unique');
            $table->unique(['program_id', 'matched_intake_id'], 'pc_intake_response_match_unique');
            $table->index(['program_id', 'status'], 'pc_intake_response_program_status');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Recipient responses require a controlled restore; destructive migration rollback is disabled.');
    }
};
