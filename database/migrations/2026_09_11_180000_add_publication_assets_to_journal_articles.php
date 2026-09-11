<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_articles', function (Blueprint $table): void {
            $table->char('pdf_sha256', 64)->nullable()->after('pdf_path');
            $table->unsignedBigInteger('pdf_size')->nullable()->after('pdf_sha256');
            $table->unsignedBigInteger('pdf_downloads_count')->default(0)->after('pdf_size');
            $table->string('wicp_registration_number', 120)->nullable()->after('pdf_downloads_count');
            $table->date('wicp_registered_at')->nullable()->after('wicp_registration_number');
            $table->string('wicp_verification_url', 1000)->nullable()->after('wicp_registered_at');
            $table->timestamp('wicp_verified_at')->nullable()->after('wicp_verification_url');

            $table->unique('wicp_registration_number', 'journal_articles_wicp_number_unique');
            $table->index(['status', 'wicp_verified_at'], 'journal_articles_wicp_public_index');
        });
    }

    public function down(): void
    {
        Schema::table('journal_articles', function (Blueprint $table): void {
            $table->dropUnique('journal_articles_wicp_number_unique');
            $table->dropIndex('journal_articles_wicp_public_index');
            $table->dropColumn([
                'pdf_sha256',
                'pdf_size',
                'pdf_downloads_count',
                'wicp_registration_number',
                'wicp_registered_at',
                'wicp_verification_url',
                'wicp_verified_at',
            ]);
        });
    }
};
