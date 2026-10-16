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
            ->where('settings', $this->settings(['enabled' => false, 'measurement_id' => ''])));

        $this->put(route('analytics-settings.update'), ['enabled' => '1'])->assertSessionHasErrors('measurement_id');
        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => 'UA-12345-1'])->assertSessionHasErrors('measurement_id');
        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => 'GTM-ABC1234'])->assertSessionHasErrors('measurement_id');
        $this->put(route('analytics-settings.update'), ['measurement_id' => "G-1');alert('x"])->assertSessionHasErrors('measurement_id');
        $this->put(route('analytics-settings.update'), ['privacy_policy_url' => 'javascript:alert(1)'])->assertSessionHasErrors('privacy_policy_url');
        $this->put(route('analytics-settings.update'), ['privacy_policy_url' => '//evil.example'])->assertSessionHasErrors('privacy_policy_url');
        $this->assertDatabaseCount('application_settings', 0);

        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => ' g-abc123xyz9 ', 'privacy_policy_url' => ' /content/confidentialite ', 'secret' => 'ignore'])
            ->assertRedirect(route('analytics-settings.edit'));

        $this->assertSame(
            $this->settings(['exclude_authenticated' => false, 'require_consent' => false, 'privacy_policy_url' => '/content/confidentialite']),
            app(AnalyticsSettings::class)->get(),
        );
        $this->assertStringNotContainsString('secret', ApplicationSetting::query()->sole()->value);

        $this->put(route('analytics-settings.update'), ['enabled' => '1', 'measurement_id' => 'gt-abc1234', 'exclude_authenticated' => '1', 'require_consent' => '1', 'privacy_policy_url' => 'https://example.com/privacy'])->assertRedirect();
        $this->assertSame(
            $this->settings(['measurement_id' => 'GT-ABC1234', 'privacy_policy_url' => 'https://example.com/privacy']),
            app(AnalyticsSettings::class)->get(),
        );

        $this->put(route('analytics-settings.update'), ['measurement_id' => ''])->assertRedirect();
        $this->assertSame(
            ['enabled' => false, 'measurement_id' => '', 'exclude_authenticated' => false, 'require_consent' => false, 'privacy_policy_url' => ''],
            app(AnalyticsSettings::class)->get(),
        );
    }

    public function test_configuration_is_shared_on_public_pages_for_visitors_only(): void
    {
        app(UpdateAnalyticsSettings::class)->handle($this->settings(['privacy_policy_url' => '/content/confidentialite']));

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics', ['measurementId' => 'G-ABC123XYZ9', 'requiresConsent' => true, 'privacyPolicyUrl' => '/content/confidentialite']));

        $administrator = $this->administrator();
        $this->actingAs($administrator)->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.measurementId', null)->where('analytics.requiresConsent', false));
        $this->actingAs($administrator)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.measurementId', null));

        app(UpdateAnalyticsSettings::class)->handle($this->settings(['exclude_authenticated' => false, 'require_consent' => false]));
        $this->actingAs($administrator)->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics', ['measurementId' => 'G-ABC123XYZ9', 'requiresConsent' => false, 'privacyPolicyUrl' => null]));
        $this->actingAs($administrator)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.measurementId', null));
    }

    public function test_tracking_script_is_never_rendered_by_the_server(): void
    {
        app(UpdateAnalyticsSettings::class)->handle($this->settings(['require_consent' => false]));

        $this->get(route('home'))->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_configuration_is_not_shared_when_disabled_or_invalid(): void
    {
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics', ['measurementId' => null, 'requiresConsent' => false, 'privacyPolicyUrl' => null]));

        app(UpdateAnalyticsSettings::class)->handle($this->settings(['enabled' => false]));
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('analytics.measurementId', null));

        ApplicationSetting::query()->where('key', AnalyticsSettings::KEY)->update([
            'value' => json_encode(['enabled' => true, 'measurement_id' => '"><script>alert(1)</script>', 'privacy_policy_url' => 'javascript:alert(1)']),
        ]);
        cache()->forget(AnalyticsSettings::CACHE_KEY);
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics', ['measurementId' => null, 'requiresConsent' => false, 'privacyPolicyUrl' => null]));
    }

    /**
     * @param  array<string, bool|string>  $overrides
     * @return array{enabled: bool, measurement_id: string, exclude_authenticated: bool, require_consent: bool, privacy_policy_url: string}
     */
    private function settings(array $overrides = []): array
    {
        /** @var array{enabled: bool, measurement_id: string, exclude_authenticated: bool, require_consent: bool, privacy_policy_url: string} */
        return [
            'enabled' => true,
            'measurement_id' => 'G-ABC123XYZ9',
            'exclude_authenticated' => true,
            'require_consent' => true,
            'privacy_policy_url' => '',
            ...$overrides,
        ];
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(RolePolicy::ADMINISTRATOR_ROLE, 'web'));

        return $user;
    }
}
