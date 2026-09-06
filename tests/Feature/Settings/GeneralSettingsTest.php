<?php

namespace Tests\Feature\Settings;

use App\Actions\ApplicationSettings\GeneralSettings;
use App\Models\ApplicationSetting;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_and_non_administrators_cannot_read_or_write_settings(): void
    {
        $this->get(route('general-settings.edit'))->assertRedirect(route('login'));
        $this->put(route('general-settings.update'), [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('general-settings.edit'))->assertForbidden();
        $this->put(route('general-settings.update'), ['name' => 'Changed'])->assertForbidden();
        $this->assertDatabaseCount('application_settings', 0);
    }

    public function test_settings_are_validated_persisted_and_cache_is_invalidated(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(RolePolicy::ADMINISTRATOR_ROLE, 'web'));
        $this->actingAs($user)->get(route('general-settings.edit'))->assertOk();
        $this->assertSame(config('app.name'), app(GeneralSettings::class)->get()['name']);
        $this->put(route('general-settings.update'), ['name' => '', 'contact_email' => 'invalid'])->assertSessionHasErrors(['name', 'contact_email']);
        $this->assertDatabaseCount('application_settings', 0);
        $this->put(route('general-settings.update'), ['name' => 'Atelier', 'tagline' => 'Bienvenue', 'contact_email' => 'hello@example.com', 'contact_phone' => '+41 00 000 00 00', 'secret' => 'ignore'])
            ->assertRedirect(route('general-settings.edit'));
        $this->assertSame('Atelier', app(GeneralSettings::class)->get()['name']);
        $this->assertStringNotContainsString('secret', ApplicationSetting::query()->sole()->value);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('name', 'Atelier')->where('application.tagline', 'Bienvenue'));
        $this->put(route('general-settings.update'), ['name' => 'Autre'])->assertRedirect();
        $this->assertSame('', app(GeneralSettings::class)->get()['contact_email']);
        $this->assertSame('Autre', app(GeneralSettings::class)->get()['name']);
    }
}
