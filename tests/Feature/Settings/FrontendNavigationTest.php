<?php

namespace Tests\Feature\Settings;

use App\Models\ApplicationSetting;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FrontendNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_must_authenticate_to_manage_frontend_navigation(): void
    {
        $this->get(route('frontend-navigation.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('frontend-navigation.update'), ['items' => []])
            ->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_manage_frontend_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('frontend-navigation.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('frontend-navigation.update'), ['items' => []])
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_the_frontend_navigation_builder(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('frontend-navigation.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/FrontendNavigation')
                ->where('items', ApplicationSetting::DEFAULT_FRONTEND_NAVIGATION),
            );
    }

    public function test_an_administrator_can_customize_nested_frontend_navigation(): void
    {
        $items = [
            [
                'type' => 'group',
                'label' => '  Découvrir   ',
                'url' => null,
                'children' => [
                    [
                        'label' => ' Notre   équipe ',
                        'url' => ' /equipe ',
                        'description' => ' Rencontrez   notre équipe. ',
                    ],
                    [
                        'label' => 'Actualités',
                        'url' => 'https://example.com/news',
                        'description' => '',
                    ],
                ],
            ],
            [
                'type' => 'link',
                'label' => ' Contact ',
                'url' => ' #contact ',
                'children' => [],
            ],
        ];
        $normalizedItems = [
            [
                'type' => 'group',
                'label' => 'Découvrir',
                'url' => null,
                'children' => [
                    [
                        'label' => 'Notre équipe',
                        'url' => '/equipe',
                        'description' => 'Rencontrez notre équipe.',
                    ],
                    [
                        'label' => 'Actualités',
                        'url' => 'https://example.com/news',
                        'description' => '',
                    ],
                ],
            ],
            [
                'type' => 'link',
                'label' => 'Contact',
                'url' => '#contact',
                'children' => [],
            ],
        ];

        $this->actingAs($this->administrator())
            ->put(route('frontend-navigation.update'), ['items' => $items])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('frontend-navigation.edit'));

        $setting = ApplicationSetting::query()
            ->where('key', ApplicationSetting::FRONTEND_NAVIGATION)
            ->sole();

        $this->assertSame(
            $normalizedItems,
            json_decode((string) $setting->value, true, flags: JSON_THROW_ON_ERROR),
        );

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.frontend', $normalizedItems),
            );
    }

    public function test_an_administrator_can_remove_all_frontend_navigation_items(): void
    {
        $this->actingAs($this->administrator())
            ->put(route('frontend-navigation.update'), ['items' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame([], ApplicationSetting::frontendNavigation());
    }

    public function test_frontend_navigation_rejects_unsafe_destinations_and_invalid_structures(): void
    {
        $items = [
            [
                'type' => 'link',
                'label' => 'Unsafe',
                'url' => 'javascript:alert(1)',
                'children' => [],
            ],
            [
                'type' => 'group',
                'label' => 'Empty group',
                'url' => null,
                'children' => [],
            ],
            [
                'type' => 'link',
                'label' => 'Nested link',
                'url' => '/about',
                'children' => [
                    [
                        'label' => 'Unexpected',
                        'url' => '/unexpected',
                        'description' => '',
                    ],
                ],
            ],
        ];

        $this->actingAs($this->administrator())
            ->from(route('frontend-navigation.edit'))
            ->put(route('frontend-navigation.update'), ['items' => $items])
            ->assertRedirect(route('frontend-navigation.edit'))
            ->assertSessionHasErrors([
                'items.0.url',
                'items.1.children',
                'items.2.children',
            ]);

        $this->assertSame(0, ApplicationSetting::query()->count());
    }

    public function test_frontend_navigation_rejects_protocol_relative_urls_and_extra_fields(): void
    {
        $items = [
            [
                'type' => 'link',
                'label' => 'Unsafe',
                'url' => '//example.com',
                'children' => [],
                'target' => '_blank',
            ],
        ];

        $this->actingAs($this->administrator())
            ->from(route('frontend-navigation.edit'))
            ->put(route('frontend-navigation.update'), ['items' => $items])
            ->assertRedirect(route('frontend-navigation.edit'))
            ->assertSessionHasErrors(['items.0', 'items.0.url']);
    }

    public function test_frontend_navigation_enforces_item_and_child_limits(): void
    {
        $administrator = $this->administrator();
        $items = [];

        foreach (range(1, 11) as $position) {
            $items[] = [
                'type' => 'link',
                'label' => 'Lien '.$position,
                'url' => '#section-'.$position,
                'children' => [],
            ];
        }

        $this->actingAs($administrator)
            ->from(route('frontend-navigation.edit'))
            ->put(route('frontend-navigation.update'), ['items' => $items])
            ->assertSessionHasErrors('items');

        $children = [];

        foreach (range(1, 9) as $position) {
            $children[] = [
                'label' => 'Sous-lien '.$position,
                'url' => '#child-'.$position,
                'description' => '',
            ];
        }

        $this->actingAs($administrator)
            ->from(route('frontend-navigation.edit'))
            ->put(route('frontend-navigation.update'), [
                'items' => [[
                    'type' => 'group',
                    'label' => 'Trop grand',
                    'url' => null,
                    'children' => $children,
                ]],
            ])
            ->assertSessionHasErrors('items.0.children');
    }

    public function test_invalid_stored_frontend_navigation_falls_back_to_defaults(): void
    {
        ApplicationSetting::factory()
            ->frontendNavigation()
            ->create(['value' => '{invalid-json']);

        $this->assertSame(
            ApplicationSetting::DEFAULT_FRONTEND_NAVIGATION,
            ApplicationSetting::frontendNavigation(),
        );
    }

    public function test_unsafe_stored_frontend_navigation_falls_back_to_defaults(): void
    {
        ApplicationSetting::factory()
            ->frontendNavigation()
            ->create([
                'value' => json_encode([
                    [
                        'type' => 'link',
                        'label' => 'Unsafe',
                        'url' => 'javascript:alert(1)',
                        'children' => [],
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $this->assertSame(
            ApplicationSetting::DEFAULT_FRONTEND_NAVIGATION,
            ApplicationSetting::frontendNavigation(),
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
