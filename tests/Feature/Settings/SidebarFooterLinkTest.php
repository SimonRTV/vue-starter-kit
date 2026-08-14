<?php

namespace Tests\Feature\Settings;

use App\Models\ApplicationSetting;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SidebarFooterLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $links = [
            [
                'title' => 'Aide',
                'url' => 'https://example.com/help',
            ],
        ];

        $this->get(route('sidebar-footer-links.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('sidebar-footer-links.update'), ['links' => $links])
            ->assertRedirect(route('login'));
    }

    public function test_non_administrators_cannot_view_or_change_sidebar_footer_links(): void
    {
        $user = User::factory()->create();
        $links = [
            [
                'title' => 'Aide',
                'url' => 'https://example.com/help',
            ],
        ];

        $this->actingAs($user)
            ->get(route('sidebar-footer-links.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('sidebar-footer-links.update'), ['links' => $links])
            ->assertForbidden();

        $this->assertSame(0, ApplicationSetting::query()->count());
    }

    public function test_the_default_links_are_shared_until_the_menu_is_customized(): void
    {
        $user = User::factory()->create();
        $administrator = $this->administrator();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'navigation.sidebarFooterLinks',
                    ApplicationSetting::DEFAULT_SIDEBAR_FOOTER_LINKS,
                ),
            );

        $this->actingAs($administrator)
            ->get(route('sidebar-footer-links.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/SidebarFooterLinks')
                ->where('links', ApplicationSetting::DEFAULT_SIDEBAR_FOOTER_LINKS),
            );
    }

    public function test_an_administrator_can_customize_and_order_sidebar_footer_links(): void
    {
        $administrator = $this->administrator();
        $links = [
            [
                'title' => '  Centre   d’aide  ',
                'url' => ' /pages ',
            ],
            [
                'title' => 'Statut',
                'url' => 'https://status.example.com',
            ],
        ];
        $normalizedLinks = [
            [
                'title' => 'Centre d’aide',
                'url' => '/pages',
            ],
            [
                'title' => 'Statut',
                'url' => 'https://status.example.com',
            ],
        ];

        $this->actingAs($administrator)
            ->put(route('sidebar-footer-links.update'), ['links' => $links])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sidebar-footer-links.edit'));

        $applicationSetting = ApplicationSetting::query()
            ->where('key', ApplicationSetting::SIDEBAR_FOOTER_LINKS)
            ->sole();

        $this->assertSame(
            $normalizedLinks,
            json_decode((string) $applicationSetting->value, true, flags: JSON_THROW_ON_ERROR),
        );

        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.sidebarFooterLinks', $normalizedLinks),
            );
    }

    public function test_an_administrator_can_remove_all_sidebar_footer_links(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->put(route('sidebar-footer-links.update'), ['links' => []])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sidebar-footer-links.edit'));

        $this->assertSame([], ApplicationSetting::sidebarFooterLinks());

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.sidebarFooterLinks', []),
            );
    }

    public function test_sidebar_footer_links_require_safe_destinations_and_reject_extra_parameters(): void
    {
        $administrator = $this->administrator();
        $links = [
            [
                'title' => 'Premier',
                'url' => 'javascript:alert(1)',
                'icon' => 'external-link',
            ],
            [
                'title' => 'Second',
                'url' => '//example.com/unsafe',
            ],
            [
                'title' => 'Troisième',
                'url' => 'settings/profile',
            ],
        ];

        $this->actingAs($administrator)
            ->from(route('sidebar-footer-links.edit'))
            ->put(route('sidebar-footer-links.update'), ['links' => $links])
            ->assertRedirect(route('sidebar-footer-links.edit'))
            ->assertSessionHasErrors([
                'links.0',
                'links.0.url',
                'links.1.url',
                'links.2.url',
            ]);

        $this->assertSame(0, ApplicationSetting::query()->count());
    }

    public function test_no_more_than_ten_sidebar_footer_links_can_be_saved(): void
    {
        $administrator = $this->administrator();
        $links = [];

        foreach (range(1, 11) as $position) {
            $links[] = [
                'title' => 'Lien '.$position,
                'url' => 'https://example.com/'.$position,
            ];
        }

        $this->actingAs($administrator)
            ->from(route('sidebar-footer-links.edit'))
            ->put(route('sidebar-footer-links.update'), ['links' => $links])
            ->assertRedirect(route('sidebar-footer-links.edit'))
            ->assertSessionHasErrors('links');

        $this->assertSame(0, ApplicationSetting::query()->count());
    }

    public function test_invalid_stored_menu_data_falls_back_to_the_default_links(): void
    {
        ApplicationSetting::factory()
            ->sidebarFooterLinks()
            ->create(['value' => '{invalid-json']);

        $this->assertSame(
            ApplicationSetting::DEFAULT_SIDEBAR_FOOTER_LINKS,
            ApplicationSetting::sidebarFooterLinks(),
        );
    }

    public function test_legacy_stored_icons_are_ignored(): void
    {
        ApplicationSetting::factory()
            ->create([
                'key' => ApplicationSetting::SIDEBAR_FOOTER_LINKS,
                'value' => json_encode(
                    [
                        [
                            'title' => 'Ancien lien',
                            'url' => 'https://example.com/legacy',
                            'icon' => 'book-open',
                        ],
                    ],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
            ]);

        $this->assertSame(
            [
                [
                    'title' => 'Ancien lien',
                    'url' => 'https://example.com/legacy',
                ],
            ],
            ApplicationSetting::sidebarFooterLinks(),
        );
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
