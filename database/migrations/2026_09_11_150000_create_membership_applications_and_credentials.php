<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('membership_id')->unique()->constrained()->restrictOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality_code', 2)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('residence_country_code', 2)->nullable();
            $table->string('identification_type', 60)->nullable();
            $table->text('identification_number')->nullable();
            $table->text('qualifications')->nullable();
            $table->string('photo_path', 500);
            $table->char('photo_sha256', 64);
            $table->string('photo_mime', 40);
            $table->timestamp('consent_at');
            $table->unsignedInteger('lock_version')->default(1);
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
               });

        Schema::create('membership_credentials', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_period_id')->unique()->constrained('membership_periods')->restrictOnDelete();
            $table->char('public_token', 64)->unique();
            $table->string('membership_number', 80);
            $table->unsignedInteger('version');
            $table->longText('payload');
            $table->char('payload_sha256', 64);
            $table->string('card_pdf_path', 500);
            $table->char('card_pdf_sha256', 64);
            $table->string('certificate_pdf_path', 500);
            $table->char('certificate_pdf_sha256', 64);
            $table->string('pades_profile', 30);
            $table->string('pades_status', 30);
            $table->char('signing_certificate_sha256', 64);
            $table->timestamp('signed_at');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['membership_id', 'version']);
            $table->index(['membership_number', 'version']);
        });

        foreach ([
            ['memberships.issue', 'Issue membership cards and certificates'],
        ] as [$code, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => 'Issue Membership Credentials',
                    'module' => 'memberships',
                    'description' => $description,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $permission = DB::table('permissions')->where('code', $code)->first();
            $role = DB::table('roles')->where('slug', 'super-admin')->first();
            if ($permission !== null && $role !== null) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permission->id,
                    'role_id' => $role->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Membership applications and credentials require a controlled restore.');
    }
};
