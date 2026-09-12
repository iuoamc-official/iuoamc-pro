<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\JournalAuthor;
use App\Models\JournalIssue;
use App\Models\JournalEditorialMember;
use App\Models\JournalNotificationOutbox;
use App\Models\JournalReview;
use App\Models\JournalSubmission;
use App\Models\ContentArticle;
use App\Models\Role;
use App\Models\User;
use App\Services\JournalWorkflow;
use App\Services\JournalNotificationService;
use App\Services\PublicAiKnowledge;
use App\Services\ArticleBodyFormatter;
use App\Mail\JournalWorkflowMail;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class JournalPublishingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_09_07_160000_create_iuoamc_core_foundation.php',
            '2026_09_07_230000_add_integrity_to_audit_logs.php',
            '2026_09_10_060000_create_public_site_content.php',
            '2026_09_11_150000_create_scientific_journal_core.php',
            '2026_09_11_160000_create_journal_submission_pipeline.php',
            '2026_09_11_170000_create_journal_prelaunch_operations.php',
            '2026_09_11_180000_add_publication_assets_to_journal_articles.php',
            '2026_09_11_190000_assign_wicp_test_placeholders.php',
            '2026_09_12_120000_create_editorial_sections_and_public_articles.php',
            '2026_09_12_130000_expand_public_article_sections.php',
            '2026_09_12_140000_add_legacy_provenance_to_content_articles.php',
            '2026_09_12_160000_add_pdf_downloads_to_content_articles.php',
        ] as $migrationFile) {
            $migration = require database_path('migrations/'.$migrationFile);
            $migration->up();
        }
    }

    public function test_public_catalog_separates_research_from_professional_articles_and_hides_drafts(): void
    {
        $this->enablePublicLaunch();
        $research = $this->createArticle('peer_reviewed_research', 'published', 'Circular Tasting Research');
        $professional = $this->createArticle('professional_article', 'published', 'Restaurant Tasting Practice');
        $draft = $this->createArticle('peer_reviewed_research', 'draft', 'Hidden Research Draft');

        $this->get('/en/journal')
            ->assertOk()
            ->assertSee('Peer-reviewed scientific research')
            ->assertSee('Editorial professional article — not peer reviewed')
            ->assertSee($research->translation('en')->title)
            ->assertSee($professional->translation('en')->title)
            ->assertDontSee($draft->translation('en')->title);
    }

    public function test_public_catalog_filters_articles_by_managed_section(): void
    {
        $this->enablePublicLaunch();
        $sensory = \App\Models\JournalSection::query()->where('slug', 'sensory-science')->firstOrFail();
        $hospitality = \App\Models\JournalSection::query()->where('slug', 'hospitality-management')->firstOrFail();
        $included = $this->createArticle('professional_article', 'published', 'Sensory section article');
        $excluded = $this->createArticle('professional_article', 'published', 'Hospitality section article');
        $included->sections()->attach($sensory);
        $excluded->sections()->attach($hospitality);

        $this->get('/en/journal?section=sensory-science')
            ->assertOk()->assertSee('Sensory section article')->assertDontSee('Hospitality section article');
    }

    public function test_general_articles_publish_directly_and_drafts_never_appear_publicly(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('editorial/articles/unsupported.webp', 'invalid-image-data');
        $section = \App\Models\ContentSection::query()->where('slug', 'culinary-knowledge')->firstOrFail();
        ContentArticle::query()->create([
            'record_uuid' => (string) Str::uuid(), 'content_section_id' => $section->id, 'slug' => 'public-culinary-story',
            'title' => ['ar' => 'قصة طهوية عامة', 'en' => 'Public culinary story', 'fr' => 'Récit culinaire public'],
            'excerpt' => ['ar' => 'ملخص عام', 'en' => 'Public summary', 'fr' => 'Résumé public'],
            'body' => ['ar' => 'نص عام', 'en' => 'Public editorial body', 'fr' => 'Texte éditorial public'],
            'seo_title' => ['ar' => 'قصة', 'en' => 'Story', 'fr' => 'Récit'], 'seo_description' => ['ar' => 'وصف', 'en' => 'Description', 'fr' => 'Description'],
            'author_name' => 'Ahmad Maadarani', 'publisher_name' => 'Ahmad Maadarani', 'cover_image_path' => 'editorial/articles/unsupported.webp', 'status' => 'published', 'published_at' => now(),
        ]);
        ContentArticle::query()->create([
            'record_uuid' => (string) Str::uuid(), 'content_section_id' => $section->id, 'slug' => 'private-editorial-draft',
            'title' => ['ar' => 'مسودة خاصة', 'en' => 'Private editorial draft', 'fr' => 'Brouillon privé'],
            'excerpt' => ['ar' => 'خاص', 'en' => 'Private', 'fr' => 'Privé'], 'body' => ['ar' => 'خاص', 'en' => 'Private', 'fr' => 'Privé'],
            'seo_title' => ['ar' => 'خاص', 'en' => 'Private', 'fr' => 'Privé'], 'seo_description' => ['ar' => 'خاص', 'en' => 'Private', 'fr' => 'Privé'],
            'author_name' => 'Ahmad Maadarani', 'publisher_name' => 'Ahmad Maadarani', 'status' => 'draft',
        ]);

        $this->get('/en/articles')->assertOk()->assertSee('Public culinary story')->assertDontSee('Private editorial draft');
        $this->get('/en/articles/public-culinary-story')
            ->assertOk()
            ->assertSee('<p>Public editorial body</p>', false)
            ->assertSee('Download article PDF')
            ->assertSee('hreflang="x-default"', false)
            ->assertSee('Ahmad Maadarani');
        $this->get('/en/articles/public-culinary-story/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertSame(1, ContentArticle::query()->where('slug', 'public-culinary-story')->value('pdf_downloads_count'));
        $this->get('/en/articles/private-editorial-draft')->assertNotFound();
        $this->get('/en/articles/private-editorial-draft/pdf')->assertNotFound();
    }

    public function test_general_article_pdf_streams_large_html_in_safe_chunks(): void
    {
        $section = \App\Models\ContentSection::query()->where('slug', 'culinary-knowledge')->firstOrFail();
        $paragraph = str_repeat('Evidence based culinary analysis ', 80);
        $body = implode("\n\n", array_fill(0, 500, $paragraph));
        $article = ContentArticle::query()->create([
            'record_uuid' => (string) Str::uuid(),
            'content_section_id' => $section->id,
            'slug' => 'large-public-culinary-article',
            'title' => ['ar' => 'مقال طهوي طويل', 'en' => 'Large culinary article', 'fr' => 'Article culinaire long'],
            'excerpt' => ['ar' => 'ملخص', 'en' => 'Summary', 'fr' => 'Résumé'],
            'body' => ['ar' => $body, 'en' => $body, 'fr' => $body],
            'seo_title' => ['ar' => 'مقال', 'en' => 'Article', 'fr' => 'Article'],
            'seo_description' => ['ar' => 'وصف', 'en' => 'Description', 'fr' => 'Description'],
            'author_name' => 'Ahmad Maadarani',
            'publisher_name' => 'Ahmad Maadarani',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get('/en/articles/'.$article->slug.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_general_article_formatter_creates_safe_semantic_blocks(): void
    {
        $html = app(ArticleBodyFormatter::class)->toHtml("## Culinary heading\n\nEvidence <script>alert(1)</script>\n\n- First\n- Second");

        $this->assertStringContainsString('<h2>Culinary heading</h2>', $html);
        $this->assertStringContainsString('<p>Evidence &lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
        $this->assertStringContainsString('<ul><li>First</li><li>Second</li></ul>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_public_article_section_filters_by_the_explicit_section_relationship(): void
    {
        $includedSection = \App\Models\ContentSection::query()->where('slug', 'recipes-techniques')->firstOrFail();
        $excludedSection = \App\Models\ContentSection::query()->where('slug', 'news')->firstOrFail();
        foreach ([
            [$includedSection, 'section-included-article', 'Included recipe article'],
            [$excludedSection, 'section-excluded-article', 'Excluded news article'],
        ] as [$section, $slug, $title]) {
            ContentArticle::query()->create([
                'record_uuid' => (string) Str::uuid(),
                'content_section_id' => $section->id,
                'slug' => $slug,
                'title' => ['ar' => $title, 'en' => $title, 'fr' => $title],
                'excerpt' => ['ar' => 'ملخص', 'en' => 'Summary', 'fr' => 'Résumé'],
                'body' => ['ar' => 'نص', 'en' => 'Body', 'fr' => 'Texte'],
                'seo_title' => ['ar' => $title, 'en' => $title, 'fr' => $title],
                'seo_description' => ['ar' => 'وصف', 'en' => 'Description', 'fr' => 'Description'],
                'author_name' => 'Ahmad Maadarani',
                'publisher_name' => 'Ahmad Maadarani',
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        $this->get('/en/articles/sections/'.$includedSection->slug)
            ->assertOk()
            ->assertSee('Included recipe article')
            ->assertDontSee('Excluded news article');
    }

    public function test_public_article_taxonomy_matches_the_iuoamc_editorial_identity(): void
    {
        $this->assertDatabaseHas('content_sections', ['slug' => 'news', 'sort_order' => 10, 'status' => 'active']);
        $this->assertDatabaseHas('content_sections', ['slug' => 'recipes-techniques', 'sort_order' => 20, 'status' => 'active']);
        $this->assertDatabaseHas('content_sections', ['slug' => 'chef-success-stories', 'sort_order' => 30, 'status' => 'active']);
        $this->assertDatabaseHas('content_sections', ['slug' => 'competitions-achievements', 'sort_order' => 40, 'status' => 'active']);
        $this->assertDatabaseHas('content_sections', ['slug' => 'culinary-heritage', 'status' => 'active']);
        $this->assertDatabaseHas('content_sections', ['slug' => 'training-career', 'status' => 'active']);
        $this->assertDatabaseHas('content_sections', ['slug' => 'health-nutrition', 'status' => 'active']);
        $this->assertSame(12, \App\Models\ContentSection::query()->active()->count());
    }

    public function test_six_legacy_articles_are_imported_as_multilingual_private_drafts_with_provenance(): void
    {
        Storage::fake('public');
        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), '/uploads/posts/')) {
                return Http::response('webp-image', 200, ['Content-Type' => 'image/webp']);
            }

            preg_match('~/([a-z]{2})/blog/post/(\d+)~', $request->url(), $matches);
            $locale = $matches[1] ?? 'en';
            $id = $matches[2] ?? '0';

            return Http::response(<<<HTML
                <html><head>
                <meta name="description" content="Legacy summary {$id} {$locale}">
                <meta property="og:image" content="https://www.iuoamc.uk/uploads/posts/{$id}.webp">
                <meta property="article:published_time" content="2024-01-17T00:11:21+00:00">
                <link rel="canonical" href="https://www.iuoamc.uk/{$locale}/blog/post/{$id}">
                </head><body><h1>Legacy article {$id} {$locale}</h1>
                <div class="prose max-w-none"><h2>Heading</h2><p>Professional culinary article body.</p><ul><li>Evidence</li></ul></div>
                </body></html>
                HTML);
        });

        $this->artisan('content:import-legacy-articles')
            ->expectsOutputToContain('CREATED=6')
            ->expectsOutputToContain('STATUS=DRAFT')
            ->assertSuccessful();

        $this->assertSame(6, ContentArticle::query()->where('status', 'draft')->count());
        $article = ContentArticle::query()->where('slug', 'kitchen-leadership-chef-as-team-builder')->firstOrFail();
        $this->assertSame('Legacy article 251 ar', $article->title['ar']);
        $this->assertSame('Legacy article 251 en', $article->title['en']);
        $this->assertSame('Legacy article 251 fr', $article->title['fr']);
        $this->assertSame('https://www.iuoamc.uk/ar/blog/post/251', $article->source_url);
        $this->assertNotNull($article->original_published_at);
        Storage::disk('public')->assertExists('editorial/articles/legacy/251.webp');
        $this->get('/ar/articles/kitchen-leadership-chef-as-team-builder')->assertNotFound();

        $this->artisan('content:import-legacy-articles')
            ->expectsOutputToContain('CREATED=0')
            ->expectsOutputToContain('SKIPPED_EXISTING=6')
            ->assertSuccessful();
        $this->assertSame(6, ContentArticle::query()->count());

        $this->artisan('content:import-legacy-articles', [
            '--refresh' => true,
            '--confirm' => 'REFRESH-6-LEGACY-ARTICLES',
        ])->expectsOutputToContain('REFRESHED=6')->assertSuccessful();
        $article->refresh();
        $this->assertStringContainsString('## Heading', $article->body['en']);
        $this->assertSame('draft', $article->status);
    }

    public function test_unauthenticated_editorial_request_redirects_to_localized_login(): void
    {
        $this->get('/en/control/journal')
            ->assertRedirect(route('login', ['locale' => 'en']));
    }

    public function test_author_submission_resources_are_public_but_the_intake_form_is_not_indexable(): void
    {
        $this->enablePublicLaunch();
        $this->get('/en/journal/author-guidelines')
            ->assertOk()
            ->assertSee('Originality and authorship');

        $this->get('/en/journal/submit')
            ->assertOk()
            ->assertSee('noindex,nofollow,noarchive', false)
            ->assertSee('Submit for editorial screening');
    }

    public function test_editorial_workspace_is_private_and_not_indexable(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->get('/en/control/journal')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_editor_can_open_professional_article_details(): void
    {
        $article = $this->createArticle('professional_article', 'draft', 'Professional detail view');
        $user = $this->superAdmin();

        $this->actingAs($user)->get('/ar/control/journal/articles/'.$article->id)
            ->assertOk()
            ->assertSee($article->article_code)
            ->assertSee('name="publication_pdf"', false);
    }

    public function test_peer_reviewed_research_cannot_be_accepted_before_peer_review(): void
    {
        $article = $this->createArticle('peer_reviewed_research', 'initial_screening', 'Research awaiting review');
        $user = $this->superAdmin();

        $this->expectException(ValidationException::class);

        app(JournalWorkflow::class)->transition($user, $article->id, $article->lock_version, 'accept');
    }

    public function test_professional_article_can_be_published_directly_without_research_procedures(): void
    {
        $this->installIntegrityKeys();
        $article = $this->createArticle('professional_article', 'draft', 'Direct editorial article');
        $publisher = $this->superAdmin();

        $published = app(JournalWorkflow::class)->transition(
            $publisher,
            $article->id,
            $article->lock_version,
            'publish_professional'
        );

        $this->assertSame('published', $published->status);
        $this->assertSame(1, $published->version_of_record);
        $this->assertSame(0, $published->reviews()->count());
        $this->assertDatabaseHas('journal_article_versions', [
            'journal_article_id' => $published->id,
            'kind' => 'version_of_record',
        ]);
    }

    public function test_public_ai_knowledge_links_only_launched_published_articles(): void
    {
        $published = $this->createArticle('professional_article', 'published', 'Circular Restaurant Tasting');
        $draft = $this->createArticle('professional_article', 'draft', 'Private Draft Tasting');

        $locked = app(PublicAiKnowledge::class)->forQuestion('en', 'Circular Restaurant Tasting');
        $this->assertFalse(collect($locked['sources'])->contains(
            fn (array $source): bool => str_contains($source['url'], '/journal/articles/'),
        ));
        $this->assertStringNotContainsString('Circular Restaurant Tasting', $locked['context']);

        $this->enablePublicLaunch();
        $result = app(PublicAiKnowledge::class)->forQuestion('en', 'Circular Restaurant Tasting');

        $this->assertSame('Circular Restaurant Tasting', $result['sources'][0]['title']);
        $this->assertSame(
            route('journal.public.articles.show', ['locale' => 'en', 'article' => $published->slug]),
            $result['sources'][0]['url'],
        );
        $this->assertStringContainsString('Controlled scholarly content.', $result['context']);
        $this->assertStringNotContainsString('Private Draft Tasting', $result['context']);
    }

    public function test_public_ai_knowledge_understands_the_current_article_page(): void
    {
        $this->enablePublicLaunch();
        $article = $this->createArticle('professional_article', 'published', 'Sensory Memory in Restaurants');

        $result = app(PublicAiKnowledge::class)->forQuestion(
            'en',
            'Explain this article',
            '/en/journal/articles/'.$article->slug,
        );

        $this->assertSame('Sensory Memory in Restaurants', $result['sources'][0]['title']);
    }

    public function test_public_ai_knowledge_understands_general_article_sections(): void
    {
        $section = \App\Models\ContentSection::query()->where('slug', 'recipes-techniques')->firstOrFail();
        $result = app(PublicAiKnowledge::class)->forQuestion(
            'en',
            'Explain this editorial section',
            '/en/articles/sections/'.$section->slug,
        );

        $this->assertSame($section->localized('name', 'en'), $result['sources'][0]['title']);
        $this->assertSame(
            route('public.articles.sections.show', ['locale' => 'en', 'section' => $section]),
            $result['sources'][0]['url'],
        );
        $this->assertStringContainsString($section->localized('description', 'en'), $result['context']);
    }

    public function test_public_ai_knowledge_covers_journal_policies_guidelines_and_submission(): void
    {
        $this->enablePublicLaunch();

        $policies = app(PublicAiKnowledge::class)->forQuestion(
            'en',
            'What is the artificial intelligence policy?',
            '/en/journal/policies',
        );
        $this->assertSame('Editorial policies', $policies['sources'][0]['title']);
        $this->assertStringContainsString('AI tools cannot be credited as authors', $policies['context']);

        $guidelines = app(PublicAiKnowledge::class)->forQuestion(
            'en',
            'What must an author prepare?',
            '/en/journal/author-guidelines',
        );
        $this->assertSame('Author guidelines', $guidelines['sources'][0]['title']);
        $this->assertStringContainsString('Prepare the complete manuscript', $guidelines['context']);

        $submission = app(PublicAiKnowledge::class)->forQuestion(
            'en',
            'How does this submission page protect my manuscript?',
            '/en/journal/submit',
        );
        $this->assertSame('Submit scientific research', $submission['sources'][0]['title']);
        $this->assertStringContainsString('Files are stored outside the public catalogue', $submission['context']);
    }

    public function test_public_ai_knowledge_exposes_only_published_issues_and_consented_editorial_members(): void
    {
        $this->enablePublicLaunch();
        $journal = Journal::query()->firstOrFail();
        $editor = $this->superAdmin();
        JournalIssue::query()->create([
            'journal_id' => $journal->id,
            'volume' => 11,
            'number' => 1,
            'slug' => 'public-ai-issue',
            'title' => ['ar' => 'عدد عام', 'en' => 'Public AI Issue', 'fr' => 'Numéro IA public'],
            'description' => ['ar' => 'وصف', 'en' => 'Published culinary research issue', 'fr' => 'Description'],
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);
        JournalIssue::query()->create([
            'journal_id' => $journal->id,
            'volume' => 12,
            'number' => 1,
            'slug' => 'private-ai-issue',
            'title' => ['ar' => 'مسودة عدد', 'en' => 'Private Draft Issue', 'fr' => 'Brouillon privé'],
            'description' => ['ar' => 'خاص', 'en' => 'Private issue description', 'fr' => 'Privé'],
            'status' => 'draft',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);
        foreach ([
            ['Consented Public Editor', 'active', now()],
            ['Private Draft Editor', 'draft', null],
        ] as [$name, $status, $consentedAt]) {
            JournalEditorialMember::query()->create([
                'record_uuid' => (string) Str::uuid(),
                'journal_id' => $journal->id,
                'name' => $name,
                'role' => 'editor_in_chief',
                'status' => $status,
                'sort_order' => 1,
                'consented_at' => $consentedAt,
                'created_by' => $editor->id,
                'updated_by' => $editor->id,
            ]);
        }

        $issue = app(PublicAiKnowledge::class)->forQuestion(
            'en',
            'Explain this issue',
            '/en/journal/issues/public-ai-issue',
        );
        $this->assertSame('Public AI Issue', $issue['sources'][0]['title']);
        $this->assertStringNotContainsString('Private Draft Issue', $issue['context']);

        $governance = app(PublicAiKnowledge::class)->forQuestion(
            'en',
            'Who is on the editorial board?',
            '/en/journal/editorial-governance',
        );
        $this->assertStringContainsString('Consented Public Editor', $governance['context']);
        $this->assertStringNotContainsString('Private Draft Editor', $governance['context']);
    }

    public function test_scientific_research_cannot_use_direct_professional_publication(): void
    {
        $article = $this->createArticle('peer_reviewed_research', 'draft', 'Research requiring review');
        $publisher = $this->superAdmin();

        $this->expectException(ValidationException::class);

        app(JournalWorkflow::class)->transition(
            $publisher,
            $article->id,
            $article->lock_version,
            'publish_professional'
        );
    }

    public function test_publishing_creates_an_immutable_version_of_record(): void
    {
        $this->installIntegrityKeys();
        $article = $this->createArticle('peer_reviewed_research', 'ready_to_publish', 'Immutable research record');
        $user = $this->superAdmin();

        $published = app(JournalWorkflow::class)->transition($user, $article->id, $article->lock_version, 'publish');

        $this->assertSame('published', $published->status);
        $this->assertSame(1, $published->version_of_record);
        $this->assertSame(64, strlen((string) $published->version_of_record_hash));
        $this->assertDatabaseHas('journal_article_versions', [
            'journal_article_id' => $published->id,
            'version' => 1,
            'payload_sha256' => $published->version_of_record_hash,
        ]);

        $this->expectException(LogicException::class);
        $published->translations->first()->update(['title' => 'Silent replacement']);
    }

    public function test_two_completed_reviews_allow_research_acceptance(): void
    {
        $this->installIntegrityKeys();
        $article = $this->createArticle('peer_reviewed_research', 'under_review', 'Independently reviewed research');
        $editor = $this->superAdmin();
        foreach (range(1, 2) as $round) {
            $reviewer = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
            JournalReview::query()->create([
                'journal_article_id' => $article->id,
                'reviewer_id' => $reviewer->id,
                'round' => $round,
                'status' => 'submitted',
                'recommendation' => 'accept',
                'author_comments' => 'Independent review completed.',
                'submitted_at' => now(),
                'assigned_by' => $editor->id,
            ]);
        }

        $accepted = app(JournalWorkflow::class)->transition($editor, $article->id, $article->lock_version, 'accept');

        $this->assertSame('accepted', $accepted->status);
        $this->assertNotNull($accepted->accepted_at);
        $this->assertDatabaseHas('journal_editorial_decisions', [
            'journal_article_id' => $accepted->id,
            'decision' => 'accepted',
            'issued_by' => $editor->id,
        ]);
    }

    public function test_reviewer_cannot_open_an_unassigned_editorial_record(): void
    {
        $assigned = $this->createArticle('peer_reviewed_research', 'under_review', 'Assigned record');
        $unassigned = $this->createArticle('peer_reviewed_research', 'under_review', 'Unassigned record');
        $reviewer = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $reviewerRole = Role::query()->where('slug', 'journal-reviewer')->firstOrFail();
        $reviewer->roles()->attach($reviewerRole);
        JournalReview::query()->create([
            'journal_article_id' => $assigned->id,
            'reviewer_id' => $reviewer->id,
            'round' => 1,
            'status' => 'invited',
            'assigned_by' => $assigned->created_by,
        ]);

        $this->actingAs($reviewer)
            ->get('/en/control/journal/articles/'.$unassigned->id)
            ->assertNotFound();
    }

    public function test_public_submission_is_private_encrypted_and_fingerprinted(): void
    {
        $this->installIntegrityKeys();
        $this->enablePublicLaunch();
        Storage::fake('local');

        $response = $this->post('/en/journal/submit', $this->validSubmissionPayload());

        $response->assertRedirect('/en/journal/submission-confirmation')->assertSessionHas('journal_submission_receipt');
        $submission = JournalSubmission::query()->firstOrFail();
        $this->assertSame('author@example.test', $submission->author_email);
        $this->assertNotSame('author@example.test', DB::table('journal_submissions')->value('author_email'));
        $this->assertSame(64, strlen($submission->file_sha256));
        Storage::disk('local')->assertExists($submission->manuscript_path);
        $this->assertDatabaseMissing('journal_articles', ['slug' => Str::slug($submission->title)]);
        $this->assertDatabaseHas('journal_notification_outbox', [
            'event' => 'submission_received',
            'status' => 'pending',
            'subject_type' => $submission->getMorphClass(),
            'subject_id' => $submission->id,
        ]);
    }

    public function test_public_research_intake_rejects_professional_articles(): void
    {
        $this->enablePublicLaunch();
        Storage::fake('local');
        $payload = $this->validSubmissionPayload();
        $payload['type'] = 'professional_article';

        $this->post('/en/journal/submit', $payload)
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('journal_submissions', 0);
    }

    public function test_submission_tracking_requires_the_private_token(): void
    {
        $this->installIntegrityKeys();
        $this->enablePublicLaunch();
        Storage::fake('local');
        $response = $this->post('/en/journal/submit', $this->validSubmissionPayload());
        $receipt = session('journal_submission_receipt');

        $this->post('/en/journal/track-submission', [
            'submission_code' => $receipt['code'],
            'tracking_token' => str_repeat('x', 64),
        ])->assertOk()->assertSee('do not match');

        $this->post('/en/journal/track-submission', [
            'submission_code' => $receipt['code'],
            'tracking_token' => $receipt['token'],
        ])->assertOk()->assertSee('Controlled sensory manuscript')->assertSee('Received');
    }

    public function test_editor_can_convert_a_screened_submission_into_a_draft_record(): void
    {
        $this->installIntegrityKeys();
        $this->enablePublicLaunch();
        Storage::fake('local');
        $this->post('/en/journal/submit', $this->validSubmissionPayload());
        $submission = JournalSubmission::query()->firstOrFail();
        $editor = $this->superAdmin();

        $this->actingAs($editor)->post('/en/control/journal/submissions/'.$submission->id.'/convert')
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('converted', $submission->status);
        $this->assertNotNull($submission->converted_article_id);
        $this->assertDatabaseHas('journal_articles', [
            'id' => $submission->converted_article_id,
            'status' => 'draft',
            'type' => 'peer_reviewed_research',
        ]);
    }

    public function test_anonymous_public_access_returns_404_before_explicit_launch(): void
    {
        $this->get('/en/journal')->assertNotFound();
        $this->get('/en/journal/submit')->assertNotFound();
    }

    public function test_launch_remains_blocked_when_mandatory_real_world_checks_are_missing(): void
    {
        $editor = $this->superAdmin();

        $this->actingAs($editor)->post('/en/control/journal/operations/public-launch')
            ->assertSessionHasErrors('launch');

        $this->assertFalse(Journal::query()->firstOrFail()->isPubliclyLaunched());
    }

    public function test_authorised_launch_succeeds_only_after_every_preflight_check_passes(): void
    {
        $this->installIntegrityKeys();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'journal@iuoamc.pro']);
        File::ensureDirectoryExists((string) config('filesystems.disks.local.root'));
        $editor = $this->superAdmin();
        $this->createArticle('professional_article', 'published', 'Inaugural professional record');
        $journal = Journal::query()->firstOrFail();
        foreach (['editor_in_chief', 'managing_editor', 'section_editor'] as $position => $role) {
            JournalEditorialMember::query()->create([
                'record_uuid' => (string) Str::uuid(),
                'journal_id' => $journal->id,
                'name' => 'Consented Editor '.($position + 1),
                'role' => $role,
                'status' => 'active',
                'sort_order' => $position + 1,
                'consented_at' => now(),
                'created_by' => $editor->id,
                'updated_by' => $editor->id,
            ]);
        }
        $journal->update(['settings' => array_merge($journal->settings ?? [], [
            'contact_email' => 'journal@iuoamc.pro',
            'publication_frequency' => 'continuous',
            'fee_policy' => 'no_fees',
            'backup_verified_at' => now()->toIso8601String(),
            'backup_reference' => 'restore-test-2026-09-11',
            'public_launch_enabled' => false,
        ])]);

        $this->actingAs($editor)->post('/en/control/journal/operations/public-launch')
            ->assertRedirect();

        $this->assertTrue(Journal::query()->firstOrFail()->isPubliclyLaunched());
        $this->assertDatabaseHas('audit_logs', ['event' => 'journal.operations.launched']);
    }

    public function test_empty_issue_cannot_be_published(): void
    {
        $editor = $this->superAdmin();
        $journal = Journal::query()->firstOrFail();
        $issue = JournalIssue::query()->create([
            'journal_id' => $journal->id,
            'volume' => 1,
            'number' => 1,
            'slug' => 'empty-inaugural-issue',
            'title' => ['ar' => 'عدد فارغ', 'en' => 'Empty issue', 'fr' => 'Numéro vide'],
            'status' => 'draft',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->actingAs($editor)->post('/en/control/journal/issues/'.$issue->id.'/publish')
            ->assertSessionHasErrors('issue');

        $this->assertSame('draft', $issue->fresh()->status);
    }

    public function test_active_editorial_appointment_requires_recorded_consent(): void
    {
        $editor = $this->superAdmin();

        $this->actingAs($editor)->post('/en/control/journal/editorial-members', [
            'name' => 'Verified Editor',
            'role' => 'editor_in_chief',
            'status' => 'active',
            'sort_order' => 1,
        ])->assertSessionHasErrors('consent_confirmed');

        $this->assertDatabaseCount('journal_editorial_members', 0);
    }

    public function test_consented_editorial_appointment_is_published_without_private_drafts(): void
    {
        $this->installIntegrityKeys();
        $this->enablePublicLaunch();
        $editor = $this->superAdmin();
        $payload = [
            'name' => 'Verified Editor',
            'role' => 'editor_in_chief',
            'status' => 'active',
            'sort_order' => 1,
            'affiliation_en' => 'Verified Culinary Institute',
            'consent_confirmed' => '1',
        ];

        $this->actingAs($editor)->post('/en/control/journal/editorial-members', $payload)->assertRedirect();
        JournalEditorialMember::query()->create([
            'record_uuid' => (string) Str::uuid(),
            'journal_id' => Journal::query()->firstOrFail()->id,
            'name' => 'Private Draft Name',
            'role' => 'section_editor',
            'status' => 'draft',
            'sort_order' => 2,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->get('/en/journal/editorial-governance')
            ->assertOk()
            ->assertSee('Verified Editor')
            ->assertSee('Verified Culinary Institute')
            ->assertDontSee('Private Draft Name');
    }

    public function test_notification_outbox_dispatches_mail_and_seals_delivery_state(): void
    {
        $this->installIntegrityKeys();
        $this->enablePublicLaunch();
        Storage::fake('local');
        Mail::fake();
        $this->post('/en/journal/submit', $this->validSubmissionPayload());

        $result = app(JournalNotificationService::class)->dispatchPending();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        Mail::assertSent(JournalWorkflowMail::class, fn (JournalWorkflowMail $mail): bool => $mail->outbox->event === 'submission_received');
        $this->assertSame('sent', JournalNotificationOutbox::query()->firstOrFail()->status);
    }

    public function test_author_facing_rejection_is_permanent_and_queued_for_delivery(): void
    {
        $this->installIntegrityKeys();
        $article = $this->createArticle('peer_reviewed_research', 'under_review', 'Rejected research');
        $article->authors->first()->update(['email' => 'author@research.org']);
        $editor = $this->superAdmin();

        $rejected = app(JournalWorkflow::class)->transition($editor, $article->id, $article->lock_version, 'reject', 'The methods do not support the stated conclusion.');

        $this->assertSame('rejected', $rejected->status);
        $this->assertDatabaseHas('journal_editorial_decisions', ['journal_article_id' => $article->id, 'decision' => 'rejected']);
        $this->assertDatabaseHas('journal_notification_outbox', ['event' => 'article_rejected', 'status' => 'pending']);
    }


    public function test_editor_can_verify_wicp_registration_and_final_pdf_before_publication(): void
    {
        Storage::fake('local');
        $article = $this->createArticle('peer_reviewed_research', 'ready_to_publish', 'WICP protected research');
        $editor = $this->superAdmin();
        $pdf = UploadedFile::fake()->createWithContent('final.pdf', "%PDF-1.4\nverified publication");

        $this->actingAs($editor)->post('/en/control/journal/articles/'.$article->id.'/publication-assets', [
            'publication_pdf' => $pdf,
            'wicp_registration_number' => 'WICP-EXTERNAL-2026-7788',
            'wicp_registered_at' => '2026-09-11',
            'wicp_verification_url' => 'https://example.com/wicp/WICP-EXTERNAL-2026-7788',
            'wicp_verified' => '1',
        ])->assertRedirect();

        $article->refresh();
        $this->assertSame('WICP-EXTERNAL-2026-7788', $article->wicp_registration_number);
        $this->assertSame(64, strlen((string) $article->pdf_sha256));
        $this->assertNotNull($article->wicp_verified_at);
        Storage::disk('local')->assertExists($article->pdf_path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'journal.article.publication_assets_verified']);
    }

    public function test_public_reader_paginates_long_research_and_downloads_verified_pdf(): void
    {
        Storage::fake('local');
        $this->enablePublicLaunch();
        $article = $this->createArticle('peer_reviewed_research', 'published', 'Long verified research');
        $longBody = collect(range(1, 80))->map(fn (int $number): string => 'Section '.$number.' '.str_repeat('controlled sensory evidence ', 24))->join("\n\n");
        DB::table('journal_article_translations')->where('journal_article_id', $article->id)->where('locale', 'en')->update(['body' => $longBody]);

        $this->get('/en/journal/articles/'.$article->slug.'?page=2')
            ->assertOk()
            ->assertSee('Page 2 of')
            ->assertSee('data-ai-panel', false)
            ->assertSee(route('public.ai.ask', ['locale' => 'en']), false);

        $this->get('/en/journal/articles/'.$article->slug.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame(1, $article->fresh()->pdf_downloads_count);
    }

    public function test_public_wicp_registry_links_the_external_number_to_the_immutable_record(): void
    {
        Storage::fake('local');
        $this->enablePublicLaunch();
        $article = $this->createArticle('professional_article', 'published', 'Registered professional article');

        $this->get('/en/journal/registry/wicp/'.$article->wicp_registration_number)
            ->assertOk()
            ->assertSee($article->wicp_registration_number)
            ->assertSee($article->pdf_sha256)
            ->assertSee($article->version_of_record_hash);
    }


    public function test_new_article_receives_an_editable_wicp_test_placeholder(): void
    {
        $journal = Journal::query()->firstOrFail();
        $editor = $this->superAdmin();
        $uuid = (string) Str::uuid();

        $article = JournalArticle::query()->create([
            'record_uuid' => $uuid,
            'journal_id' => $journal->id,
            'article_code' => 'PLACEHOLDER-'.Str::upper(Str::random(8)),
            'slug' => 'placeholder-'.Str::lower(Str::random(8)),
            'type' => 'professional_article',
            'status' => 'draft',
            'primary_locale' => 'en',
            'license' => 'all-rights-reserved',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->assertStringStartsWith(JournalArticle::WICP_TEST_PREFIX, $article->wicp_registration_number);
        $this->assertNull($article->wicp_verified_at);
        $this->assertFalse($article->hasVerifiedWicpRegistration());
    }

    public function test_wicp_test_placeholder_can_never_authorise_publication(): void
    {
        $this->installIntegrityKeys();
        $article = $this->createArticle('professional_article', 'draft', 'Blocked placeholder article');
        $article->update([
            'wicp_registration_number' => JournalArticle::WICP_TEST_PREFIX.Str::upper(Str::random(12)),
            'wicp_verified_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(JournalWorkflow::class)->transition(
            $this->superAdmin(),
            $article->id,
            $article->lock_version,
            'publish_professional'
        );
    }

    private function createArticle(string $type, string $status, string $englishTitle): JournalArticle
    {
        $journal = Journal::query()->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $issue = JournalIssue::query()->create([
            'journal_id' => $journal->id,
            'volume' => JournalIssue::query()->count() + 1,
            'number' => 1,
            'slug' => 'test-issue-'.Str::lower(Str::random(8)),
            'title' => ['ar' => 'عدد اختباري', 'en' => 'Test issue', 'fr' => 'Numéro test'],
            'description' => ['ar' => '', 'en' => '', 'fr' => ''],
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $recordUuid = (string) Str::uuid();
        $pdfContents = "%PDF-1.4\\nIUOAMC controlled publication";
        $pdfHash = hash('sha256', $pdfContents);
        $pdfPath = 'journal/publication-pdfs/'.$recordUuid.'/'.$pdfHash.'.pdf';
        Storage::disk('local')->put($pdfPath, $pdfContents);
        $article = JournalArticle::query()->create([
            'record_uuid' => $recordUuid,
            'journal_id' => $journal->id,
            'journal_issue_id' => $issue->id,
            'article_code' => 'TEST-'.Str::upper(Str::random(10)),
            'slug' => Str::slug($englishTitle).'-'.Str::lower(Str::random(6)),
            'type' => $type,
            'status' => $status === 'published' ? 'ready_to_publish' : $status,
            'primary_locale' => 'ar',
            'license' => 'all-rights-reserved',
            'pdf_path' => $pdfPath,
            'pdf_sha256' => $pdfHash,
            'pdf_size' => strlen($pdfContents),
            'pdf_downloads_count' => 0,
            'wicp_registration_number' => 'WICP-VERIFIED-FIXTURE-'.Str::upper(Str::substr(str_replace('-', '', $recordUuid), 0, 12)),
            'wicp_registered_at' => now()->toDateString(),
            'wicp_verification_url' => 'https://example.com/wicp/'.Str::substr($recordUuid, 0, 8),
            'wicp_verified_at' => now(),
            'published_at' => null,
            'version_of_record' => 0,
            'version_of_record_hash' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        foreach (['ar' => 'بحث تذوق اختباري', 'en' => $englishTitle, 'fr' => 'Recherche de dégustation test'] as $locale => $title) {
            $article->translations()->create([
                'locale' => $locale,
                'title' => $title,
                'abstract' => 'A controlled abstract for the journal publishing test.',
                'body' => 'Controlled scholarly content.',
                'keywords' => ['tasting', 'gastronomy'],
                'references' => ['Reference one'],
            ]);
        }
        $author = JournalAuthor::query()->create([
            'record_uuid' => (string) Str::uuid(),
            'name' => 'Test Author',
            'latin_name' => 'Test Author',
            'orcid' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $article->authors()->attach($author->id, ['position' => 1, 'is_corresponding' => true]);

        if ($status === 'published') {
            $article->update([
                'status' => 'published',
                'published_at' => now(),
                'version_of_record' => 1,
                'version_of_record_hash' => hash('sha256', $englishTitle),
            ]);
        }

        return $article->fresh(['translations', 'authors', 'issue']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrator', 'is_system' => true]);
        $user->roles()->attach($role);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validSubmissionPayload(): array
    {
        return [
            'type' => 'peer_reviewed_research',
            'primary_locale' => 'en',
            'title' => 'Controlled sensory manuscript',
            'abstract' => 'A complete abstract submitted for confidential editorial screening.',
            'keywords' => 'sensory evaluation, gastronomy',
            'manuscript' => UploadedFile::fake()->create('manuscript.pdf', 120, 'application/pdf'),
            'author_name' => 'Submission Author',
            'author_email' => 'author@example.test',
            'affiliation' => 'Independent Culinary Research Unit',
            'orcid' => '0000-0002-1825-0097',
            'country_code' => 'GB',
            'conflicts' => 'No competing interests declared.',
            'funding' => 'No external funding.',
            'ethics' => 'No human participants were involved.',
            'authorship_confirmed' => '1',
            'originality_confirmed' => '1',
            'privacy_confirmed' => '1',
        ];
    }

    private function installIntegrityKeys(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        $directory = storage_path('app/secure/integrity');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/ed25519.secret', base64_encode(sodium_crypto_sign_secretkey($keyPair)));
        File::put($directory.'/ed25519.public', base64_encode(sodium_crypto_sign_publickey($keyPair)));
    }

    private function enablePublicLaunch(): void
    {
        $journal = Journal::query()->firstOrFail();
        $journal->update(['settings' => array_merge($journal->settings ?? [], ['public_launch_enabled' => true])]);
    }
}
