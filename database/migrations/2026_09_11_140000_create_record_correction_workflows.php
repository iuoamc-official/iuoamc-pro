<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_certificate_delivery_contacts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('certificate_id')->unique()->constrained('pro_certificates')->restrictOnDelete();
            $table->longText('email')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('pro_certificate_replacements', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('source_certificate_id')->unique()->constrained('pro_certificates')->restrictOnDelete();
            $table->foreignId('replacement_certificate_id')->unique()->constrained('pro_certificates')->restrictOnDelete();
            $table->longText('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamp('created_at');
        });

        foreach ([
            ['certificates.correct', 'certificates'],
            ['memberships.correct', 'memberships'],
        ] as [$code, $module]) {
            $permissionId = DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => ucwords(str_replace(['.', '_'], ' ', $code)),
                    'module' => $module,
                    'description' => 'Controlled correction of an approved institutional record.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $permission = DB::table('permissions')->where('code', $code)->first();
            $superAdmin = DB::table('roles')->where('slug', 'super-admin')->first();
            if ($permission !== null && $superAdmin !== null) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permission->id,
                    'role_id' => $superAdmin->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Correction history requires a controlled restore; destructive rollback is disabled.');
    }
};
