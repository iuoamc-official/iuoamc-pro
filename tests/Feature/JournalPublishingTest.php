<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\JournalAuthor;
use App\Models\JournalIssue;
use App\Models\JournalReview;
use App\Models\Role;
use App\Models\User;
use App\Services\JournalWorkflow;
use Illuminate\Support\Facades\File;
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
        ] as $migrationFile) {
            $migration = require database_path('migrations/'.$migrationFile);
            $migration->up();
        }
    }

    public function test_public_catalog_separates_research_from_professional_articles_and_hides_drafts(): void
    {
        $research = $this->createArticle('peer_reviewed_research', 'published', 'Circular Tasting Research');
        $professional = $this->createArticle('professional_article', 'published', 'Restaurant Tasting Practice');
        $draft = $this->createArticle('peer_reviewed_research', 'draft', 'Hidden Research Draft');

        $this->get('/en/journal')
            ->assertOk()
            ->assertSee('Peer-reviewed scientific research')
            ->assertSee('Professional article — not peer reviewed')
            ->assertSee($research->translation('en')->title)
            ->assertSee($professional->translation('en')->title)
            ->assertDontSee($draft->translation('en')->title);
    }

    public function test_unauthenticated_editorial_request_redirects_to_localized_login(): void
    {
        $this->get('/en/control/journal')
            ->assertRedirect(route('login', ['locale' => 'en']));
    }

    public function test_editorial_workspace_is_private_and_not_indexable(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->get('/en/control/journal')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_peer_reviewed_research_cannot_be_accepted_before_peer_review(): void
    {
        $article = $this->createArticle('peer_reviewed_research', 'initial_screening', 'Research awaiting review');
        $user = $this->superAdmin();

        $this->expectException(ValidationException::class);

        app(JournalWorkflow::class)->transition($user, $article->id, $article->lock_version, 'accept');
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
        $article = JournalArticle::query()->create([
            'record_uuid' => (string) Str::uuid(),
            'journal_id' => $journal->id,
            'journal_issue_id' => $issue->id,
            'article_code' => 'TEST-'.Str::upper(Str::random(10)),
            'slug' => Str::slug($englishTitle).'-'.Str::lower(Str::random(6)),
            'type' => $type,
            'status' => $status === 'published' ? 'ready_to_publish' : $status,
            'primary_locale' => 'ar',
            'license' => 'all-rights-reserved',
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

    private function installIntegrityKeys(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        $directory = storage_path('app/secure/integrity');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/ed25519.secret', base64_encode(sodium_crypto_sign_secretkey($keyPair)));
        File::put($directory.'/ed25519.public', base64_encode(sodium_crypto_sign_publickey($keyPair)));
    }
}
