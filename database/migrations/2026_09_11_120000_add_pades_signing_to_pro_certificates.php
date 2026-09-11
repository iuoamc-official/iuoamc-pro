<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pro_certificates', 'recipient_email')) {
            Schema::table('pro_certificates', function (Blueprint $table): void {
                $table->text('recipient_email')->nullable()->after('public_name');
                $table->string('pdf_signature_profile', 24)->nullable()->after('pdf_sha256');
                $table->string('pdf_signature_status', 16)->nullable()->after('pdf_signature_profile');
                $table->string('pdf_signature_field', 96)->nullable()->after('pdf_signature_status');
                $table->char('pdf_signing_certificate_sha256', 64)->nullable()->after('pdf_signature_field');
                $table->dateTime('pdf_signed_at')->nullable()->after('pdf_signing_certificate_sha256');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('PAdES evidence and delivery metadata require a controlled restore; destructive rollback is disabled.');
    }
};
