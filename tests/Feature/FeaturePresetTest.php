<?php

namespace Tests\Feature;

use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Page;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeaturePresetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_business_preset_blocks_pages_and_preserves_data(): void
    {
        config(['starter.features' => config('starter.presets.business')]);
        app(SyncPolicyPermissions::class)->handle();
        $user = User::factory()->create();
        $user->assignRole(RolePolicy::ADMINISTRATOR_ROLE);
        $page = Page::factory()->published()->create();
        $this->actingAs($user);
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get(route('pages.index'))->assertNotFound();
        $this->post(route('pages.store'), [])->assertNotFound();
        $this->delete(route('pages.destroy', $page))->assertNotFound();
        $this->get(route('content.show', $page->slug))->assertNotFound();
        $this->get(route('frontend-navigation.edit'))->assertNotFound();
        $this->put(route('frontend-navigation.update'), [])->assertNotFound();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.managePages', false)->where('navigation.frontend', []));
        $this->assertModelExists($page);
    }

    public function test_portal_keeps_page_management_private_and_website_restores_public_content(): void
    {
        app(SyncPolicyPermissions::class)->handle();
        $user = User::factory()->create();
        $user->assignRole(RolePolicy::ADMINISTRATOR_ROLE);
        $page = Page::factory()->published()->create();
        config(['starter.features' => config('starter.presets.portal')]);
        $this->actingAs($user)->get(route('pages.index'))->assertOk();
        $this->get(route('content.show', $page->slug))->assertNotFound();
        config(['starter.features' => config('starter.presets.website')]);
        $this->get(route('content.show', $page->slug))->assertOk();
    }

    public function test_enabled_pages_still_require_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('pages.index'))->assertForbidden();
    }
}
