<?php

namespace Tests\Feature\Settings;

use App\Models\ApplicationSetting;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationLogoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('application-logo.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrators_cannot_view_or_change_the_application_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('application-logo.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('application-logo.update'), [
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('application-logo.destroy'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('application-logo.full.update'), [
                'full_logo' => UploadedFile::fake()->image('full-logo.png'),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('application-logo.full.destroy'))
            ->assertForbidden();

        $this->assertSame(0, ApplicationSetting::query()->count());
        Storage::disk('public')->assertDirectoryEmpty('application/logos');
    }

    public function test_application_settings_navigation_ability_is_shared_only_for_administrators(): void
    {
        $user = User::factory()->create();
        $administrator = $this->administrator();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageApplicationSettings', false),
            );

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageApplicationSettings', true),
            );
    }

    public function test_administrators_can_view_the_application_logo_settings(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get(route('application-logo.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/ApplicationLogo')
                ->where('iconUrl', null)
                ->where('fullLogoUrl', null)
                ->where('branding.iconUrl', null)
                ->where('branding.fullLogoUrl', null),
            );
    }

    public function test_an_administrator_can_upload_an_application_wide_logo(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('application-logo.update'), [
                'logo' => UploadedFile::fake()->image('logo.png', 600, 300),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('application-logo.edit'));

        $applicationSetting = ApplicationSetting::query()->sole();

        $this->assertSame(ApplicationSetting::ICON_PATH, $applicationSetting->key);
        $this->assertNotNull($applicationSetting->value);
        Storage::disk('public')->assertExists($applicationSetting->value);

        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'branding.iconUrl',
                    Storage::disk('public')->url($applicationSetting->value),
                )
                ->where('branding.fullLogoUrl', null)
                ->where('auth.can.manageApplicationSettings', false),
            );
    }

    public function test_application_logo_upload_requires_a_valid_image(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->from(route('application-logo.edit'))
            ->post(route('application-logo.update'), [
                'logo' => UploadedFile::fake()->create(
                    'logo.pdf',
                    100,
                    'application/pdf',
                ),
            ])
            ->assertRedirect(route('application-logo.edit'))
            ->assertSessionHasErrors('logo');

        $this->assertSame(0, ApplicationSetting::query()->count());
    }

    public function test_replacing_the_application_logo_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();
        $oldLogoPath = 'application/logos/old-logo.png';
        $applicationSetting = ApplicationSetting::factory()
            ->icon($oldLogoPath)
            ->create();
        Storage::disk('public')->put($oldLogoPath, 'old logo');

        $this->actingAs($administrator)
            ->post(route('application-logo.update'), [
                'logo' => UploadedFile::fake()->image('new-logo.webp'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('application-logo.edit'));

        $applicationSetting->refresh();

        $this->assertNotSame($oldLogoPath, $applicationSetting->value);
        Storage::disk('public')->assertMissing($oldLogoPath);
        Storage::disk('public')->assertExists($applicationSetting->value);
    }

    public function test_an_administrator_can_restore_the_default_logo(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();
        $logoPath = 'application/logos/custom-logo.png';
        $applicationSetting = ApplicationSetting::factory()
            ->icon($logoPath)
            ->create();
        Storage::disk('public')->put($logoPath, 'custom logo');

        $this->actingAs($administrator)
            ->delete(route('application-logo.destroy'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('application-logo.edit'));

        $this->assertNull($applicationSetting->refresh()->value);
        Storage::disk('public')->assertMissing($logoPath);
    }

    public function test_an_administrator_can_upload_a_full_application_logo(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('application-logo.full.update'), [
                'full_logo' => UploadedFile::fake()->image('full-logo.png', 1200, 400),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('application-logo.edit'));

        $applicationSetting = ApplicationSetting::query()->sole();

        $this->assertSame(ApplicationSetting::FULL_LOGO_PATH, $applicationSetting->key);
        $this->assertNotNull($applicationSetting->value);
        Storage::disk('public')->assertExists($applicationSetting->value);

        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.iconUrl', null)
                ->where(
                    'branding.fullLogoUrl',
                    Storage::disk('public')->url($applicationSetting->value),
                ),
            );
    }

    public function test_full_application_logo_upload_requires_a_valid_image(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->from(route('application-logo.edit'))
            ->post(route('application-logo.full.update'), [
                'full_logo' => UploadedFile::fake()->create(
                    'full-logo.pdf',
                    100,
                    'application/pdf',
                ),
            ])
            ->assertRedirect(route('application-logo.edit'))
            ->assertSessionHasErrors('full_logo');

        $this->assertSame(0, ApplicationSetting::query()->count());
    }

    public function test_replacing_the_full_logo_preserves_the_app_icon(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();
        $iconPath = 'application/logos/icon.png';
        $oldFullLogoPath = 'application/logos/old-full-logo.png';
        $iconSetting = ApplicationSetting::factory()->icon($iconPath)->create();
        $fullLogoSetting = ApplicationSetting::factory()
            ->fullLogo($oldFullLogoPath)
            ->create();
        Storage::disk('public')->put($iconPath, 'icon');
        Storage::disk('public')->put($oldFullLogoPath, 'old full logo');

        $this->actingAs($administrator)
            ->post(route('application-logo.full.update'), [
                'full_logo' => UploadedFile::fake()->image('new-full-logo.webp', 1200, 400),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('application-logo.edit'));

        $fullLogoSetting->refresh();

        $this->assertSame($iconPath, $iconSetting->refresh()->value);
        $this->assertNotSame($oldFullLogoPath, $fullLogoSetting->value);
        Storage::disk('public')->assertExists($iconPath);
        Storage::disk('public')->assertMissing($oldFullLogoPath);
        Storage::disk('public')->assertExists($fullLogoSetting->value);
    }

    public function test_removing_the_full_logo_preserves_the_app_icon(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();
        $iconPath = 'application/logos/icon.png';
        $fullLogoPath = 'application/logos/full-logo.png';
        $iconSetting = ApplicationSetting::factory()->icon($iconPath)->create();
        $fullLogoSetting = ApplicationSetting::factory()
            ->fullLogo($fullLogoPath)
            ->create();
        Storage::disk('public')->put($iconPath, 'icon');
        Storage::disk('public')->put($fullLogoPath, 'full logo');

        $this->actingAs($administrator)
            ->delete(route('application-logo.full.destroy'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('application-logo.edit'));

        $this->assertSame($iconPath, $iconSetting->refresh()->value);
        $this->assertNull($fullLogoSetting->refresh()->value);
        Storage::disk('public')->assertExists($iconPath);
        Storage::disk('public')->assertMissing($fullLogoPath);
    }

    private function administrator(): User
    {
        $administratorRole = Role::query()->firstOrCreate([
            'name' => RolePolicy::ADMINISTRATOR_ROLE,
            'guard_name' => 'web',
        ]);
        $administrator = User::factory()->create();
        $administrator->assignRole($administratorRole);

        return $administrator;
    }
}
