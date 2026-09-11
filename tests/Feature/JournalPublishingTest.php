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
use App\Models\Role;
use App\Models\User;
use App\Services\JournalWorkflow;
use App\Services\JournalNotificationService;
use App\Mail\JournalWorkflowMail;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Page 2 of');

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
            'wicp_registration_number' => 'WICP-TEST-'.Str::upper(Str::substr(str_replace('-', '', $recordUuid), 0, 12)),
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
