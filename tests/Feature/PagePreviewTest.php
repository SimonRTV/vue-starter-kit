<?php

namespace Tests\Feature;

use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageFieldSet;
use App\Models\PageTemplate;
use App\Models\User;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PagePreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_preview_requires_a_verified_user_with_page_view_permission(): void
    {
        $page = Page::factory()->draft()->create();
        $this->get(route('content.preview', $page))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('content.preview', $page))->assertForbidden();
        $this->actingAs(User::factory()->unverified()->create())->get(route('content.preview', $page))->assertRedirect(route('verification.notice'));

        $reader = User::factory()->create();
        $reader->givePermissionTo(PagePolicy::VIEW);
        $this->actingAs($reader)->get(route('content.preview', $page))->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('content/Show')
                ->where('preview.id', $page->id)
                ->where('preview.canUpdate', false)
                ->where('preview.isPublished', false)
                ->where('page.published_at', null));
        $this->get(route('content.show', ['page' => $page->slug, 'preview' => true]))->assertNotFound();
    }

    public function test_preview_is_private_not_indexable_and_uses_public_appearance(): void
    {
        $page = Page::factory()->draft()->create(['seo' => ['robots_index' => 'index', 'robots_follow' => 'follow']]);
        $user = $this->editor();
        $user->update(['appearance' => 'dark', 'admin_theme' => 'ocean']);

        $response = $this->actingAs($user)->get(route('content.preview', $page))->assertOk()
            ->assertSee('name="robots" content="none"', false)
            ->assertViewHas('appearanceSurface', 'frontend')
            ->assertViewHas('adminTheme', 'neutral');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->get(route('sitemap'))->assertDontSee(route('content.preview', $page))->assertDontSee(route('content.show', $page->slug));
    }

    public function test_preview_uses_sanitized_saved_content_and_only_public_attachments(): void
    {
        $page = Page::factory()->draft()->create(['body_format' => 'html', 'body' => '<p>Preview body</p><script>alert(1)</script>']);
        $public = Media::factory()->publiclyShared()->create();
        $private = Media::factory()->create();
        $page->attachments()->attach([$public->id, $private->id]);
        $this->actingAs($this->editor())->get(route('content.preview', $page))->assertInertia(fn (Assert $response) => $response
            ->where('page.body_html', '<p>Preview body</p>')
            ->has('page.attachments', 1)
            ->where('page.attachments.0.id', $public->id));
        config(['starter.features.media' => false]);
        $this->get(route('content.preview', $page))->assertInertia(fn (Assert $response) => $response->where('page.attachments', []));
        $this->assertFalse($page->refresh()->is_published);
    }

    public function test_preview_respects_feature_flags_and_missing_pages(): void
    {
        $page = Page::factory()->draft()->create();
        $this->actingAs($this->editor())->get(route('content.preview', 999999))->assertNotFound();
        foreach (['pages', 'public_site'] as $feature) {
            config(['starter.features.'.$feature => false]);
            $this->get(route('content.preview', $page))->assertNotFound();
            config(['starter.features.'.$feature => true]);
        }
    }

    public function test_saving_and_previewing_a_new_page_never_publishes_it(): void
    {
        $response = $this->actingAs($this->editor())->post(route('pages.store'), $this->attributes('preview'))->assertSessionHasNoErrors();
        $page = Page::query()->sole();
        $this->assertFalse($page->is_published);
        $this->assertNull($page->published_at);
        $this->assertSame('<p>Private body</p>', $page->body);
        $response->assertRedirect(route('content.preview', $page));
        $this->get(route('content.show', $page->slug))->assertNotFound();
        $this->get(route('content.preview', $page))->assertInertia(fn (Assert $response) => $response->where('page.title', 'Private title'));
    }

    public function test_saved_draft_edits_leave_the_published_content_and_url_unchanged_until_publish(): void
    {
        $page = Page::factory()->published()->create(['title' => 'Live title', 'slug' => 'live-slug', 'body' => 'Live body']);
        $publishedAt = $page->published_at->toISOString();
        $this->actingAs($this->editor())->put(route('pages.update', $page), $this->attributes('preview'))
            ->assertSessionHasNoErrors()->assertRedirect(route('content.preview', $page));

        $this->assertSame('Live title', $page->refresh()->title);
        $this->assertSame('Live body', $page->body);
        $this->assertSame($publishedAt, $page->published_at->toISOString());
        $this->assertTrue($page->is_published);
        $this->get(route('content.show', 'live-slug'))->assertInertia(fn (Assert $response) => $response
            ->where('page.title', 'Live title')->where('page.body', 'Live body')->where('preview', null)->missing('page.draft'));
        $this->get(route('content.show', 'private-slug'))->assertNotFound();
        $this->get(route('content.preview', $page))->assertInertia(fn (Assert $response) => $response
            ->where('page.title', 'Private title')->where('page.body_html', '<p>Private body</p>')->where('preview.isPublished', true));
        $this->get(route('pages.edit', $page))->assertInertia(fn (Assert $response) => $response
            ->where('page.title', 'Private title')->where('page.slug', 'private-slug')->where('page.public_slug', 'live-slug')->where('page.has_draft', true));

        $this->put(route('pages.update', $page), $this->attributes('publish'))->assertSessionHasNoErrors()->assertRedirect(route('pages.edit', $page));
        $this->assertNull($page->refresh()->draft);
        $this->get(route('content.show', 'private-slug'))->assertInertia(fn (Assert $response) => $response->where('page.title', 'Private title'));
        $this->get(route('content.show', 'live-slug'))->assertNotFound();
    }

    public function test_regular_save_keeps_edits_private_and_unpublish_removes_the_public_page(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'live-slug']);
        $this->actingAs($this->editor())->put(route('pages.update', $page), $this->attributes('save'))->assertSessionHasNoErrors()->assertRedirect(route('pages.edit', $page));
        $this->assertSame('Private title', $page->refresh()->draft['title']);
        $this->assertTrue($page->is_published);
        $this->put(route('pages.update', $page), $this->attributes('unpublish'))->assertSessionHasNoErrors();
        $this->assertFalse($page->refresh()->is_published);
        $this->assertNull($page->draft);
        $this->get(route('content.show', 'live-slug'))->assertNotFound();
        $this->get(route('content.show', 'private-slug'))->assertNotFound();
    }

    public function test_saving_an_existing_unpublished_page_stays_a_draft_until_explicitly_published(): void
    {
        $page = Page::factory()->draft()->create();
        $this->actingAs($this->editor())->put(route('pages.update', $page), $this->attributes('save'))->assertSessionHasNoErrors();
        $this->assertFalse($page->refresh()->is_published);
        $this->assertNull($page->published_at);
        $this->assertSame('Private title', $page->title);
        $this->put(route('pages.update', $page), $this->attributes('publish'))->assertSessionHasNoErrors();
        $this->assertTrue($page->refresh()->is_published);
        $this->get(route('content.show', $page->slug))->assertOk();
    }

    public function test_invalid_or_unauthorized_preview_saves_do_not_change_content(): void
    {
        $page = Page::factory()->published()->create();
        $original = $page->body;
        $this->actingAs($this->editor())->put(route('pages.update', $page), [...$this->attributes('preview'), 'title' => ''])->assertSessionHasErrors('title');
        $this->put(route('pages.update', $page), $this->attributes('invalid'))->assertSessionHasErrors('intent');
        $this->assertNull($page->refresh()->draft);
        $this->assertSame($original, $page->body);
        $this->actingAs(User::factory()->create())->put(route('pages.update', $page), $this->attributes('preview'))->assertForbidden();
        $editorWithoutView = User::factory()->create();
        $editorWithoutView->givePermissionTo([PagePolicy::CREATE, PagePolicy::UPDATE]);
        $this->actingAs($editorWithoutView)->put(route('pages.update', $page), $this->attributes('preview'))->assertForbidden();
        $this->post(route('pages.store'), $this->attributes('preview'))->assertForbidden();
        $this->assertDatabaseCount('pages', 1);
    }

    public function test_bulk_publication_publishes_saved_edits_and_clears_the_draft(): void
    {
        $page = Page::factory()->published()->create();
        $this->actingAs($this->editor())->put(route('pages.update', $page), $this->attributes('save'))->assertSessionHasNoErrors();
        $this->patch(route('pages.bulk'), ['ids' => [$page->id], 'action' => 'publish'])->assertSessionHasNoErrors();
        $this->assertNull($page->refresh()->draft);
        $this->assertSame('Private title', $page->title);
        $this->get(route('content.show', 'private-slug'))->assertOk();
    }

    public function test_slug_conflicts_at_publication_preserve_the_saved_draft(): void
    {
        $page = Page::factory()->published()->create();
        $this->actingAs($this->editor())->put(route('pages.update', $page), $this->attributes('save'))->assertSessionHasNoErrors();
        Page::factory()->create(['slug' => 'private-slug']);
        $this->put(route('pages.update', $page), $this->attributes('publish'))->assertSessionHasErrors('slug');
        $this->patch(route('pages.bulk'), ['ids' => [$page->id], 'action' => 'publish'])->assertSessionHasErrors('ids');
        $this->assertNotNull($page->refresh()->draft);
        $this->assertNotSame('private-slug', $page->slug);
    }

    public function test_preview_uses_the_saved_template_and_fields_without_changing_the_public_layout(): void
    {
        $template = PageTemplate::factory()->create(['renderer' => 'feature']);
        $fieldSet = PageFieldSet::factory()->create(['key' => 'hero']);
        $template->fieldSets()->attach($fieldSet, ['position' => 0]);
        $page = Page::factory()->published()->create(['slug' => 'live-layout']);
        $attributes = [...$this->attributes('preview'), 'page_template_id' => $template->id, 'template_fields' => ['hero' => ['heading' => 'Private heading']]];

        $this->actingAs($this->editor())->put(route('pages.update', $page), $attributes)->assertSessionHasNoErrors();
        $this->assertNull($page->refresh()->page_template_id);
        $this->get(route('content.show', 'live-layout'))->assertInertia(fn (Assert $response) => $response
            ->component('content/Show')->missing('fields'));
        $this->get(route('content.preview', $page))->assertInertia(fn (Assert $response) => $response
            ->component('content/Feature')->where('fields.hero.heading', 'Private heading')->where('preview.id', $page->id));
        $this->get(route('pages.edit', $page))->assertInertia(fn (Assert $response) => $response
            ->where('page.page_template_id', $template->id)->where('page.template_fields.hero.heading', 'Private heading'));

        $this->put(route('pages.update', $page), [...$attributes, 'intent' => 'publish'])->assertSessionHasNoErrors();
        $this->get(route('content.show', 'private-slug'))->assertInertia(fn (Assert $response) => $response
            ->component('content/Feature')->where('fields.hero.heading', 'Private heading')->where('preview', null));
    }

    public function test_preview_keeps_intentionally_empty_content_in_the_draft(): void
    {
        $page = Page::factory()->published()->create(['excerpt' => 'Live excerpt', 'body' => 'Live body']);
        $this->actingAs($this->editor())->put(route('pages.update', $page), [...$this->attributes('preview'), 'body' => null])->assertSessionHasNoErrors();
        $this->get(route('content.preview', $page))->assertInertia(fn (Assert $response) => $response
            ->where('page.excerpt', null)->where('page.body', null)->where('page.body_html', ''));
        $this->get(route('pages.edit', $page))->assertInertia(fn (Assert $response) => $response
            ->where('page.excerpt', null)->where('page.body', null));
        $this->assertSame('Live body', $page->refresh()->body);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PagePolicy::PERMISSIONS);

        return $user;
    }

    /** @return array<string, mixed> */
    private function attributes(string $intent): array
    {
        return ['title' => 'Private title', 'slug' => 'private-slug', 'excerpt' => null, 'body' => '<p>Private body</p><script>alert(1)</script>', 'body_format' => 'html', 'is_published' => true, 'intent' => $intent];
    }
}
