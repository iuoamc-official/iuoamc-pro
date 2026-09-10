<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ExampleTest extends TestCase
{
    public function test_root_redirects_guests_to_the_default_localized_public_home(): void
    {
        $this->get('/')
            ->assertRedirect(route('public.home', ['locale' => 'ar']));
    }
}
