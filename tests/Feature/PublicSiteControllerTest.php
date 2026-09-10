<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PublicPage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PublicSiteControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->primary(['role_id', 'user_id']);
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('module');
            $table->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_root')->default(false);
            $table->timestamps();
        });
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('group');
            $table->string('key');
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
        Schema::create('public_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('template')->default('standard');
            $table->json('navigation_label');
            $table->json('eyebrow');
            $table->json('title');
            $table->json('summary');
            $table->json('body');
            $table->json('seo_title');
            $table->json('seo_description');
            $table->unsignedSmallInteger('navigation_order')->default(0);
            $table->boolean('show_in_navigation')->default(true);
            $table->string('status')->default('published');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        $this->createPage('home', false);
        $this->createPage('about', true);
        $this->createLeadershipPage();
        $this->createEntityPage('icga');
    }

    public function test_renders_published_home_in_arabic(): void
    {
        $response = $this->get('/ar')
            ->assertOk()
            ->assertSee('منظومة مؤسسية عالمية')
            ->assertSee('QR + NFC')
            ->assertSee('WSA-CA')
            ->assertSee('ICGA')
            ->assertSee('®')
            ->assertSee('WSACT')
            ->assertSee('IUOAMC TV')
            ->assertSee('/ar/entities/icga', false)
            ->assertSee('info@iuoamc.uk')
            ->assertSee('hreflang="en"', false)
            ->assertSee('"@context":"https://schema.org"', false)
            ->assertDontSee('__contextArgs', false)
            ->assertDontSee('لوحة التحكم');

        self::assertSame(1, preg_match('/nonce="([^"]+)"/', (string) $response->getContent(), $nonce));
        self::assertStringContainsString("'nonce-{$nonce[1]}'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_renders_the_french_edition_of_a_public_page(): void
    {
        $this->get('/fr/about')
            ->assertOk()
            ->assertSee('Une union professionnelle')
            ->assertSee('protection des données');
    }

    public function test_renders_an_entity_profile_with_public_registry_details(): void
    {
        $this->get('/en/entities/icga')
            ->assertOk()
            ->assertSee('International Culinary & Gastronomy Arbitration')
            ->assertSee('ICGA')
            ->assertSee('®')
            ->assertSee('16846998')
            ->assertSee('10101250')
            ->assertSee('ZC146889')
            ->assertSee('UK00004350642')
            ->assertSee('10604085')
            ->assertSee('/ar/entities/icga', false)
            ->assertSee('/fr/entities/icga', false);
    }

    public function test_renders_the_official_leadership_profile_as_structured_public_knowledge(): void
    {
        $this->get('/en/leadership')
            ->assertOk()
            ->assertSee('Engineer &amp; Master Chef Ahmad Maadarani', false)
            ->assertSee('President General &amp; Authorised Signatory', false)
            ->assertSee('International Arbitration in Culinary Arts and Gastronomy')
            ->assertSee('first initiative to codify the field')
            ->assertSee('The 17 Signals of Flavour')
            ->assertDontSee('telecommunications engineering')
            ->assertDontSee('hotel management')
            ->assertSee('assets/brand/leadership/ahmad-maadarani-president-general-v1.webp', false)
            ->assertSee('"@type":"Person"', false)
            ->assertSee('/ar/leadership', false)
            ->assertSee('/fr/leadership', false);
    }

    public function test_ai_concierge_uses_only_the_public_knowledge_request(): void
    {
        config()->set('services.openai.api_key', 'test-project-key');
        config()->set('services.openai.model', 'gpt-5-mini');
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test_iuoamc',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'IUOAMC is the system lead entity. [SOURCE 1]',
                    ]],
                ]],
            ]),
        ]);

        $this->postJson('/en/ai/ask', ['question' => 'Who is Ahmad Maadarani?'])
            ->assertOk()
            ->assertJsonPath('answer', 'IUOAMC is the system lead entity. [SOURCE 1]')
            ->assertJsonPath('request_id', 'resp_test_iuoamc')
            ->assertJsonStructure(['sources' => [['title', 'url']]]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['model'] === 'gpt-5-mini'
                && $request['store'] === false
                && str_contains($request['instructions'], 'official public information assistant')
                && str_contains($request['input'], 'OFFICIAL KNOWLEDGE')
                && str_contains($request['input'], 'first initiative to codify the field')
                && str_contains($request['input'], 'The 17 Signals of Flavour')
                && str_contains($request['input'], '/en/leadership')
                && $request->hasHeader('Authorization', 'Bearer test-project-key');
        });
    }

    public function test_ai_concierge_fails_safely_without_a_server_key(): void
    {
        config()->set('services.openai.api_key', null);

        $this->postJson('/ar/ai/ask', ['question' => 'ما هي المنظومة؟'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'المساعد غير متاح مؤقتاً. يرجى التواصل عبر info@iuoamc.uk.');

        Http::assertNothingSent();
    }

    public function test_returns_not_found_for_a_draft_page(): void
    {
        PublicPage::query()->where('slug', 'about')->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->get('/en/about')->assertNotFound();
    }

    public function test_escapes_managed_public_content(): void
    {
        $page = PublicPage::query()->where('slug', 'about')->firstOrFail();
        $body = $page->body;
        $body['en'] = '<script>alert("public-content")</script>';
        $page->update(['body' => $body]);

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("public-content")</script>', false);
    }

    public function test_public_content_control_rejects_an_unprivileged_user(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get('/en/control/public-content')
            ->assertForbidden();
    }

    public function test_authenticated_super_administrator_sees_the_control_link(): void
    {
        $role = Role::query()->create([
            'name' => 'Super Administrator',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);
        $user = User::factory()->create([
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/en')
            ->assertOk()
            ->assertSee('Control centre');
    }

    private function createPage(string $slug, bool $navigation): void
    {
        $copy = $slug === 'home'
            ? [
                'ar' => 'منظومة مؤسسية عالمية',
                'en' => 'A global institutional system',
                'fr' => 'Un système institutionnel mondial',
            ]
            : [
                'ar' => 'اتحاد مهني بمنظومة رقمية موحدة',
                'en' => 'A professional union with a unified digital system',
                'fr' => 'Une union professionnelle dotée d’un système numérique unifié',
            ];

        PublicPage::query()->create([
            'slug' => $slug,
            'template' => $slug === 'home' ? 'home' : 'standard',
            'navigation_label' => ['ar' => 'عن المنظومة', 'en' => 'About', 'fr' => 'À propos'],
            'eyebrow' => $copy,
            'title' => $copy,
            'summary' => $copy,
            'body' => $slug === 'about'
                ? ['ar' => 'حماية البيانات', 'en' => 'Data protection', 'fr' => 'protection des données']
                : $copy,
            'seo_title' => $copy,
            'seo_description' => $copy,
            'navigation_order' => $slug === 'home' ? 0 : 10,
            'show_in_navigation' => $navigation,
            'status' => 'published',
            'revision' => 1,
            'published_at' => now(),
        ]);
    }

    private function createEntityPage(string $slug): void
    {
        $copy = [
            'ar' => 'التحكيم المهني',
            'en' => 'International Culinary & Gastronomy Arbitration',
            'fr' => 'Arbitrage international culinaire et gastronomique',
        ];

        PublicPage::query()->create([
            'slug' => 'entity-'.$slug,
            'template' => 'entity',
            'navigation_label' => $copy,
            'eyebrow' => $copy,
            'title' => $copy,
            'summary' => $copy,
            'body' => $copy,
            'seo_title' => $copy,
            'seo_description' => $copy,
            'navigation_order' => 500,
            'show_in_navigation' => false,
            'status' => 'published',
            'revision' => 1,
            'published_at' => now(),
        ]);
    }

    private function createLeadershipPage(): void
    {
        $title = [
            'ar' => 'المهندس وماستر شيف أحمد المعدراني',
            'en' => 'Engineer & Master Chef Ahmad Maadarani',
            'fr' => 'Ingénieur & Master Chef Ahmad Maadarani',
        ];

        $body = [
            'ar' => 'صاحب المبادرة الأولى لتقنين المجال ومؤلف الإشارات الـ17 للنكهة',
            'en' => 'Originator of the first initiative to codify the field and author of The 17 Signals of Flavour',
            'fr' => 'Initiateur de la première démarche de codification du domaine et auteur des 17 signaux de la saveur',
        ];

        PublicPage::query()->create([
            'slug' => 'leadership',
            'template' => 'leadership',
            'navigation_label' => ['ar' => 'القيادة', 'en' => 'Leadership', 'fr' => 'Direction'],
            'eyebrow' => $title,
            'title' => $title,
            'summary' => $title,
            'body' => $body,
            'seo_title' => $title,
            'seo_description' => $body,
            'navigation_order' => 15,
            'show_in_navigation' => true,
            'status' => 'published',
            'revision' => 1,
            'published_at' => now(),
        ]);
    }
}
