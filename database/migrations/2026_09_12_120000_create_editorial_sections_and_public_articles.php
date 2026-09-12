<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->string('slug', 100)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('scope', 40)->default('all')->index();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('journal_article_section', function (Blueprint $table): void {
            $table->foreignId('journal_article_id')->constrained('journal_articles')->restrictOnDelete();
            $table->foreignId('journal_section_id')->constrained('journal_sections')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['journal_article_id', 'journal_section_id']);
        });

        Schema::create('content_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('content_articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('content_section_id')->nullable()->constrained('content_sections')->restrictOnDelete();
            $table->string('slug', 180)->unique();
            $table->json('title');
            $table->json('excerpt');
            $table->json('body');
            $table->json('seo_title');
            $table->json('seo_description');
            $table->json('image_alt')->nullable();
            $table->json('author_biography')->nullable();
            $table->json('tags')->nullable();
            $table->string('author_name')->default('Ahmad Maadarani');
            $table->string('publisher_name')->default('Ahmad Maadarani');
            $table->string('cover_image_path')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('reading_minutes')->default(1);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'content_section_id', 'published_at'], 'content_articles_public_index');
        });

        $now = now();
        $actorId = DB::table('users')->orderBy('id')->value('id');
        $journal = DB::table('journals')->where('code', 'MCIJ')->first();
        if ($journal !== null) {
            $journalSections = [
                ['sensory-science', 'all', 10, ['ar' => 'علوم التذوق والتحليل الحسي', 'en' => 'Sensory Science & Tasting', 'fr' => 'Sciences sensorielles et dégustation']],
                ['culinary-innovation', 'all', 20, ['ar' => 'الابتكار وفنون الطهي', 'en' => 'Culinary Innovation', 'fr' => 'Innovation culinaire']],
                ['food-culture', 'all', 30, ['ar' => 'ثقافة الطعام والتراث', 'en' => 'Food Culture & Heritage', 'fr' => 'Culture et patrimoine alimentaires']],
                ['hospitality-management', 'all', 40, ['ar' => 'إدارة المطاعم والضيافة', 'en' => 'Hospitality & Restaurant Management', 'fr' => 'Gestion hôtelière et restauration']],
                ['sustainability', 'all', 50, ['ar' => 'الاستدامة والنظم الغذائية', 'en' => 'Sustainability & Food Systems', 'fr' => 'Durabilité et systèmes alimentaires']],
                ['standards-arbitration', 'all', 60, ['ar' => 'المعايير والتحكيم المهني', 'en' => 'Standards & Professional Arbitration', 'fr' => 'Normes et arbitrage professionnel']],
            ];
            foreach ($journalSections as [$slug, $scope, $order, $name]) {
                DB::table('journal_sections')->insert([
                    'journal_id' => $journal->id, 'slug' => $slug, 'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                    'description' => json_encode($name, JSON_UNESCAPED_UNICODE), 'scope' => $scope, 'status' => 'active',
                    'sort_order' => $order, 'created_by' => $actorId, 'updated_by' => $actorId,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $settings = json_decode((string) $journal->settings, true) ?: [];
            DB::table('journals')->where('id', $journal->id)->update([
                'settings' => json_encode(array_merge($settings, [
                    'publisher_person_name' => 'Ahmad Maadarani',
                    'publisher_title' => [
                        'ar' => 'المؤسس والناشر المسؤول',
                        'en' => 'Founder and Responsible Publisher',
                        'fr' => 'Fondateur et éditeur responsable',
                    ],
                    'publisher_biography' => [
                        'ar' => 'المهندس وماستر شيف أحمد المعدراني، مؤسس ورئيس عام الاتحاد الدولي لماستر شيف العرب وصاحب مبادرات في تقنين فنون الطهي والتذوق المهني.',
                        'en' => 'Engineer and Master Chef Ahmad Maadarani, founder and President General of IUOAMC and initiator of professional culinary and tasting codification projects.',
                        'fr' => 'Ingénieur et Master Chef Ahmad Maadarani, fondateur et président général de l’IUOAMC et initiateur de projets de codification culinaire et sensorielle.',
                    ],
                ]), JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

            if ($actorId !== null && ! DB::table('journal_editorial_members')->where('journal_id', $journal->id)->where('name', 'Ahmad Maadarani')->exists()) {
                DB::table('journal_editorial_members')->insert([
                    'record_uuid' => (string) Str::uuid(), 'journal_id' => $journal->id, 'name' => 'Ahmad Maadarani',
                    'role' => 'editor_in_chief',
                    'title' => json_encode(['ar' => 'المؤسس والناشر المسؤول ورئيس التحرير المؤقت', 'en' => 'Founder, Responsible Publisher and Interim Editor-in-Chief', 'fr' => 'Fondateur, éditeur responsable et rédacteur en chef par intérim'], JSON_UNESCAPED_UNICODE),
                    'affiliation' => json_encode(['ar' => 'الاتحاد الدولي لماستر شيف العرب', 'en' => 'International Union of Arab Master Chefs', 'fr' => 'Union internationale des Master Chefs arabes'], JSON_UNESCAPED_UNICODE),
                    'biography' => json_encode([
                        'ar' => 'المهندس وماستر شيف أحمد المعدراني، مؤسس ورئيس عام IUOAMC وصاحب مبادرات في تقنين فنون الطهي والتذوق المهني.',
                        'en' => 'Engineer and Master Chef Ahmad Maadarani, founder and President General of IUOAMC and initiator of professional culinary and tasting codification projects.',
                        'fr' => 'Ingénieur et Master Chef Ahmad Maadarani, fondateur et président général de l’IUOAMC et initiateur de projets de codification culinaire et sensorielle.',
                    ], JSON_UNESCAPED_UNICODE),
                    'status' => 'active', 'sort_order' => 1, 'consented_at' => $now,
                    'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $articleId = DB::table('journal_articles')->where('article_code', 'MCIJ-2026-DB6E05B6')->value('id');
            $sectionId = DB::table('journal_sections')->where('slug', 'sensory-science')->value('id');
            if ($articleId !== null && $sectionId !== null) {
                DB::table('journal_article_section')->insertOrIgnore([
                    'journal_article_id' => $articleId,
                    'journal_section_id' => $sectionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ([
            ['news', 10, ['ar' => 'الأخبار والإعلانات', 'en' => 'News & Announcements', 'fr' => 'Actualités et annonces']],
            ['culinary-knowledge', 20, ['ar' => 'معرفة الطهي', 'en' => 'Culinary Knowledge', 'fr' => 'Savoirs culinaires']],
            ['restaurant-insights', 30, ['ar' => 'رؤى المطاعم', 'en' => 'Restaurant Insights', 'fr' => 'Regards sur la restauration']],
            ['interviews', 40, ['ar' => 'حوارات وشخصيات', 'en' => 'Interviews & People', 'fr' => 'Entretiens et personnalités']],
            ['events', 50, ['ar' => 'فعاليات ومؤتمرات', 'en' => 'Events & Conferences', 'fr' => 'Événements et conférences']],
            ['opinions', 60, ['ar' => 'آراء وتحليلات', 'en' => 'Opinion & Analysis', 'fr' => 'Opinions et analyses']],
        ] as [$slug, $order, $name]) {
            DB::table('content_sections')->insert([
                'slug' => $slug, 'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                'description' => json_encode($name, JSON_UNESCAPED_UNICODE), 'status' => 'active',
                'sort_order' => $order, 'created_by' => $actorId, 'updated_by' => $actorId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_articles');
        Schema::dropIfExists('content_sections');
        Schema::dropIfExists('journal_article_section');
        Schema::dropIfExists('journal_sections');
    }
};
