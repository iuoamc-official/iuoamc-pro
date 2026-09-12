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
            $table->string('source_url', 1000)->nullable()->after('cover_image_path');
            $table->timestamp('original_published_at')->nullable()->after('source_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table): void {
            $table->dropIndex(['original_published_at']);
            $table->dropColumn(['source_url', 'original_published_at']);
        });
    }
};
