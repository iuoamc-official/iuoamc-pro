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
        Schema::create('journals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->string('code', 40)->unique();
            $table->json('name');
            $table->json('description');
            $table->string('publisher_name');
            $table->string('issn', 20)->nullable()->unique();
            $table->string('eissn', 20)->nullable()->unique();
            $table->string('status', 20)->default('active')->index();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('journal_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('volume');
            $table->unsignedSmallInteger('number');
            $table->string('slug', 100)->unique();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['journal_id', 'volume', 'number']);
            $table->index(['journal_id', 'status', 'published_at']);
        });

        Schema::create('journal_articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_issue_id')->nullable()->constrained('journal_issues')->restrictOnDelete();
            $table->foreignId('correction_of_id')->nullable()->constrained('journal_articles')->restrictOnDelete();
            $table->string('article_code', 80)->unique();
            $table->string('slug', 180)->unique();
            $table->string('type', 40)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('primary_locale', 5)->default('ar');
            $table->string('doi', 255)->nullable()->unique();
            $table->string('license', 80)->default('all-rights-reserved');
            $table->unsignedSmallInteger('page_start')->nullable();
            $table->unsignedSmallInteger('page_end')->nullable();
            $table->date('received_at')->nullable();
            $table->date('accepted_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('retracted_at')->nullable();
            $table->json('declarations')->nullable();
            $table->string('pdf_path')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unsignedInteger('version_of_record')->default(0);
            $table->char('version_of_record_hash', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['journal_id', 'type', 'status', 'published_at'], 'journal_articles_public_index');
            $table->index(['journal_issue_id', 'status']);
        });

        Schema::create('journal_article_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_article_id')->constrained('journal_articles')->restrictOnDelete();
            $table->string('locale', 5);
            $table->string('title', 500);
            $table->string('subtitle', 500)->nullable();
            $table->longText('abstract');
            $table->longText('body');
            $table->json('keywords');
            $table->json('references')->nullable();
            $table->string('seo_title', 180)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();

            $table->unique(['journal_article_id', 'locale']);
        });

        Schema::create('journal_authors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->string('name');
            $table->string('latin_name')->nullable();
            $table->text('email')->nullable();
            $table->string('orcid', 25)->nullable()->index();
            $table->string('country_code', 2)->nullable();
            $table->json('biography')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('journal_article_author', function (Blueprint $table): void {
            $table->foreignId('journal_article_id')->constrained('journal_articles')->restrictOnDelete();
            $table->foreignId('journal_author_id')->constrained('journal_authors')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_corresponding')->default(false);
            $table->string('affiliation_name')->nullable();
            $table->string('affiliation_ror', 255)->nullable();
            $table->text('contribution')->nullable();

            $table->primary(['journal_article_id', 'journal_author_id']);
            $table->index(['journal_article_id', 'position']);
        });

        Schema::create('journal_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_article_id')->constrained('journal_articles')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->string('status', 20)->default('invited')->index();
            $table->string('recommendation', 30)->nullable();
            $table->text('author_comments')->nullable();
            $table->text('confidential_comments')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['journal_article_id', 'reviewer_id', 'round']);
        });

        Schema::create('journal_article_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_article_id')->constrained('journal_articles')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind', 30);
            $table->longText('payload');
            $table->char('payload_sha256', 64);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->unique(['journal_article_id', 'version']);
        });

        $now = now();
        foreach ([
            ['Journal View', 'journal.view', 'View editorial records and workflow status.'],
            ['Journal Manage', 'journal.manage', 'Create and edit journal records before publication.'],
            ['Journal Review', 'journal.review', 'Manage peer-review assignments and recommendations.'],
            ['Journal Publish', 'journal.publish', 'Approve, publish, correct and retract version-of-record articles.'],
        ] as [$name, $code, $description]) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'code' => $code,
                'module' => 'journal',
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rolePermissions = [
            'journal-editor-in-chief' => ['journal.view', 'journal.manage', 'journal.review', 'journal.publish'],
            'journal-managing-editor' => ['journal.view', 'journal.manage', 'journal.review'],
            'journal-section-editor' => ['journal.view', 'journal.manage', 'journal.review'],
            'journal-reviewer' => ['journal.view', 'journal.review'],
            'journal-copy-editor' => ['journal.view', 'journal.manage'],
            'journal-production-editor' => ['journal.view', 'journal.manage'],
        ];
        foreach ($rolePermissions as $slug => $codes) {
            DB::table('roles')->insertOrIgnore([
                'name' => str($slug)->replace('-', ' ')->title()->toString(),
                'slug' => $slug,
                'description' => 'System role for the Master Chefs International Journal editorial workflow.',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        DB::table('journals')->insert([
            'record_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'code' => 'MCIJ',
            'name' => json_encode([
                'ar' => 'المجلة الدولية لماستر شيف',
                'en' => 'Master Chefs International Journal',
                'fr' => 'Revue internationale des Master Chefs',
            ], JSON_UNESCAPED_UNICODE),
            'description' => json_encode([
                'ar' => 'منصة علمية دولية محكّمة لأبحاث فنون الطهي والذواقة، مع قسم مستقل للمقالات المهنية.',
                'en' => 'An international scholarly publishing platform for peer-reviewed culinary and gastronomy research, with a distinct professional-articles section.',
                'fr' => 'Une plateforme scientifique internationale consacrée à la recherche évaluée par les pairs en arts culinaires et gastronomie, avec une rubrique professionnelle distincte.',
            ], JSON_UNESCAPED_UNICODE),
            'publisher_name' => 'International Union of Arab Master Chefs Ltd',
            'status' => 'active',
            'settings' => json_encode([
                'doi_enabled' => false,
                'oai_pmh_enabled' => false,
                'jats_export_enabled' => false,
                'crossmark_enabled' => false,
                'supported_locales' => ['ar', 'en', 'fr'],
                'minimum_peer_reviews' => 2,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_article_versions');
        Schema::dropIfExists('journal_reviews');
        Schema::dropIfExists('journal_article_author');
        Schema::dropIfExists('journal_authors');
        Schema::dropIfExists('journal_article_translations');
        Schema::dropIfExists('journal_articles');
        Schema::dropIfExists('journal_issues');
        Schema::dropIfExists('journals');

        DB::table('roles')->whereIn('slug', [
            'journal-editor-in-chief', 'journal-managing-editor', 'journal-section-editor',
            'journal-reviewer', 'journal-copy-editor', 'journal-production-editor',
        ])->delete();

        DB::table('permissions')->whereIn('code', [
            'journal.view',
            'journal.manage',
            'journal.review',
            'journal.publish',
        ])->delete();
    }
};
