<?php

namespace Tests\Feature;

use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaFolderTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('media');
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_folder_management_requires_the_manage_folders_permission(): void
    {
        $folder = MediaFolder::factory()->create();
        $this->actingAs($this->operator());
        $this->post(route('media-folders.store'), ['name' => 'Presse'])->assertForbidden();
        $this->put(route('media-folders.update', $folder), ['name' => 'Renommé'])->assertForbidden();
        $this->delete(route('media-folders.destroy', $folder))->assertForbidden();
        $this->assertDatabaseCount('media_folders', 1);
        $this->get(route('media.index'))->assertInertia(fn (Assert $page) => $page->where('canManageFolders', false)->has('folders', 1));
    }

    public function test_folders_nest_up_to_three_levels(): void
    {
        $user = $this->manager();
        $this->actingAs($user)->post(route('media-folders.store'), ['name' => '  Marketing  '])->assertSessionHasNoErrors();
        $marketing = MediaFolder::query()->sole();
        $this->assertSame('Marketing', $marketing->name);
        $this->assertSame($user->id, $marketing->created_by);
        $this->post(route('media-folders.store'), ['name' => 'Événements', 'parent_id' => $marketing->id])->assertSessionHasNoErrors();
        $events = MediaFolder::query()->where('name', 'Événements')->sole();
        $this->post(route('media-folders.store'), ['name' => '2026', 'parent_id' => $events->id])->assertSessionHasNoErrors();
        $year = MediaFolder::query()->where('name', '2026')->sole();

        $this->post(route('media-folders.store'), ['name' => 'Trop profond', 'parent_id' => $year->id])->assertSessionHasErrors('parent_id');
        $this->post(route('media-folders.store'), ['name' => 'Orphelin', 'parent_id' => 999])->assertSessionHasErrors('parent_id');
        $this->post(route('media-folders.store'), ['name' => str_repeat('a', 81)])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('media_folders', 3);
    }

    public function test_sibling_names_are_unique_regardless_of_case(): void
    {
        $parent = MediaFolder::factory()->create(['name' => 'Marketing']);
        MediaFolder::factory()->childOf($parent)->create(['name' => 'Logos']);
        $this->actingAs($this->manager());
        $this->post(route('media-folders.store'), ['name' => 'logos', 'parent_id' => $parent->id])->assertSessionHasErrors('name');
        $this->post(route('media-folders.store'), ['name' => 'MARKETING'])->assertSessionHasErrors('name');
        $this->post(route('media-folders.store'), ['name' => 'Logos'])->assertSessionHasNoErrors();
        $this->put(route('media-folders.update', $parent), ['name' => 'marketing'])->assertSessionHasNoErrors();
        $this->assertSame('marketing', $parent->fresh()->name);
    }

    public function test_moving_folders_prevents_cycles_and_respects_the_depth_limit(): void
    {
        $root = MediaFolder::factory()->create(['name' => 'Racine']);
        $child = MediaFolder::factory()->childOf($root)->create(['name' => 'Enfant']);
        $grandchild = MediaFolder::factory()->childOf($child)->create(['name' => 'Petit-enfant']);
        $other = MediaFolder::factory()->create(['name' => 'Autre']);
        $this->actingAs($this->manager());

        $this->put(route('media-folders.update', $root), ['name' => 'Racine', 'parent_id' => $grandchild->id])->assertSessionHasErrors('parent_id');
        $this->put(route('media-folders.update', $root), ['name' => 'Racine', 'parent_id' => $root->id])->assertSessionHasErrors('parent_id');
        $this->put(route('media-folders.update', $child), ['name' => 'Enfant', 'parent_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $child->fresh()->parent_id);
        $this->put(route('media-folders.update', $other), ['name' => 'Autre', 'parent_id' => $root->id])->assertSessionHasErrors('parent_id');
        $this->put(route('media-folders.update', $grandchild), ['name' => 'Petit-enfant', 'parent_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($grandchild->fresh()->parent_id);
    }

    public function test_deleting_a_folder_moves_its_contents_to_the_parent(): void
    {
        $owner = $this->operator();
        $parent = MediaFolder::factory()->create(['name' => 'Parent']);
        MediaFolder::factory()->childOf($parent)->create(['name' => 'Logos']);
        $doomed = MediaFolder::factory()->childOf($parent)->create(['name' => 'À supprimer']);
        $conflicting = MediaFolder::factory()->childOf($doomed)->create(['name' => 'logos']);
        $unique = MediaFolder::factory()->childOf($doomed)->create(['name' => 'Photos']);
        $file = Media::factory()->create(['uploaded_by' => $owner->id, 'folder_id' => $doomed->id]);

        $this->actingAs($this->manager())->from(route('media.index'))->delete(route('media-folders.destroy', $doomed))
            ->assertRedirect(route('media.index'));

        $this->assertModelMissing($doomed);
        $this->assertSame($parent->id, $file->fresh()->folder_id);
        $this->assertSame([$parent->id, 'logos (2)'], [$conflicting->fresh()->parent_id, $conflicting->fresh()->name]);
        $this->assertSame([$parent->id, 'Photos'], [$unique->fresh()->parent_id, $unique->fresh()->name]);
        $this->assertModelExists($file);
    }

    public function test_listing_filters_by_folder_and_exposes_a_scoped_tree(): void
    {
        $user = $this->operator();
        $marketing = MediaFolder::factory()->create(['name' => 'Marketing']);
        $logos = MediaFolder::factory()->childOf($marketing)->create(['name' => 'Logos']);
        $archive = MediaFolder::factory()->create(['name' => 'Archives']);
        $inLogos = Media::factory()->create(['uploaded_by' => $user->id, 'folder_id' => $logos->id]);
        $unfiled = Media::factory()->create(['uploaded_by' => $user->id]);
        Media::factory()->create(['folder_id' => $logos->id]);
        $this->actingAs($user);

        $this->getJson(route('media.index', ['folder' => $logos->id]))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inLogos->id)
            ->assertJsonPath('data.0.folder_id', $logos->id);
        $this->getJson(route('media.index', ['folder' => 'none']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unfiled->id);
        $this->getJson(route('media.index', ['folder' => $marketing->id]))->assertJsonCount(0, 'data');
        $this->getJson(route('media.index'))->assertJsonCount(2, 'data');
        $this->getJson(route('media.index', ['folder' => 999]))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(route('media.index', ['folder' => 'abc']))->assertJsonValidationErrors('folder');

        $this->get(route('media.index', ['folder' => $logos->id]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.folder', $logos->id)
            ->where('maxFolderDepth', 3)
            ->where('folders', [
                ['id' => $archive->id, 'name' => 'Archives', 'parent_id' => null, 'depth' => 1, 'path' => 'Archives', 'files_count' => 0],
                ['id' => $marketing->id, 'name' => 'Marketing', 'parent_id' => null, 'depth' => 1, 'path' => 'Marketing', 'files_count' => 0],
                ['id' => $logos->id, 'name' => 'Logos', 'parent_id' => $marketing->id, 'depth' => 2, 'path' => 'Marketing › Logos', 'files_count' => 1],
            ]));
    }

    public function test_files_can_be_uploaded_into_and_moved_between_folders(): void
    {
        $user = $this->operator();
        $folder = MediaFolder::factory()->create();
        $this->actingAs($user)->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->image('photo.png', 10, 10), 'folder_id' => $folder->id,
        ])->assertCreated()->assertJsonPath('media.folder_id', $folder->id);
        $this->postJson(route('media.store'), ['file' => UploadedFile::fake()->image('photo.png', 10, 10), 'folder_id' => 999])
            ->assertJsonValidationErrors('folder_id');

        $media = Media::query()->sole();
        $this->put(route('media.update', $media), ['title' => 'Photo', 'visibility' => 'private', 'folder_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($media->fresh()->folder_id);
        $this->put(route('media.update', $media), ['title' => 'Photo', 'visibility' => 'private', 'folder_id' => $folder->id]);
        $this->assertSame($folder->id, $media->fresh()->folder_id);
        $this->put(route('media.update', $media), ['title' => 'Photo sans dossier envoyé', 'visibility' => 'private']);
        $this->assertSame($folder->id, $media->fresh()->folder_id);
    }

    public function test_bulk_move_requires_a_destination_and_authorizes_every_file(): void
    {
        $user = $this->operator();
        $folder = MediaFolder::factory()->create();
        $mine = Media::factory()->count(2)->create(['uploaded_by' => $user->id]);
        $ids = $mine->pluck('id')->all();
        $this->actingAs($user);

        $this->patch(route('media.bulk'), ['ids' => $ids, 'action' => 'move'])->assertSessionHasErrors('folder_id');
        $this->patch(route('media.bulk'), ['ids' => $ids, 'action' => 'move', 'folder_id' => $folder->id])->assertSessionHasNoErrors();
        $this->assertSame([$folder->id, $folder->id], $mine->map(fn (Media $media): ?int => $media->fresh()->folder_id)->all());
        $this->patch(route('media.bulk'), ['ids' => $ids, 'action' => 'move', 'folder_id' => null])->assertSessionHasNoErrors();
        $this->assertSame([null, null], $mine->map(fn (Media $media): ?int => $media->fresh()->folder_id)->all());

        $other = Media::factory()->create();
        $this->patch(route('media.bulk'), ['ids' => [$ids[0], $other->id], 'action' => 'move', 'folder_id' => $folder->id])->assertForbidden();
        $this->assertNull($mine->first()->fresh()->folder_id);
    }

    public function test_the_folder_tree_is_available_as_json_for_the_media_picker(): void
    {
        $user = $this->operator([MediaPolicy::VIEW]);
        $marketing = MediaFolder::factory()->create(['name' => 'Marketing']);
        $logos = MediaFolder::factory()->create(['name' => 'Logos', 'parent_id' => $marketing->id]);
        Media::factory()->count(2)->create(['uploaded_by' => $user->id, 'folder_id' => $logos->id]);
        Media::factory()->create(['uploaded_by' => $user->id]);
        Media::factory()->create(['folder_id' => $logos->id]);

        $this->getJson(route('media-folders.index'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('media-folders.index'))->assertForbidden();

        $this->actingAs($user)->getJson(route('media-folders.index'))->assertOk()
            ->assertJsonCount(2, 'folders')
            ->assertJsonPath('folders.0.name', 'Marketing')->assertJsonPath('folders.0.files_count', 0)
            ->assertJsonPath('folders.1.path', 'Marketing › Logos')->assertJsonPath('folders.1.depth', 2)
            ->assertJsonPath('folders.1.files_count', 2)
            ->assertJsonPath('stats.count', 3)->assertJsonPath('stats.unfiled', 1);
    }

    /** @param list<string>|null $permissions */
    private function operator(?array $permissions = null): User
    {
        $role = Role::create(['name' => 'Media '.fake()->uuid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions ?? [MediaPolicy::VIEW, MediaPolicy::CREATE, MediaPolicy::UPDATE, MediaPolicy::DELETE]);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function manager(): User
    {
        return $this->operator([MediaPolicy::VIEW, MediaPolicy::MANAGE_FOLDERS]);
    }
}
