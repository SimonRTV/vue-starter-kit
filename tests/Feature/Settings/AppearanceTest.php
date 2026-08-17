<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('appearance.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_cannot_update_appearance_preferences(): void
    {
        $this->patchJson(route('appearance.update'), [
            'appearance' => 'dark',
        ])->assertUnauthorized();
    }

    public function test_appearance_settings_page_uses_the_authenticated_users_preferences(): void
    {
        $user = User::factory()->create([
            'appearance' => 'dark',
            'admin_theme' => 'forest',
        ]);

        $response = $this
            ->actingAs($user)
            ->withUnencryptedCookie('appearance', 'light')
            ->withUnencryptedCookie('admin_theme', 'ocean')
            ->get(route('appearance.edit'));

        $response
            ->assertOk()
            ->assertSee('data-admin-theme="forest"', false)
            ->assertSee('data-appearance="dark"', false)
            ->assertSee('data-appearance-surface="dashboard"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Appearance')
                ->where('auth.user.appearance', 'dark')
                ->where('auth.user.admin_theme', 'forest'),
            );
    }

    public function test_user_can_persist_each_dashboard_appearance_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('appearance.update'), [
                'appearance' => 'dark',
            ])
            ->assertOk()
            ->assertJsonPath('appearance', 'dark')
            ->assertJsonPath('admin_theme', 'neutral');

        $this->actingAs($user)
            ->patchJson(route('appearance.update'), [
                'admin_theme' => 'ocean',
            ])
            ->assertOk()
            ->assertJsonPath('appearance', 'dark')
            ->assertJsonPath('admin_theme', 'ocean');

        $user->refresh();

        $this->assertSame('dark', $user->appearance);
        $this->assertSame('ocean', $user->admin_theme);
    }

    public function test_invalid_appearance_preferences_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('appearance.update'), [
                'appearance' => 'midnight',
                'admin_theme' => 'sunset',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['appearance', 'admin_theme']);

        $user->refresh();

        $this->assertSame('system', $user->appearance);
        $this->assertSame('neutral', $user->admin_theme);
    }

    public function test_dashboard_preferences_are_isolated_between_users(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)
            ->patchJson(route('appearance.update'), [
                'appearance' => 'dark',
                'admin_theme' => 'forest',
            ])
            ->assertOk();

        $firstUser->refresh();
        $secondUser->refresh();

        $this->assertSame('dark', $firstUser->appearance);
        $this->assertSame('forest', $firstUser->admin_theme);
        $this->assertSame('system', $secondUser->appearance);
        $this->assertSame('neutral', $secondUser->admin_theme);
    }

    public function test_public_frontend_uses_its_own_appearance_and_neutral_palette(): void
    {
        $user = User::factory()->create([
            'appearance' => 'dark',
            'admin_theme' => 'forest',
        ]);

        $response = $this
            ->actingAs($user)
            ->withUnencryptedCookie('frontend_appearance', 'light')
            ->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('data-admin-theme="neutral"', false)
            ->assertSee('data-appearance="light"', false)
            ->assertSee('data-appearance-surface="frontend"', false);

        $darkResponse = $this
            ->actingAs($user)
            ->withUnencryptedCookie('frontend_appearance', 'dark')
            ->get(route('home'));

        $darkResponse
            ->assertOk()
            ->assertSee('data-admin-theme="neutral"', false)
            ->assertSee('data-appearance="dark"', false)
            ->assertSee('class="dark"', false);
    }

    public function test_invalid_frontend_appearance_falls_back_to_system(): void
    {
        $response = $this
            ->withUnencryptedCookie('frontend_appearance', 'unknown')
            ->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('data-admin-theme="neutral"', false)
            ->assertSee('data-appearance="system"', false)
            ->assertSee('data-appearance-surface="frontend"', false);
    }
}
