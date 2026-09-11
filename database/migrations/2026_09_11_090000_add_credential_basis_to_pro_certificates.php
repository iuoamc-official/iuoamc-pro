<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pro_certificates', 'credential_basis')) {
            Schema::table('pro_certificates', function (Blueprint $table): void {
                $table->string('credential_basis', 32)->nullable()->after('certificate_type');
                $table->string('accreditation_reference', 120)->nullable()->after('credential_basis');
                $table->date('accreditation_date')->nullable()->after('accreditation_reference');
                $table->index(['credential_basis', 'status'], 'pro_certificates_basis_status_index');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Credential evidence is issuance history; destructive rollback is disabled.');
    }
};
