<?php

namespace Tests\Feature\Settings;

use App\Actions\ApplicationSettings\FrontendAppearanceSettings;
use App\Actions\ApplicationSettings\UpdateFrontendAppearanceSettings;
use App\Models\ApplicationSetting;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FrontendAppearanceSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{'system'|'light'|'dark'}>
     */
    public static function forcedModes(): array
    {
        return [
            'system' => ['system'],
            'light' => ['light'],
            'dark' => ['dark'],
        ];
    }

    public function test_guests_and_non_administrators_cannot_read_or_write_settings(): void
    {
        $this->get(route('frontend-appearance.edit'))->assertRedirect(route('login'));
        $this->put(route('frontend-appearance.update'), ['mode' => 'dark'])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('frontend-appearance.edit'))->assertForbidden();
        $this->put(route('frontend-appearance.update'), ['mode' => 'dark'])->assertForbidden();
        $this->assertDatabaseCount('application_settings', 0);
    }

    public function test_settings_page_is_unavailable_without_the_public_site(): void
    {
        config(['starter.features.public_site' => false]);
        $this->actingAs($this->administrator());

        $this->get(route('frontend-appearance.edit'))->assertNotFound();
        $this->put(route('frontend-appearance.update'), ['mode' => 'dark'])->assertNotFound();
    }

    public function test_settings_are_validated_and_persisted(): void
    {
        $this->actingAs($this->administrator());

        $this->get(route('frontend-appearance.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('settings/FrontendAppearance')
            ->where('settings', ['mode' => 'toggle']));

        $this->put(route('frontend-appearance.update'), [])->assertSessionHasErrors('mode');
        $this->put(route('frontend-appearance.update'), ['mode' => 'sepia'])->assertSessionHasErrors('mode');
        $this->put(route('frontend-appearance.update'), ['mode' => ['dark']])->assertSessionHasErrors('mode');
        $this->assertDatabaseCount('application_settings', 0);

        $this->put(route('frontend-appearance.update'), ['mode' => 'dark', 'secret' => 'ignore'])
            ->assertRedirect(route('frontend-appearance.edit'));

        $this->assertSame(['mode' => 'dark'], app(FrontendAppearanceSettings::class)->get());
        $this->assertStringNotContainsString('secret', ApplicationSetting::query()->sole()->value);

        $this->get(route('frontend-appearance.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('settings', ['mode' => 'dark'])
            ->where('frontendAppearance', ['mode' => 'dark']));

        $this->put(route('frontend-appearance.update'), ['mode' => 'toggle'])->assertRedirect();
        $this->assertSame(['mode' => 'toggle'], app(FrontendAppearanceSettings::class)->get());
        $this->assertDatabaseCount('application_settings', 1);
    }

    public function test_visitor_choice_is_used_when_the_toggle_is_enabled(): void
    {
        $this->withUnencryptedCookie('frontend_appearance', 'dark')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-appearance="dark"', false)
            ->assertInertia(fn (Assert $page) => $page->where('frontendAppearance', ['mode' => 'toggle']));
    }

    #[DataProvider('forcedModes')]
    public function test_forced_mode_overrides_the_visitor_choice_on_public_pages(string $mode): void
    {
        app(UpdateFrontendAppearanceSettings::class)->handle(['mode' => $mode]);
        $visitorChoice = $mode === 'dark' ? 'light' : 'dark';

        $response = $this->withUnencryptedCookie('frontend_appearance', $visitorChoice)->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('data-appearance="'.$mode.'"', false)
            ->assertSee('data-appearance-surface="frontend"', false)
            ->assertInertia(fn (Assert $page) => $page->where('frontendAppearance', ['mode' => $mode]));

        $this->assertSame(1, preg_match('/<html\b[^>]*\bclass="([^"]*)"/i', $response->getContent(), $matches));
        $this->assertSame($mode === 'dark', in_array('dark', preg_split('/\s+/', trim($matches[1])), true));
    }

    public function test_forced_mode_does_not_change_the_dashboard_preference(): void
    {
        app(UpdateFrontendAppearanceSettings::class)->handle(['mode' => 'dark']);
        $user = User::factory()->create(['appearance' => 'light']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-appearance="light"', false)
            ->assertSee('data-appearance-surface="dashboard"', false);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-appearance="dark"', false);
    }

    public function test_invalid_stored_mode_falls_back_to_the_toggle(): void
    {
        ApplicationSetting::query()->create([
            'key' => FrontendAppearanceSettings::KEY,
            'value' => json_encode(['mode' => 'sepia']),
        ]);

        $this->assertSame(['mode' => 'toggle'], app(FrontendAppearanceSettings::class)->get());

        $this->withUnencryptedCookie('frontend_appearance', 'dark')
            ->get(route('home'))
            ->assertSee('data-appearance="dark"', false);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(RolePolicy::ADMINISTRATOR_ROLE, 'web'));

        return $user;
    }
}
