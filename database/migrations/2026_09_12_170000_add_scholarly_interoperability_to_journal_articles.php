<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_articles', function (Blueprint $table): void {
            $table->unsignedBigInteger('html_views_count')->default(0)->after('pdf_downloads_count');
            $table->unsignedBigInteger('citation_downloads_count')->default(0)->after('html_views_count');
            $table->unsignedBigInteger('jats_downloads_count')->default(0)->after('citation_downloads_count');
            $table->timestamp('crossref_deposited_at')->nullable()->after('doi');
            $table->string('crossref_deposit_id', 120)->nullable()->after('crossref_deposited_at');
        });

        Schema::table('journal_reviews', function (Blueprint $table): void {
            $table->timestamp('responded_at')->nullable()->after('due_at');
            $table->text('decline_reason')->nullable()->after('responded_at');
        });

        DB::table('journals')->where('status', 'active')->get()->each(function (object $journal): void {
            $settings = json_decode((string) $journal->settings, true) ?: [];
            DB::table('journals')->where('id', $journal->id)->update([
                'settings' => json_encode(array_merge($settings, [
                    'oai_pmh_enabled' => true,
                    'jats_export_enabled' => true,
                    'citation_exports_enabled' => true,
                ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('journal_reviews', function (Blueprint $table): void {
            $table->dropColumn(['responded_at', 'decline_reason']);
        });

        Schema::table('journal_articles', function (Blueprint $table): void {
            $table->dropColumn([
                'html_views_count', 'citation_downloads_count', 'jats_downloads_count',
                'crossref_deposited_at', 'crossref_deposit_id',
            ]);
        });
    }
};
