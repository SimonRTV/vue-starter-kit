<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('appearance.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_appearance_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('appearance.edit'));

        $response->assertOk();
    }

    public function test_supported_admin_theme_cookies_are_applied_to_the_document(): void
    {
        $user = User::factory()->create();

        foreach (['neutral', 'ocean', 'forest'] as $adminTheme) {
            $response = $this
                ->actingAs($user)
                ->withUnencryptedCookie('admin_theme', $adminTheme)
                ->get(route('appearance.edit'));

            $response
                ->assertOk()
                ->assertSee('data-admin-theme="'.$adminTheme.'"', false);
        }
    }

    public function test_invalid_admin_theme_cookie_falls_back_to_neutral(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withUnencryptedCookie('admin_theme', 'unknown')
            ->get(route('appearance.edit'));

        $response
            ->assertOk()
            ->assertSee('data-admin-theme="neutral"', false);
    }
}
