<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pro_certificate_students')) {
            return;
        }

        Schema::create('pro_certificate_students', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->longText('private_name');
            $table->char('name_fingerprint', 64)->collation('utf8mb4_bin');
            $table->unsignedInteger('source_record_count');
            $table->unsignedBigInteger('first_source_record_id');
            $table->unsignedBigInteger('last_source_record_id');
            $table->timestamp('archived_at');
            $table->timestamp('created_at');
            $table->unique(
                ['organization_id', 'name_fingerprint'],
                'pro_certificate_students_org_name_unique'
            );
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Preserved student identities require a controlled restore; destructive rollback is disabled.'
        );
    }
};
