<?php

namespace Tests\Feature;

use App\Actions\Media\UploadMedia;
use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Policies\MediaPolicy;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaLibraryManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('media');
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_listing_filters_by_type_visibility_tag_and_searches_names_and_alt_text(): void
    {
        $user = $this->operator();
        $photo = $this->file($user, ['title' => 'Photo', 'mime_type' => 'image/png', 'visibility' => 'public', 'tags' => ['Équipe', 'Été']]);
        $report = $this->file($user, ['title' => 'Rapport', 'original_name' => 'bilan-annuel.pdf', 'mime_type' => 'application/pdf', 'tags' => ['Finances']]);
        $logo = $this->file($user, ['title' => 'Logo', 'mime_type' => 'image/webp', 'alt_text' => 'Marque officielle']);
        $this->file(User::factory()->create(), ['title' => 'Bilan étranger', 'original_name' => 'bilan.pdf']);
        $this->actingAs($user);

        $this->assertIds(['type' => 'image'], [$photo, $logo]);
        $this->assertIds(['type' => 'document'], [$report]);
        $this->assertIds(['visibility' => 'public'], [$photo]);
        $this->assertIds(['tag' => 'Équipe'], [$photo]);
        $this->assertIds(['search' => 'bilan'], [$report]);
        $this->assertIds(['search' => 'officielle'], [$logo]);
        $this->assertIds(['type' => 'image', 'visibility' => 'private'], [$logo]);
    }

    public function test_listing_sorts_paginates_and_rejects_unknown_options(): void
    {
        $user = $this->operator();
        $large = $this->file($user, ['title' => 'Beta', 'size' => 9000]);
        $small = $this->file($user, ['title' => 'Alpha', 'size' => 10]);
        $this->actingAs($user);

        $this->assertIds(['sort' => 'size', 'direction' => 'asc'], [$small, $large], ordered: true);
        $this->assertIds(['sort' => 'title', 'direction' => 'desc'], [$large, $small], ordered: true);
        $this->getJson(route('media.index', ['per_page' => 48]))->assertOk()->assertJsonPath('per_page', 48);
        $this->getJson(route('media.index', ['per_page' => 1000]))->assertJsonValidationErrors('per_page');
        $this->getJson(route('media.index', ['sort' => 'path']))->assertJsonValidationErrors('sort');
        $this->getJson(route('media.index', ['type' => 'video']))->assertJsonValidationErrors('type');
    }

    public function test_page_receives_scoped_stats_tags_and_normalized_filters(): void
    {
        $user = $this->operator();
        $this->file($user, ['size' => 1000, 'tags' => ['Presse', 'logos']]);
        $this->file($user, ['size' => 500, 'tags' => ['presse', 'Archives']]);
        $this->file(User::factory()->create(), ['size' => 99999, 'tags' => ['Secret']]);

        $this->actingAs($user)->get(route('media.index', ['search' => '  ', 'sort' => 'size']))->assertInertia(fn (Assert $page) => $page
            ->component('media/Index')
            ->where('stats', ['count' => 2, 'size' => 1500, 'unfiled' => 2])
            ->where('tags', ['Archives', 'logos', 'Presse'])
            ->where('filters.search', null)
            ->where('filters.sort', 'size')
            ->where('filters.direction', 'desc')
            ->where('filters.per_page', 24)
            ->where('canBulkUpdate', true)
            ->has('media.data.0.usages')
            ->where('media.data.0.uploader', $user->name));
    }

    public function test_json_upload_returns_the_item_with_normalized_tags(): void
    {
        $user = $this->operator();
        $this->actingAs($user)->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->image('photo.png', 40, 40),
            'tags' => [' Presse ', 'presse', 'Été  2026', ''],
        ])->assertCreated()
            ->assertJsonPath('media.title', 'photo.png')
            ->assertJsonPath('media.tags', ['Presse', 'Été 2026'])
            ->assertJsonPath('media.is_image', true)
            ->assertJsonPath('media.uploader', $user->name)
            ->assertJsonMissingPath('media.path');
        $this->assertSame(['Presse', 'Été 2026'], Media::query()->sole()->tags);
    }

    public function test_tag_limits_are_validated(): void
    {
        $this->actingAs($this->operator());
        $this->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->image('photo.png', 10, 10),
            'tags' => array_map(fn (int $number): string => 'tag '.$number, range(1, 11)),
        ])->assertJsonValidationErrors('tags');
        $this->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->image('photo.png', 10, 10),
            'tags' => [str_repeat('a', 31)],
        ])->assertJsonValidationErrors('tags.0');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_optimized_png_uploads_are_converted_to_webp(): void
    {
        $user = $this->operator();
        $this->actingAs($user)->post(route('media.store'), [
            'file' => $this->noisyImage('photo.png', 'png'), 'optimize' => '1',
        ])->assertRedirect(route('media.index'));
        $media = Media::query()->sole();
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame('photo.webp', $media->original_name);
        $this->assertStringEndsWith('/original.webp', $media->path);
        $this->assertSame(strlen(Storage::disk('media')->get($media->path)), $media->size);
        Storage::disk('media')->assertExists($media->thumbnail_path);
        $this->assertCount(2, Storage::disk('media')->allFiles());
    }

    public function test_uploads_keep_the_original_format_without_optimization(): void
    {
        $this->actingAs($this->operator())->post(route('media.store'), ['file' => $this->noisyImage('photo.png', 'png')]);
        $this->assertSame('image/png', Media::query()->sole()->mime_type);
    }

    public function test_thumbnails_and_webp_copies_follow_exif_orientation(): void
    {
        $user = $this->operator();
        $media = app(UploadMedia::class)->handle($user, $this->rotatedJpeg(), optimize: true);
        $thumbnail = getimagesizefromstring(Storage::disk('media')->get($media->thumbnail_path));
        $this->assertSame([100, 200], [$thumbnail[0], $thumbnail[1]]);
        $this->assertSame('image/webp', $media->mime_type);
        $original = getimagesizefromstring(Storage::disk('media')->get($media->path));
        $this->assertSame([100, 200], [$original[0], $original[1]]);
    }

    public function test_metadata_updates_can_set_and_clear_tags(): void
    {
        $user = $this->operator();
        $media = $this->file($user, ['tags' => ['Ancien']]);
        $this->actingAs($user)->from(route('media.index'))->put(route('media.update', $media), [
            'title' => 'Nouveau', 'visibility' => 'private', 'tags' => 'Presse, Logos, presse',
        ])->assertRedirect(route('media.index'));
        $this->assertSame(['Presse', 'Logos'], $media->fresh()->tags);
        $this->put(route('media.update', $media), ['title' => 'Nouveau', 'visibility' => 'private', 'tags' => []]);
        $this->assertNull($media->fresh()->tags);
        $this->put(route('media.update', $media), ['title' => 'Sans tags envoyés', 'visibility' => 'private']);
        $this->assertNull($media->fresh()->tags);
    }

    public function test_replacing_a_file_keeps_its_id_attachments_and_custom_title(): void
    {
        $user = $this->operator();
        $media = app(UploadMedia::class)->handle($user, UploadedFile::fake()->image('ancien.png', 50, 50), 'Logo principal', 'Logo');
        $page = Page::factory()->create();
        $page->attachments()->attach($media->id);
        $previous = [$media->path, $media->thumbnail_path];

        $this->actingAs($user)->from(route('media.index'))->post(route('media.replace', $media), [
            'file' => UploadedFile::fake()->image('nouveau.jpg', 60, 30),
        ])->assertRedirect(route('media.index'));

        $media->refresh();
        $this->assertSame('Logo principal', $media->title);
        $this->assertSame('nouveau.jpg', $media->original_name);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('Logo', $media->alt_text);
        Storage::disk('media')->assertMissing($previous);
        Storage::disk('media')->assertExists([$media->path, $media->thumbnail_path]);
        $this->assertCount(2, Storage::disk('media')->allFiles());
        $this->assertTrue($page->attachments()->whereKey($media->id)->exists());
        $this->get(route('media-files.download', $media))->assertDownload('nouveau.jpg');
    }

    public function test_replacing_a_file_updates_an_automatic_title(): void
    {
        $user = $this->operator();
        $media = app(UploadMedia::class)->handle($user, UploadedFile::fake()->create('notes.txt', 1, 'text/plain'));
        $this->actingAs($user)->post(route('media.replace', $media), ['file' => UploadedFile::fake()->create('bilan.pdf', 5, 'application/pdf')]);
        $this->assertSame('bilan.pdf', $media->fresh()->title);
    }

    public function test_images_can_only_be_replaced_by_images_and_only_by_authorized_users(): void
    {
        $user = $this->operator();
        $media = app(UploadMedia::class)->handle($user, UploadedFile::fake()->image('photo.png', 20, 20));
        $this->actingAs($user)->post(route('media.replace', $media), ['file' => UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->operator())->post(route('media.replace', $media), ['file' => UploadedFile::fake()->image('other.png', 20, 20)])
            ->assertForbidden();
        $this->assertSame('photo.png', $media->fresh()->original_name);
    }

    public function test_bulk_visibility_changes_require_publish_permission_to_go_public(): void
    {
        $user = $this->operator();
        $first = $this->file($user);
        $second = $this->file($user);
        $this->actingAs($user)->patch(route('media.bulk'), ['ids' => [$first->id, $second->id], 'action' => 'public'])->assertForbidden();
        $this->assertSame('private', $first->fresh()->visibility);

        $publisher = $this->operator([...MediaPolicy::PERMISSIONS]);
        $mine = [$this->file($publisher), $this->file($publisher)];
        $this->actingAs($publisher)->from(route('media.index'))
            ->patch(route('media.bulk'), ['ids' => array_map(fn (Media $media): string => $media->id, $mine), 'action' => 'public'])
            ->assertRedirect(route('media.index'))->assertSessionHasNoErrors();
        $this->assertSame(['public', 'public'], array_map(fn (Media $media): string => $media->fresh()->visibility, $mine));
        $this->patch(route('media.bulk'), ['ids' => [$mine[0]->id], 'action' => 'private']);
        $this->assertSame('private', $mine[0]->fresh()->visibility);
    }

    public function test_bulk_actions_authorize_every_row_before_changing_any(): void
    {
        $user = $this->operator();
        $mine = $this->file($user);
        $other = $this->file(User::factory()->create());
        $this->actingAs($user)->patch(route('media.bulk'), ['ids' => [$mine->id, $other->id], 'action' => 'delete'])->assertForbidden();
        $this->assertModelExists($mine);
        $this->patch(route('media.bulk'), ['ids' => [$mine->id, '0199c4a4-0000-7000-8000-000000000000'], 'action' => 'private'])
            ->assertSessionHasErrors('ids');
        $this->patch(route('media.bulk'), ['ids' => [$mine->id], 'action' => 'archive'])->assertSessionHasErrors('action');
        $this->patch(route('media.bulk'), ['ids' => ['not-a-uuid'], 'action' => 'private'])->assertSessionHasErrors('ids.0');
    }

    public function test_bulk_delete_removes_files_but_refuses_attached_ones(): void
    {
        $user = $this->operator();
        $first = app(UploadMedia::class)->handle($user, UploadedFile::fake()->image('a.png', 10, 10));
        $second = app(UploadMedia::class)->handle($user, UploadedFile::fake()->image('b.png', 10, 10));
        $attached = $this->file($user);
        Page::factory()->create()->attachments()->attach($attached->id);

        $this->actingAs($user)->patch(route('media.bulk'), ['ids' => [$first->id, $attached->id], 'action' => 'delete'])
            ->assertSessionHasErrors('ids');
        $this->assertModelExists($first);

        $this->patch(route('media.bulk'), ['ids' => [$first->id, $second->id], 'action' => 'delete'])->assertSessionHasNoErrors();
        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertSame([$attached->path], Storage::disk('media')->allFiles());
    }

    public function test_items_list_where_they_are_used_with_edit_links_only_when_allowed(): void
    {
        $user = $this->operator([MediaPolicy::VIEW, PagePolicy::VIEW, PagePolicy::UPDATE]);
        $media = $this->file($user);
        $page = Page::factory()->create(['title' => 'Accueil']);
        $trashed = Page::factory()->create(['title' => 'Ancienne page']);
        $page->attachments()->attach($media->id);
        $trashed->attachments()->attach($media->id);
        $trashed->delete();

        $usages = collect($this->actingAs($user)->getJson(route('media.index'))->json('data.0.usages'))->keyBy('title');
        $this->assertSame(route('pages.edit', $page), $usages['Accueil']['url']);
        $this->assertFalse($usages['Accueil']['trashed']);
        $this->assertNull($usages['Ancienne page']['url']);
        $this->assertTrue($usages['Ancienne page']['trashed']);

        $viewer = $this->operator([MediaPolicy::VIEW, MediaPolicy::MANAGE_ALL]);
        $this->actingAs($viewer)->getJson(route('media.index'))->assertJsonPath('data.0.usages.0.url', null);
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<Media>  $expected
     */
    private function assertIds(array $query, array $expected, bool $ordered = false): void
    {
        $ids = $this->getJson(route('media.index', $query))->assertOk()->json('data.*.id');
        $expectedIds = array_map(fn (Media $media): string => $media->id, $expected);
        if (! $ordered) {
            sort($ids);
            sort($expectedIds);
        }
        $this->assertSame($expectedIds, $ids);
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

    /** @param array<string, mixed> $attributes */
    private function file(User $owner, array $attributes = []): Media
    {
        $media = Media::factory()->create(['uploaded_by' => $owner->id, ...$attributes]);
        Storage::disk('media')->put($media->path, 'Example');

        return $media;
    }

    private function noisyImage(string $name, string $format): UploadedFile
    {
        $image = imagecreatetruecolor(300, 200);
        mt_srand(42);
        for ($x = 0; $x < 300; $x++) {
            for ($y = 0; $y < 200; $y++) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
        ob_start();
        $format === 'png' ? imagepng($image) : imagejpeg($image, null, 95);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    /**
     * Build a 200×100 JPEG whose EXIF orientation (6) asks viewers to rotate it to portrait.
     */
    private function rotatedJpeg(): UploadedFile
    {
        $image = imagecreatetruecolor(200, 100);
        imagefill($image, 0, 0, 0x3366CC);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', 6).pack('v', 0).pack('V', 0);
        $segment = "Exif\0\0".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($segment) + 2).$segment;

        return UploadedFile::fake()->createWithContent('portrait.jpg', substr($jpeg, 0, 2).$app1.substr($jpeg, 2));
    }
}
