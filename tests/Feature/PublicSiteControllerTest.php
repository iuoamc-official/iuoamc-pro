<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PublicPage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class PublicSiteControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

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
}
