<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ExampleTest extends TestCase
{
    public function test_home_redirects_guests_to_the_default_localized_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('login', ['locale' => 'ar']));
    }
}
