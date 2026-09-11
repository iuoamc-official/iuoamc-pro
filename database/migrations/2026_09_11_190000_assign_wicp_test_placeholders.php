<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('journal_articles')
            ->select(['id', 'record_uuid'])
            ->whereNull('wicp_registration_number')
            ->orderBy('id')
            ->chunkById(200, function ($articles): void {
                foreach ($articles as $article) {
                    $suffix = strtoupper(substr(str_replace('-', '', (string) $article->record_uuid), 0, 12));
                    DB::table('journal_articles')->where('id', $article->id)->update([
                        'wicp_registration_number' => 'WICP-TEST-PENDING-'.$suffix,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('journal_articles')
            ->where('wicp_registration_number', 'like', 'WICP-TEST-PENDING-%')
            ->whereNull('wicp_verified_at')
            ->update(['wicp_registration_number' => null]);
    }
};
