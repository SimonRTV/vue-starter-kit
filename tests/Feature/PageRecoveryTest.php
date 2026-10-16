<?php

namespace Tests\Feature;

use App\Actions\Pages\RecordPageRevision;
use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageFieldSet;
use App\Models\PageRevision;
use App\Models\PageTemplate;
use App\Models\User;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_creation_and_edits_record_versions_and_author_without_duplicate_saves(): void
    {
        $user = $this->editor();
        $this->actingAs($user)->post(route('pages.store'), $this->attributes())->assertSessionHasNoErrors();
        $page = Page::query()->sole();
        $revision = $page->revisions()->sole();
        $this->assertSame($user->id, $revision->user_id);
        $this->assertSame($user->name, $revision->author_name);
        $this->assertSame('created', $revision->event);
        $this->assertSame('Original content', $revision->content['body']);

        $updated = [...$this->attributes(), 'body' => 'Updated content'];
        $this->put(route('pages.update', $page), $updated)->assertSessionHasNoErrors();
        $this->put(route('pages.update', $page), $updated)->assertSessionHasNoErrors();
        $this->assertSame(2, $page->revisions()->count());
        $this->assertSame('Updated content', $page->revisions()->latest('id')->first()->content['body']);
    }

    public function test_existing_page_baseline_and_draft_are_preserved_before_the_first_edit(): void
    {
        $page = Page::factory()->published()->create(['title' => 'Live title', 'body' => 'Live body', 'draft' => [
            'title' => 'Old draft', 'slug' => 'old-draft', 'excerpt' => null, 'body' => 'Old draft body', 'body_format' => 'text',
        ]]);
        $this->actingAs($this->editor())->put(route('pages.update', $page), $this->attributes())->assertSessionHasNoErrors();
        $this->assertSame(['Live body', 'Old draft body', 'Original content'], $page->revisions()->orderBy('id')->get()->map(fn (PageRevision $revision): ?string => $revision->content['body'])->all());
        $this->assertNull($page->revisions()->oldest('id')->first()->user_id);
        $this->assertSame('Live body', $page->refresh()->body);
    }

    public function test_restoring_a_revision_preserves_live_content_and_the_replaced_draft(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'live', 'body' => 'Live body', 'seo' => ['title' => 'Keep SEO']]);
        $user = $this->editor();
        $this->actingAs($user)->put(route('pages.update', $page), $this->attributes())->assertSessionHasNoErrors();
        $old = $page->revisions()->latest('id')->first();
        $this->put(route('pages.update', $page), [...$this->attributes(), 'body' => 'New draft'])->assertSessionHasNoErrors();
        $before = $page->revisions()->count();
        $this->post(route('page-revisions.restore', [$page, $old]), ['body' => 'Tampered'])->assertRedirect(route('pages.edit', $page))->assertSessionHasNoErrors();
        $this->assertSame('Original content', $page->refresh()->draft['body']);
        $this->assertSame('Live body', $page->body);
        $this->assertTrue($page->is_published);
        $this->assertSame(['title' => 'Keep SEO'], $page->seo);
        $this->assertSame($before + 1, $page->revisions()->count());
        $this->assertTrue($page->revisions()->get()->contains(fn (PageRevision $revision): bool => $revision->content['body'] === 'New draft'));
        $this->get(route('content.show', 'live'))->assertInertia(fn (Assert $view) => $view->where('page.body', 'Live body'));
        $this->post(route('page-revisions.restore', [$page, $old]))->assertSessionHasNoErrors();
        $this->assertSame($before + 1, $page->revisions()->count());
    }

    public function test_restoring_published_history_to_an_unpublished_page_does_not_publish_it(): void
    {
        $page = Page::factory()->published()->create(['body' => 'Original live content']);
        $this->actingAs($this->editor())->put(route('pages.update', $page), [...$this->attributes(), 'intent' => 'unpublish'])->assertSessionHasNoErrors();
        $revision = $page->revisions()->oldest('id')->first();
        $this->post(route('page-revisions.restore', [$page, $revision]))->assertSessionHasNoErrors();
        $this->assertFalse($page->refresh()->is_published);
        $this->assertNull($page->published_at);
        $this->assertSame('Original live content', $page->body);
        $this->get(route('content.show', $page->slug))->assertNotFound();
    }

    public function test_history_compares_a_scoped_revision_with_the_current_draft_and_sanitizes_html(): void
    {
        $page = Page::factory()->published()->create(['body' => 'Live']);
        $revision = PageRevision::factory()->for($page)->create(['content' => [
            ...app(RecordPageRevision::class)->content($page), 'body_format' => 'html',
            'body' => '<p>Safe</p><script>alert(1)</script>',
        ]]);
        $page->update(['draft' => [...app(RecordPageRevision::class)->content($page), 'body' => 'Current draft']]);
        $this->actingAs($this->editor())->get(route('page-revisions.index', ['page' => $page, 'revision' => $revision]))->assertInertia(fn (Assert $view) => $view
            ->component('pages/History')->where('current.body', 'Current draft')->where('selected.body_html', '<p>Safe</p>')
            ->where('selected.id', $revision->id)->missing('revisions.data.0.content')->where('canRestore', true));
        $this->post(route('page-revisions.restore', [$page, $revision]))->assertSessionHasNoErrors();
        $this->assertSame('<p>Safe</p>', $page->refresh()->draft['body']);
    }

    public function test_history_and_trash_authorization_and_cross_page_revision_isolation(): void
    {
        $page = Page::factory()->create();
        $revision = PageRevision::factory()->for($page)->create();
        $other = Page::factory()->create();
        $this->get(route('page-revisions.index', $page))->assertRedirect(route('login'));
        $this->get(route('page-trash.index'))->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('page-revisions.index', $page))->assertForbidden();
        $this->get(route('page-trash.index'))->assertForbidden();
        $user->givePermissionTo(PagePolicy::VIEW);
        $this->get(route('page-revisions.index', $page))->assertInertia(fn (Assert $view) => $view->where('canRestore', false));
        $this->post(route('page-revisions.restore', [$page, $revision]))->assertForbidden();
        $this->get(route('page-trash.index'))->assertForbidden();
        $this->actingAs($this->editor())->get(route('page-revisions.index', ['page' => $other, 'revision' => $revision]))->assertNotFound();
        $this->post(route('page-revisions.restore', [$other, $revision]))->assertNotFound();
        $page->delete();
        $this->actingAs($user)->post(route('page-trash.restore', $page))->assertForbidden();
        $this->delete(route('page-trash.destroy', $page))->assertForbidden();
        $this->assertSoftDeleted($page);
    }

    public function test_invalid_revision_restoration_preserves_content_and_history(): void
    {
        $page = Page::factory()->create(['body' => 'Current']);
        $revision = PageRevision::factory()->for($page)->create(['content' => [
            ...app(RecordPageRevision::class)->content($page), 'slug' => 'reserved',
        ]]);
        $reserved = Page::factory()->create(['slug' => 'reserved']);
        $reserved->delete();
        $this->actingAs($this->editor())->from(route('page-revisions.index', $page))->post(route('page-revisions.restore', [$page, $revision]))
            ->assertSessionHasErrors('revision');
        $this->assertSame('Current', $page->refresh()->body);
        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_template_fields_are_restored_and_obsolete_definitions_are_rejected(): void
    {
        $template = PageTemplate::factory()->create(['renderer' => 'feature']);
        $fields = PageFieldSet::factory()->create(['key' => 'hero']);
        $template->fieldSets()->attach($fields, ['position' => 0]);
        $page = Page::factory()->create();
        $revision = PageRevision::factory()->for($page)->create(['content' => [
            ...app(RecordPageRevision::class)->content($page),
            'page_template_id' => $template->id, 'template_fields' => ['hero' => ['heading' => 'Historic heading']],
        ]]);
        $this->actingAs($this->editor())->post(route('page-revisions.restore', [$page, $revision]))->assertSessionHasNoErrors();
        $this->assertSame($template->id, $page->refresh()->page_template_id);
        $this->assertSame('Historic heading', $page->template_fields['hero']['heading']);
        $fields->update(['fields' => [['key' => 'other', 'label' => 'Other', 'type' => 'text', 'required' => true]]]);
        $this->post(route('page-revisions.restore', [$page, $revision]))->assertSessionHasErrors('revision');
        $this->assertSame('Historic heading', $page->refresh()->template_fields['hero']['heading']);
    }

    public function test_trash_hides_a_page_everywhere_and_restoration_preserves_drafts_and_attachments(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'recoverable']);
        $media = Media::factory()->create();
        $page->attachments()->attach($media);
        $this->actingAs($this->editor())->put(route('pages.update', $page), $this->attributes())->assertSessionHasNoErrors();
        $count = $page->revisions()->count();
        $this->delete(route('pages.destroy', $page))->assertRedirect(route('pages.index'));
        $this->assertSoftDeleted($page);
        $this->assertSame(1, $page->attachments()->count());
        $this->get(route('pages.index'))->assertInertia(fn (Assert $view) => $view->where('pages.total', 0)->where('canViewTrash', true));
        foreach (['pages.show', 'pages.edit', 'content.preview', 'page-revisions.index'] as $route) {
            $this->get(route($route, $page))->assertNotFound();
        }
        $this->get(route('content.show', 'recoverable'))->assertNotFound();
        $this->get(route('sitemap'))->assertDontSee(route('content.show', 'recoverable'));
        $this->get(route('page-trash.index'))->assertInertia(fn (Assert $view) => $view->component('pages/Trash')->where('pages.total', 1));
        $this->post(route('page-trash.restore', $page))->assertRedirect(route('pages.show', $page));
        $this->assertFalse($page->refresh()->trashed());
        $this->assertFalse($page->is_published);
        $this->assertNull($page->published_at);
        $this->assertSame('Original content', $page->draft['body']);
        $this->assertSame($count, $page->revisions()->count());
        $this->assertSame(1, $page->attachments()->count());
        $this->get(route('content.show', 'recoverable'))->assertNotFound();
        $this->get(route('pages.edit', $page))->assertInertia(fn (Assert $view) => $view->where('page.body', 'Original content'));
    }

    public function test_permanent_deletion_requires_trash_and_removes_history_without_deleting_shared_files(): void
    {
        $page = Page::factory()->create();
        PageRevision::factory()->for($page)->create();
        $media = Media::factory()->create();
        $other = Page::factory()->create();
        $page->attachments()->attach($media);
        $other->attachments()->attach($media);
        $this->actingAs($this->editor())->delete(route('page-trash.destroy', $page))->assertNotFound();
        $this->post(route('page-trash.restore', $page))->assertNotFound();
        $this->delete(route('pages.destroy', $page))->assertRedirect();
        $this->delete(route('page-trash.destroy', $page))->assertRedirect(route('page-trash.index'));
        $this->assertModelMissing($page);
        $this->assertSame(0, $page->revisions()->count());
        $this->assertModelExists($media);
        $this->assertDatabaseCount('media_attachments', 1);
        $this->assertSame($media->id, $other->attachments()->sole()->id);
        $this->post(route('page-trash.restore', $page))->assertNotFound();
    }

    public function test_trashed_slugs_are_reserved_for_creation_updates_and_bulk_publication(): void
    {
        $trashed = Page::factory()->create(['slug' => 'reserved']);
        $trashed->delete();
        $this->actingAs($this->editor())->post(route('pages.store'), [...$this->attributes(), 'slug' => 'reserved'])->assertSessionHasNoErrors();
        $created = Page::query()->sole();
        $this->assertSame('reserved-2', $created->slug);
        $this->put(route('pages.update', $created), [...$this->attributes(), 'slug' => 'reserved'])->assertSessionHasErrors('slug');
        $created->update(['draft' => [...app(RecordPageRevision::class)->content($created), 'slug' => 'reserved']]);
        $this->patch(route('pages.bulk'), ['ids' => [$created->id], 'action' => 'publish'])->assertSessionHasErrors('ids');
        $this->assertFalse($created->refresh()->is_published);
    }

    public function test_trash_search_pagination_and_history_pagination_are_bounded(): void
    {
        Page::factory()->count(12)->create(['title' => 'Recover me'])->each(fn (Page $page) => $page->delete());
        $page = Page::factory()->create();
        PageRevision::factory()->for($page)->count(12)->create();
        $this->actingAs($this->editor())->get(route('page-trash.index', ['search' => 'Recover', 'page' => 2]))->assertInertia(fn (Assert $view) => $view->has('pages.data', 2)->where('pages.total', 12));
        $this->get(route('page-trash.index', ['search' => 'missing']))->assertInertia(fn (Assert $view) => $view->where('pages.total', 0));
        $this->get(route('page-trash.index', ['page' => 99]))->assertRedirect(route('page-trash.index', ['page' => 2]));
        $this->get(route('page-revisions.index', ['page' => $page, 'history_page' => 2]))->assertInertia(fn (Assert $view) => $view->has('revisions.data', 2)->where('revisions.total', 12));
        $this->get(route('page-revisions.index', ['page' => $page, 'history_page' => 99]))->assertRedirect();
        $this->getJson(route('page-revisions.index', ['page' => $page, 'revision' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('page-revisions.index', ['page' => $page, 'history_page' => -1]))->assertUnprocessable();
    }

    public function test_disabled_pages_module_blocks_all_recovery_routes(): void
    {
        $page = Page::factory()->create();
        $revision = PageRevision::factory()->for($page)->create();
        $this->actingAs($this->editor());
        config(['starter.features.pages' => false]);
        $this->get(route('page-revisions.index', $page))->assertNotFound();
        $this->post(route('page-revisions.restore', [$page, $revision]))->assertNotFound();
        $page->delete();
        $this->get(route('page-trash.index'))->assertNotFound();
        $this->post(route('page-trash.restore', $page))->assertNotFound();
        $this->delete(route('page-trash.destroy', $page))->assertNotFound();
    }

    public function test_template_used_in_trash_cannot_be_deleted(): void
    {
        $template = PageTemplate::factory()->create();
        $page = Page::factory()->create(['page_template_id' => $template->id]);
        $page->delete();
        $this->actingAs($this->editor())->delete(route('page-templates.destroy', $template))->assertSessionHasErrors('template');
        $this->assertModelExists($template);
    }

    public function test_a_revision_write_failure_rolls_back_the_page_and_baseline(): void
    {
        $page = Page::factory()->create(['body' => 'Preserved content']);
        PageRevision::creating(function (PageRevision $revision): void {
            if ($revision->content['body'] === 'Original content') {
                throw new \RuntimeException('Simulated revision failure');
            }
        });
        $this->actingAs($this->editor())->put(route('pages.update', $page), $this->attributes())->assertStatus(500);
        $this->assertSame('Preserved content', $page->refresh()->body);
        $this->assertSame(0, $page->revisions()->count());
    }

    public function test_history_is_read_only_until_the_first_save_and_bulk_updates_record_versions(): void
    {
        $page = Page::factory()->create();
        $this->actingAs($this->editor())->get(route('page-revisions.index', $page))->assertInertia(fn (Assert $view) => $view
            ->where('selected', null)->where('revisions.total', 0));
        $this->assertSame(0, $page->revisions()->count());
        $this->patch(route('pages.bulk'), ['ids' => [$page->id], 'action' => 'publish'])->assertSessionHasNoErrors();
        $this->assertSame(2, $page->revisions()->count());
        $this->assertTrue($page->revisions()->latest('id')->first()->is_published);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PagePolicy::PERMISSIONS);

        return $user;
    }

    /** @return array<string, mixed> */
    private function attributes(): array
    {
        return ['title' => 'Page title', 'slug' => 'page-title', 'excerpt' => null, 'body' => 'Original content', 'body_format' => 'text', 'is_published' => false, 'intent' => 'save'];
    }
}
