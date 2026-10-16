<?php

namespace Tests\Feature\Settings;

use App\Actions\ApplicationSettings\AnalyticsSettings;
use App\Actions\ApplicationSettings\UpdateAnalyticsSettings;
use App\Models\ApplicationSetting;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_and_non_administrators_cannot_read_or_write_settings(): void
    {
        $this->get(route('analytics-settings.edit'))->assertRedirect(route('login'));
        $this->put(route('analytics-settings.update'), [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('analytics-settings.edit'))->assertForbidden();
        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => 'G-ABC1234'])->assertForbidden();
        $this->assertDatabaseCount('application_settings', 0);
    }

    public function test_settings_page_is_unavailable_without_the_public_site(): void
    {
        config(['starter.features.public_site' => false]);
        $this->actingAs($this->administrator());

        $this->get(route('analytics-settings.edit'))->assertNotFound();
        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => 'G-ABC1234'])->assertNotFound();
    }

    public function test_settings_are_validated_normalized_and_persisted(): void
    {
        $this->actingAs($this->administrator());

        $this->get(route('analytics-settings.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('settings/Analytics')
            ->where('settings', ['enabled' => false, 'measurement_id' => '', 'exclude_authenticated' => true]));

        $this->put(route('analytics-settings.update'), ['enabled' => '1'])->assertSessionHasErrors('measurement_id');
        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => 'UA-12345-1'])->assertSessionHasErrors('measurement_id');
        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => 'GTM-ABC1234'])->assertSessionHasErrors('measurement_id');
        $this->put(route('analytics-settings.update'), ['measurement_id' => "G-1');alert('x"])->assertSessionHasErrors('measurement_id');
        $this->assertDatabaseCount('application_settings', 0);

        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => ' g-abc123xyz9 ', 'secret' => 'ignore'])
            ->assertRedirect(route('analytics-settings.edit'));

        $this->assertSame(
            ['enabled' => true, 'measurement_id' => 'G-ABC123XYZ9', 'exclude_authenticated' => false],
            app(AnalyticsSettings::class)->get(),
        );
        $this->assertStringNotContainsString('secret', ApplicationSetting::query()->sole()->value);

        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => 'gt-abc1234', 'exclude_authenticated' => '1'])->assertRedirect();
        $this->assertSame(
            ['enabled' => true, 'measurement_id' => 'GT-ABC1234', 'exclude_authenticated' => true],
            app(AnalyticsSettings::class)->get(),
        );

        $this->put(route('analytics-settings.update'), ['measurement_id' => ''])->assertRedirect();
        $this->assertSame(
            ['enabled' => false, 'measurement_id' => '', 'exclude_authenticated' => false],
            app(AnalyticsSettings::class)->get(),
        );
    }

    public function test_script_is_rendered_on_public_pages_for_visitors_only(): void
    {
        app(UpdateAnalyticsSettings::class)->handle(['enabled' => true, 'measurement_id' => 'G-ABC123XYZ9', 'exclude_authenticated' => true]);

        $this->get(route('home'))->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ9', false)
            ->assertSee("gtag('config', 'G-ABC123XYZ9')", false);

        $administrator = $this->administrator();
        $this->actingAs($administrator)->get(route('home'))->assertOk()->assertDontSee('googletagmanager.com', false);
        $this->actingAs($administrator)->get(route('dashboard'))->assertOk()->assertDontSee('googletagmanager.com', false);

        app(UpdateAnalyticsSettings::class)->handle(['enabled' => true, 'measurement_id' => 'G-ABC123XYZ9', 'exclude_authenticated' => false]);
        $this->actingAs($administrator)->get(route('home'))->assertOk()->assertSee('googletagmanager.com', false);
        $this->actingAs($administrator)->get(route('dashboard'))->assertOk()->assertDontSee('googletagmanager.com', false);

        auth()->logout();
        app(UpdateAnalyticsSettings::class)->handle(['enabled' => true, 'measurement_id' => 'GT-ABC1234', 'exclude_authenticated' => true]);
        $this->get(route('home'))->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=GT-ABC1234', false)
            ->assertSee("gtag('config', 'GT-ABC1234')", false);
    }

    public function test_script_is_not_rendered_when_disabled_or_invalid(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('googletagmanager.com', false);

        app(UpdateAnalyticsSettings::class)->handle(['enabled' => false, 'measurement_id' => 'G-ABC123XYZ9', 'exclude_authenticated' => true]);
        $this->get(route('home'))->assertOk()->assertDontSee('googletagmanager.com', false);

        ApplicationSetting::query()->where('key', AnalyticsSettings::KEY)->update([
            'value' => json_encode(['enabled' => true, 'measurement_id' => '"><script>alert(1)</script>', 'exclude_authenticated' => true]),
        ]);
        cache()->forget(AnalyticsSettings::CACHE_KEY);
        $this->get(route('home'))->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(RolePolicy::ADMINISTRATOR_ROLE, 'web'));

        return $user;
    }
}
