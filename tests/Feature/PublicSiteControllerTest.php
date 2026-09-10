<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PublicPage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
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
    }

    public function test_renders_published_home_in_arabic(): void
    {
        $this->get('/ar')
            ->assertOk()
            ->assertSee('منظومة مؤسسية عالمية')
            ->assertSee('QR + NFC')
            ->assertSee('hreflang="en"', false)
            ->assertDontSee('لوحة التحكم');
    }

    public function test_renders_the_french_edition_of_a_public_page(): void
    {
        $this->get('/fr/about')
            ->assertOk()
            ->assertSee('Une union professionnelle')
            ->assertSee('protection des données');
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
}
