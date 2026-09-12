<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_articles', function (Blueprint $table): void {
            $table->unsignedBigInteger('pdf_downloads_count')->default(0)->after('views_count');
        });
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table): void {
            $table->dropColumn('pdf_downloads_count');
        });
    }
};
