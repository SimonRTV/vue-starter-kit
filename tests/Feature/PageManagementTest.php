<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Policies\PagePolicy;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PageManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('pages.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_view_permission_cannot_access_page_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('pages.index'))
            ->assertForbidden();
    }

    public function test_page_navigation_ability_is_shared_from_the_server(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.managePages', false),
            );

        $this->grantPermissions($user, [PagePolicy::VIEW]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.managePages', true),
            );
    }

    public function test_authenticated_users_can_view_the_page_list(): void
    {
        $user = $this->authorizedUser();
        $olderPage = Page::factory()->draft()->create([
            'title' => 'Older page',
            'updated_at' => now()->subDay(),
        ]);
        $newerPage = Page::factory()->published()->create([
            'title' => 'Newer page',
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('pages.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pages/Index')
                ->has('pages.data', 2)
                ->where('pages.data.0.id', $newerPage->id)
                ->where('pages.data.0.status', 'published')
                ->where('pages.data.1.id', $olderPage->id)
                ->where('pages.total', 2)
                ->where('pages.current_page', 1)
                ->where('filters.search', null)
                ->where('filters.status', null)
                ->where('filters.sort', 'updated_at')
                ->where('filters.direction', 'desc')
                ->where('filters.per_page', 10)
                ->missing('pages.data.0.body'),
            );
    }

    public function test_page_list_can_be_searched_filtered_and_sorted_on_the_server(): void
    {
        $user = $this->authorizedUser();
        $matchingPage = Page::factory()->published()->create([
            'title' => 'Alpha handbook',
            'slug' => 'company-handbook',
        ]);
        Page::factory()->draft()->create([
            'title' => 'Alpha draft',
            'slug' => 'alpha-draft',
        ]);
        Page::factory()->published()->create([
            'title' => 'Beta handbook',
            'slug' => 'beta-handbook',
        ]);

        $this->actingAs($user)
            ->get(route('pages.index', [
                'search' => '  Alpha   handbook  ',
                'status' => 'published',
                'sort' => 'title',
                'direction' => 'asc',
                'per_page' => 25,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pages/Index')
                ->has('pages.data', 1)
                ->where('pages.data.0.id', $matchingPage->id)
                ->where('pages.total', 1)
                ->where('filters.search', 'Alpha handbook')
                ->where('filters.status', 'published')
                ->where('filters.sort', 'title')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 25),
            );
    }

    public function test_page_list_is_paginated_by_the_server(): void
    {
        $user = $this->authorizedUser();

        Page::factory()
            ->count(12)
            ->sequence(fn (Sequence $sequence): array => [
                'title' => sprintf('Page %02d', $sequence->index),
                'slug' => sprintf('page-%02d', $sequence->index),
            ])
            ->create();

        $this->actingAs($user)
            ->get(route('pages.index', [
                'sort' => 'title',
                'direction' => 'asc',
                'per_page' => 10,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pages/Index')
                ->has('pages.data', 2)
                ->where('pages.data.0.title', 'Page 10')
                ->where('pages.data.1.title', 'Page 11')
                ->where('pages.current_page', 2)
                ->where('pages.last_page', 2)
                ->where('pages.per_page', 10)
                ->where('pages.total', 12),
            );
    }

    public function test_page_list_rejects_unsupported_query_parameters(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->getJson(route('pages.index', [
                'status' => 'archived',
                'sort' => 'body',
                'direction' => 'sideways',
                'per_page' => 500,
                'page' => 0,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'sort',
                'direction',
                'per_page',
                'page',
            ]);
    }

    public function test_page_list_redirects_an_out_of_range_page_to_the_last_page(): void
    {
        $user = $this->authorizedUser();
        Page::factory()->published()->count(12)->create();

        $this->actingAs($user)
            ->get(route('pages.index', [
                'status' => 'published',
                'page' => 99,
            ]))
            ->assertRedirect(route('pages.index', [
                'status' => 'published',
                'page' => 2,
            ]));
    }

    public function test_authenticated_users_can_view_the_create_page(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->get(route('pages.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('pages/Create'));
    }

    public function test_authenticated_users_can_create_a_published_page(): void
    {
        $user = $this->authorizedUser();

        $response = $this->actingAs($user)->post(route('pages.store'), [
            'title' => 'Company history',
            'slug' => 'company-history',
            'excerpt' => 'A short company history.',
            'body' => 'The company was founded with a simple idea.',
            'is_published' => true,
        ]);

        $page = Page::query()->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pages.show', $page));

        $this->assertSame('Company history', $page->title);
        $this->assertSame('company-history', $page->slug);
        $this->assertTrue($page->is_published);
        $this->assertNotNull($page->published_at);
    }

    public function test_page_creation_validates_required_and_boolean_fields(): void
    {
        $user = $this->authorizedUser();

        $response = $this
            ->actingAs($user)
            ->from(route('pages.create'))
            ->post(route('pages.store'), [
                'title' => '',
                'slug' => '',
                'is_published' => 'sometimes',
            ]);

        $response
            ->assertRedirect(route('pages.create'))
            ->assertSessionHasErrors(['title', 'slug', 'is_published']);
    }

    /**
     * @param  list<string>  $existingSlugs
     */
    #[DataProvider('pageSlugCollisions')]
    public function test_page_creation_uses_the_next_available_slug(array $existingSlugs, string $requestedSlug, string $expectedSlug): void
    {
        $user = $this->authorizedUser();
        $originalPages = [];

        foreach ($existingSlugs as $index => $existingSlug) {
            $factory = $index % 2 === 0 ? Page::factory()->published() : Page::factory()->draft();
            $originalPages[] = $factory->create(['slug' => $existingSlug, 'title' => 'Original page'])->refresh();
        }

        $response = $this->actingAs($user)->post(route('pages.store'), [
            'title' => 'New page',
            'slug' => $requestedSlug,
            'body' => 'New content.',
            'is_published' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $createdPage = Page::query()->where('title', 'New page')->sole();
        $response->assertRedirect(route('pages.show', $createdPage));
        $this->assertSame($expectedSlug, $createdPage->slug);
        $this->assertLessThanOrEqual(255, Str::length($createdPage->slug));
        $this->assertSame('New content.', $createdPage->body);
        $this->assertFalse($createdPage->is_published);
        $this->assertSame(count($existingSlugs) + 1, Page::query()->count());

        foreach ($originalPages as $originalPage) {
            $this->assertSame($originalPage->getRawOriginal(), $originalPage->fresh()->getRawOriginal());
        }
    }

    /**
     * @return array<string, array{list<string>, string, string}>
     */
    public static function pageSlugCollisions(): array
    {
        return [
            'available custom slug' => [['other-page'], 'custom-address', 'custom-address'],
            'first duplicate' => [['about'], 'about', 'about-2'],
            'several duplicates' => [['about', 'about-2', 'about-3'], 'about', 'about-4'],
            'first available suffix' => [['about', 'about-3'], 'about', 'about-2'],
            'existing numeric ending' => [['report-2026'], 'report-2026', 'report-2026-2'],
            'maximum length with an occupied suffix' => [[Str::repeat('a', 255), Str::repeat('a', 253).'-2'], Str::repeat('a', 255), Str::repeat('a', 253).'-3'],
            'multibyte maximum length' => [[Str::repeat('é', 255)], Str::repeat('é', 255), Str::repeat('é', 253).'-2'],
        ];
    }

    public function test_authenticated_users_can_view_a_page(): void
    {
        $user = $this->authorizedUser();
        $managedPage = Page::factory()->published()->create([
            'body' => 'Full page content.',
        ]);

        $this->actingAs($user)
            ->get(route('pages.show', $managedPage))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pages/Show')
                ->where('page.id', $managedPage->id)
                ->where('page.body', 'Full page content.')
                ->where('page.status', 'published')
                ->where('canUpdate', true)
                ->where('canDelete', true),
            );
    }

    public function test_page_view_exposes_only_the_readers_management_abilities(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, [PagePolicy::VIEW]);
        $managedPage = Page::factory()->draft()->create();

        $this->actingAs($user)
            ->get(route('pages.show', $managedPage))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pages/Show')
                ->where('page.id', $managedPage->id)
                ->where('page.status', 'draft')
                ->where('canUpdate', false)
                ->where('canDelete', false)
                ->where('attachments', []),
            );
    }

    public function test_authenticated_users_can_publish_and_unpublish_a_page(): void
    {
        $user = $this->authorizedUser();
        $managedPage = Page::factory()->draft()->create([
            'title' => 'Launch',
            'slug' => 'launch',
        ]);

        $publishResponse = $this
            ->actingAs($user)
            ->patch(route('pages.update', $managedPage), [
                'title' => 'Launch page',
                'slug' => 'launch',
                'excerpt' => null,
                'body' => 'Ready to launch.',
                'is_published' => true,
            ]);

        $publishResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pages.edit', $managedPage));

        $managedPage->refresh();

        $this->assertSame('Launch page', $managedPage->title);
        $this->assertTrue($managedPage->is_published);
        $this->assertNotNull($managedPage->published_at);

        $unpublishResponse = $this
            ->actingAs($user)
            ->patch(route('pages.update', $managedPage), [
                'title' => 'Launch page',
                'slug' => 'launch',
                'excerpt' => null,
                'body' => 'Back in review.',
                'is_published' => false,
            ]);

        $unpublishResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pages.edit', $managedPage));

        $managedPage->refresh();

        $this->assertFalse($managedPage->is_published);
        $this->assertNull($managedPage->published_at);
    }

    public function test_saving_in_the_editor_returns_the_updated_content_without_changing_draft_status(): void
    {
        $user = $this->authorizedUser();
        $managedPage = Page::factory()->draft()->create();

        $this->actingAs($user)
            ->patch(route('pages.update', $managedPage), [
                'title' => 'Updated title',
                'slug' => 'updated-page',
                'excerpt' => 'A short introduction.',
                'body' => '<h2>Introduction</h2><p><strong>Updated content</strong></p>',
                'body_format' => 'html',
                'is_published' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pages.edit', $managedPage));

        $this->get(route('pages.edit', $managedPage))
            ->assertInertia(fn (Assert $response) => $response
                ->component('pages/Edit')
                ->where('page.title', 'Updated title')
                ->where('page.slug', 'updated-page')
                ->where('page.excerpt', 'A short introduction.')
                ->where('page.body_html', '<h2>Introduction</h2><p><strong>Updated content</strong></p>')
                ->where('page.is_published', false)
                ->where('page.published_at', null),
            );
    }

    public function test_invalid_editor_changes_return_to_edit_and_preserve_saved_content(): void
    {
        $user = $this->authorizedUser();
        $managedPage = Page::factory()->draft()->create(['title' => 'Saved title']);
        $originalBody = $managedPage->body;
        Page::factory()->create(['slug' => 'taken-slug']);

        $this->actingAs($user)
            ->from(route('pages.edit', $managedPage))
            ->patch(route('pages.update', $managedPage), [
                'title' => '',
                'slug' => 'taken-slug',
                'body' => '<p>Unsaved content</p>',
                'body_format' => 'html',
                'is_published' => false,
            ])
            ->assertRedirect(route('pages.edit', $managedPage))
            ->assertSessionHasErrors(['title', 'slug']);

        $managedPage->refresh();
        $this->assertSame('Saved title', $managedPage->title);
        $this->assertSame($originalBody, $managedPage->body);
    }

    public function test_authenticated_users_can_delete_a_page(): void
    {
        $user = $this->authorizedUser();
        $managedPage = Page::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('pages.destroy', $managedPage));

        $response->assertRedirect(route('pages.index'));
        $this->assertModelMissing($managedPage);
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, PagePolicy::PERMISSIONS);

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grantPermissions(User $user, array $permissions): void
    {
        $permissionModels = collect($permissions)
            ->map(fn (string $permission): Permission => Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]));

        $user->givePermissionTo($permissionModels);
    }
}
