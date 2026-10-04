<?php

namespace Tests\Feature;

use App\Actions\Pages\DeletePage;
use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Policies\MediaPolicy;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageAttachmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('media');
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_authorized_attachment_is_idempotent_and_can_be_detached_without_deleting_file(): void
    {
        $user = $this->editor();
        $media = Media::factory()->create(['uploaded_by' => $user->id]);
        $page = Page::factory()->create();
        $this->actingAs($user);
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('page-attachments.store', $page), ['media_id' => $media->id])->assertRedirect(route('pages.edit', $page));
        }
        $this->assertSame(1, $page->attachments()->count());
        $this->get(route('pages.edit', $page))->assertInertia(fn (Assert $response) => $response->has('attachments', 1)->where('attachments.0.id', $media->id));
        $this->delete(route('page-attachments.destroy', [$page, $media]))->assertRedirect(route('pages.edit', $page));
        $this->assertSame(0, $page->attachments()->count());
        $this->assertModelExists($media);
    }

    public function test_attaching_requires_both_page_authority_and_access_to_the_file(): void
    {
        $page = Page::factory()->create();
        $user = $this->editor();
        $otherMedia = Media::factory()->create();
        $this->actingAs($user)->post(route('page-attachments.store', $page), ['media_id' => $otherMedia->id])->assertForbidden();
        $unprivileged = User::factory()->create();
        $ownMedia = Media::factory()->create(['uploaded_by' => $unprivileged->id]);
        $this->actingAs($unprivileged)->post(route('page-attachments.store', $page), ['media_id' => $ownMedia->id])->assertForbidden();
        $this->assertDatabaseCount('media_attachments', 0);
    }

    public function test_unrelated_detach_is_rejected_and_attached_files_cannot_be_deleted(): void
    {
        $user = $this->editor();
        $media = Media::factory()->create(['uploaded_by' => $user->id]);
        Storage::disk('media')->put($media->path, 'Example');
        $page = Page::factory()->create();
        $unrelated = Page::factory()->create();
        $page->attachments()->attach($media);
        $this->actingAs($user)->delete(route('page-attachments.destroy', [$unrelated, $media]))->assertNotFound();
        $this->delete(route('media.destroy', $media))->assertSessionHasErrors('media');
        $this->assertModelExists($media);
        Storage::disk('media')->assertExists($media->path);
    }

    public function test_public_pages_expose_only_public_attachments_and_feature_toggle_hides_them(): void
    {
        $page = Page::factory()->published()->create();
        $public = Media::factory()->publiclyShared()->create();
        $private = Media::factory()->create();
        $page->attachments()->attach([$public->id, $private->id]);
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $response) => $response
            ->has('page.attachments', 1)->where('page.attachments.0.id', $public->id));
        config(['starter.features.media' => false]);
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $response) => $response->where('page.attachments', []));
        $this->actingAs($this->editor())->post(route('page-attachments.store', $page), ['media_id' => $private->id])->assertNotFound();
    }

    public function test_page_reader_exposes_only_authorized_attachments_and_respects_the_feature_toggle(): void
    {
        $user = $this->editor();
        $user->givePermissionTo(PagePolicy::VIEW);
        $page = Page::factory()->create();
        $accessible = Media::factory()->create(['uploaded_by' => $user->id]);
        $inaccessible = Media::factory()->create();
        $page->attachments()->attach([$accessible->id, $inaccessible->id]);

        $this->actingAs($user)->get(route('pages.show', $page))
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('pages/Show')
                ->has('attachments', 1)
                ->where('attachments.0.id', $accessible->id)
                ->where('attachments.0.download_url', route('media-files.download', $accessible)),
            );

        config(['starter.features.media' => false]);

        $this->get(route('pages.show', $page))
            ->assertInertia(fn (Assert $response) => $response->where('attachments', []));
    }

    public function test_page_reader_without_media_permission_does_not_receive_attached_files(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PagePolicy::VIEW);
        $page = Page::factory()->create();
        $media = Media::factory()->create(['uploaded_by' => $user->id]);
        $page->attachments()->attach($media);

        $this->actingAs($user)->get(route('pages.show', $page))
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response->where('attachments', []));
    }

    public function test_deleting_a_page_cleans_its_links_but_preserves_shared_media(): void
    {
        $page = Page::factory()->create();
        $other = Page::factory()->create();
        $media = Media::factory()->create();
        $page->attachments()->attach($media);
        $other->attachments()->attach($media);
        app(DeletePage::class)->handle($page);
        $this->assertDatabaseCount('media_attachments', 1);
        $this->assertSame($media->id, $other->attachments()->sole()->id);
        $this->assertModelExists($media);
    }

    public function test_attachment_validation_and_limit(): void
    {
        $user = $this->editor();
        $page = Page::factory()->create();
        $this->actingAs($user)->post(route('page-attachments.store', $page), ['media_id' => 'not-a-uuid'])->assertSessionHasErrors('media_id');
        $media = Media::factory()->count(51)->create(['uploaded_by' => $user->id]);
        $page->attachments()->attach($media->take(50)->modelKeys());
        $this->post(route('page-attachments.store', $page), ['media_id' => $media->last()->id])->assertSessionHasErrors('media_id');
        $this->assertSame(50, $page->attachments()->count());
    }

    private function editor(): User
    {
        $role = Role::create(['name' => 'Editor '.fake()->uuid(), 'guard_name' => 'web']);
        $role->syncPermissions([PagePolicy::UPDATE, MediaPolicy::VIEW, MediaPolicy::DELETE]);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
