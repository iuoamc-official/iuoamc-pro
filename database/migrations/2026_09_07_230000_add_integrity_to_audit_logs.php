<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('sequence_number')->nullable()->unique();
            $table->char('previous_hash', 64)->nullable();
            $table->char('payload_hash', 64)->nullable()->index();
            $table->char('record_hash', 64)->nullable()->unique();
            $table->text('signature')->nullable();
            $table->char('signing_key_id', 64)->nullable()->index();
            $table->string('integrity_version', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique('audit_logs_sequence_number_unique');
            $table->dropIndex('audit_logs_payload_hash_index');
            $table->dropUnique('audit_logs_record_hash_unique');
            $table->dropIndex('audit_logs_signing_key_id_index');
            $table->dropColumn([
                'sequence_number',
                'previous_hash',
                'payload_hash',
                'record_hash',
                'signature',
                'signing_key_id',
                'integrity_version',
            ]);
        });
    }
};
