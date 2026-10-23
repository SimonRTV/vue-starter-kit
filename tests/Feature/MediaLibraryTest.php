<?php

namespace Tests\Feature;

use App\Actions\Media\DeleteMedia;
use App\Actions\Media\MediaLibrary;
use App\Actions\Media\UploadMedia;
use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Media;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('media');
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_guests_and_unprivileged_users_cannot_manage_the_library(): void
    {
        $this->get(route('media.index'))->assertRedirect(route('login'));
        $this->post(route('media.store'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('media.index'))->assertForbidden();
        $this->post(route('media.store'), ['file' => UploadedFile::fake()->image('photo.png')])->assertForbidden();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_defaults_to_private_and_creates_a_safe_thumbnail(): void
    {
        $user = $this->operator();
        $this->actingAs($user)->post(route('media.store'), [
            'file' => UploadedFile::fake()->image('photo.png', 900, 600),
            'title' => 'Portrait', 'alt_text' => 'Un portrait',
            'uploaded_by' => 999, 'disk' => 'public', 'path' => 'injected',
        ])->assertRedirect(route('media.index'));
        $media = Media::query()->sole();
        $this->assertSame($user->id, $media->uploaded_by);
        $this->assertSame('private', $media->visibility);
        $this->assertSame('media', $media->disk);
        Storage::disk('media')->assertExists([$media->path, $media->thumbnail_path]);
        $dimensions = getimagesizefromstring(Storage::disk('media')->get($media->thumbnail_path));
        $this->assertSame(400, $dimensions[0]);
        $this->assertSame('image/jpeg', $dimensions['mime']);
        $this->get(route('media-files.thumbnail', $media))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('media-files.download', $media))->assertDownload('photo.png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson(route('media.index'))->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.path')->assertJsonMissingPath('data.0.disk');
    }

    public function test_library_picker_and_mutations_are_scoped_to_the_owner(): void
    {
        $user = $this->operator();
        $mine = $this->file($user);
        $other = $this->file(User::factory()->create());
        $this->actingAs($user)->getJson(route('media.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->get(route('media.index'))->assertInertia(fn (Assert $page) => $page->has('media.data', 1)->where('auth.can.manageMedia', true));
        $this->put(route('media.update', $other), ['title' => 'Stolen', 'visibility' => 'private'])->assertForbidden();
        $this->delete(route('media.destroy', $other))->assertForbidden();
        $this->get(route('media-files.download', $other))->assertNotFound();
        $this->assertModelExists($other);
    }

    public function test_manage_all_requires_individual_operation_permissions(): void
    {
        $user = $this->operator([MediaPolicy::VIEW, MediaPolicy::MANAGE_ALL]);
        $media = $this->file(User::factory()->create());
        $this->actingAs($user)->getJson(route('media.index'))->assertJsonCount(1, 'data');
        $this->get(route('media-files.download', $media))->assertOk();
        $this->delete(route('media.destroy', $media))->assertForbidden();
        $this->put(route('media.update', $media), ['title' => 'Changed', 'visibility' => 'private'])->assertForbidden();
    }

    public function test_private_files_and_thumbnails_cannot_be_accessed_anonymously_or_unverified(): void
    {
        $user = $this->operator();
        $media = app(UploadMedia::class)->handle($user, UploadedFile::fake()->image('photo.png'));
        $this->get(route('media-files.download', $media))->assertNotFound();
        $this->get(route('media-files.thumbnail', $media))->assertNotFound();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->get(route('media-files.download', $media))->assertNotFound();
    }

    public function test_public_access_can_be_revoked_without_moving_files(): void
    {
        $owner = $this->operator([...MediaPolicy::PERMISSIONS]);
        $media = $this->file($owner, 'public');
        $this->get(route('media-files.download', $media))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($owner)->put(route('media.update', $media), ['title' => 'Private now', 'visibility' => 'private'])->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('media-files.download', $media))->assertNotFound();
        Storage::disk('media')->assertExists($media->path);
    }

    public function test_publishing_requires_separate_permission(): void
    {
        $user = $this->operator();
        $media = $this->file($user);
        $this->actingAs($user)->put(route('media.update', $media), ['title' => 'Public', 'visibility' => 'public'])->assertSessionHasErrors('visibility');
        $this->post(route('media.store'), ['file' => UploadedFile::fake()->image('photo.png'), 'visibility' => 'public'])->assertSessionHasErrors('visibility');
        $this->assertSame('private', $media->fresh()->visibility);
    }

    public function test_unsafe_types_spoofed_extensions_and_large_uploads_are_rejected(): void
    {
        $this->actingAs($this->operator());
        foreach ([
            UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('script.php', '<?php echo "test";'),
            UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
        ] as $file) {
            $this->post(route('media.store'), ['file' => $file])->assertSessionHasErrors('file');
        }
        $temporary = tmpfile();
        fwrite($temporary, '<html><script>alert(1)</script></html>');
        try {
            $spoofed = new UploadedFile(stream_get_meta_data($temporary)['uri'], 'fake.pdf', 'application/pdf', null, true);
            $this->post(route('media.store'), ['file' => $spoofed])->assertSessionHasErrors('file');
        } finally {
            fclose($temporary);
        }
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_image_dimension_limit_and_metadata_validation(): void
    {
        config(['media.max_image_pixels' => 100]);
        $this->actingAs($this->operator())->post(route('media.store'), ['file' => UploadedFile::fake()->image('photo.png', 20, 20)])->assertSessionHasErrors('file');
        $this->post(route('media.store'), ['file' => UploadedFile::fake()->image('photo.png', 5, 5), 'alt_text' => str_repeat('a', 501)])->assertSessionHasErrors('alt_text');
    }

    public function test_failed_database_insert_cleans_up_uploaded_files(): void
    {
        $user = $this->operator();
        Media::creating(static function (): void {
            throw new RuntimeException('Database unavailable');
        });
        try {
            app(UploadMedia::class)->handle($user, UploadedFile::fake()->image('photo.png'));
            $this->fail('Expected a persistence failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database unavailable', $exception->getMessage());
            $this->assertSame([], Storage::disk('media')->allFiles());
        }
    }

    public function test_deletion_removes_original_thumbnail_and_record(): void
    {
        $user = $this->operator();
        $media = app(UploadMedia::class)->handle($user, UploadedFile::fake()->image('photo.png'));
        $this->actingAs($user)->from(route('media.index'))->delete(route('media.destroy', $media))->assertRedirect(route('media.index'));
        $this->assertModelMissing($media);
        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_storage_deletion_failure_preserves_the_record_for_retry(): void
    {
        $media = $this->file($this->operator());
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('media')->andReturn($disk);
        try {
            app(DeleteMedia::class)->handle($media);
            $this->fail('Expected a storage failure.');
        } catch (RuntimeException) {
            $this->assertModelExists($media);
        }
    }

    public function test_disabled_feature_blocks_public_files_and_library_without_deleting_data(): void
    {
        $user = $this->operator();
        $media = $this->file($user, 'public');
        config(['starter.features.media' => false]);
        $this->get(route('media-files.download', $media))->assertNotFound();
        $this->actingAs($user)->get(route('media.index'))->assertNotFound();
        $this->post(route('media.store'), [])->assertNotFound();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.can.manageMedia', false));
        $this->assertModelExists($media);
    }

    public function test_audio_and_video_uploads_can_be_played_inline_with_range_requests(): void
    {
        $this->actingAs($this->operator());
        $wav = "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xAC\x00\x00\x88\x58\x01\x00\x02\x00\x10\x00data\x00\x00\x00\x00";
        $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";
        $this->postJson(route('media.store'), ['file' => UploadedFile::fake()->createWithContent('voix.wav', $wav)->mimeType('audio/x-wav')])->assertCreated()
            ->assertJsonPath('media.is_audio', true)->assertJsonPath('media.is_video', false)->assertJsonPath('media.is_image', false);
        $this->postJson(route('media.store'), ['file' => UploadedFile::fake()->createWithContent('clip.mp4', $mp4)->mimeType('video/mp4')])->assertCreated()
            ->assertJsonPath('media.is_video', true)->assertJsonPath('media.mime_type', 'video/mp4')->assertJsonPath('media.thumbnail_url', null);
        $audio = Media::query()->where('mime_type', 'audio/x-wav')->sole();
        $video = Media::query()->where('mime_type', 'video/mp4')->sole();
        $this->getJson(route('media.index', ['type' => 'video']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.stream_url', route('media-files.stream', $video));
        $this->getJson(route('media.index', ['type' => 'audio']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $audio->id);
        $this->getJson(route('media.index', ['type' => 'document']))->assertJsonCount(0, 'data');

        $full = $this->get(route('media-files.stream', $video))->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')->assertHeader('Accept-Ranges', 'bytes')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename=clip.mp4')->assertHeader('Content-Length', (string) strlen($mp4));
        $this->assertSame($mp4, $full->streamedContent());
        $partial = $this->get(route('media-files.stream', $video), ['Range' => 'bytes=4-7'])->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 4-7/'.strlen($mp4))->assertHeader('Content-Length', '4');
        $this->assertSame('ftyp', $partial->streamedContent());
        $this->assertSame(substr($mp4, -8), $this->get(route('media-files.stream', $video), ['Range' => 'bytes=-8'])->assertStatus(206)->streamedContent());
        $this->assertSame(substr($mp4, 10), $this->get(route('media-files.stream', $video), ['Range' => 'bytes=10-'])->assertStatus(206)->streamedContent());
        $this->get(route('media-files.stream', $video), ['Range' => 'bytes=999-'])->assertStatus(416)->assertHeader('Content-Range', 'bytes */'.strlen($mp4));
        $this->get(route('media-files.download', $video))->assertDownload('clip.mp4');
    }

    public function test_only_playable_files_can_be_streamed_and_private_streams_stay_protected(): void
    {
        $owner = $this->operator();
        $document = $this->file($owner);
        $audio = Media::factory()->create(['uploaded_by' => $owner->id, 'original_name' => 'son.mp3', 'mime_type' => 'audio/mpeg']);
        Storage::disk('media')->put($audio->path, 'Example');
        $shared = Media::factory()->publiclyShared()->create(['uploaded_by' => $owner->id, 'original_name' => 'clip.mp4', 'mime_type' => 'video/mp4']);
        Storage::disk('media')->put($shared->path, 'Example');

        $this->get(route('media-files.stream', $audio))->assertNotFound();
        $this->get(route('media-files.stream', $shared))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->operator())->get(route('media-files.stream', $audio))->assertNotFound();
        $this->actingAs($owner)->get(route('media-files.stream', $audio))->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->get(route('media-files.stream', $document))->assertNotFound();
        $this->get(route('media-files.download', $document))->assertDownload('document.txt');
        $this->assertNull(app(MediaLibrary::class)->item($document)['stream_url']);
    }

    public function test_search_and_pagination_remain_scoped(): void
    {
        $user = $this->operator();
        Media::factory()->count(25)->create(['uploaded_by' => $user->id, 'title' => 'Report']);
        Media::factory()->create(['title' => 'Report']);
        $this->actingAs($user)->getJson(route('media.index', ['search' => 'Report']))->assertJsonPath('total', 25)->assertJsonCount(24, 'data');
        $this->getJson(route('media.index', ['search' => 'Report', 'page' => 2]))->assertJsonCount(1, 'data');
        $this->getJson(route('media.index', ['search' => 'missing']))->assertJsonCount(0, 'data');
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

    private function file(User $owner, string $visibility = 'private'): Media
    {
        $media = Media::factory()->create(['uploaded_by' => $owner->id, 'visibility' => $visibility]);
        Storage::disk('media')->put($media->path, 'Example');

        return $media;
    }
}
