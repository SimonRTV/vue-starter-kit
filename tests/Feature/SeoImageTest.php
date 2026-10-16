<?php

namespace Tests\Feature;

use App\Actions\ApplicationSettings\SeoSettings;
use App\Actions\Pages\ForceDeletePage;
use App\Models\ApplicationSetting;
use App\Models\Page;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoImageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_only_administrators_can_upload_and_remove_social_images(): void
    {
        $page = Page::factory()->create();
        $targets = ['defaults', 'home', (string) $page->id];
        foreach ($targets as $target) {
            $this->post(route('seo.images.store', [$target, 'og_image']), ['image' => $this->image()])->assertRedirect(route('login'));
            $this->delete(route('seo.images.destroy', [$target, 'og_image']))->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create());
        foreach ($targets as $target) {
            foreach (['og_image', 'twitter_image'] as $field) {
                $this->post(route('seo.images.store', [$target, $field]), ['image' => $this->image()])->assertForbidden();
                $this->delete(route('seo.images.destroy', [$target, $field]))->assertForbidden();
            }
        }
        $this->assertNull($page->fresh()->seo);
        $this->assertDatabaseCount('application_settings', 0);
        Storage::disk('public')->assertDirectoryEmpty('seo/images');
    }

    public function test_default_image_upload_is_stored_publicly_and_used_in_meta_tags(): void
    {
        $this->administrator();
        $this->put(route('seo.update', 'defaults'), [...app(SeoSettings::class)->defaults(), 'og_image' => 'https://example.com/old.jpg', 'og_image_alt' => 'Notre équipe']);
        $this->post(route('seo.images.store', ['defaults', 'og_image']), ['image' => $this->image()])
            ->assertSessionHasNoErrors()->assertRedirect(route('seo.edit', 'defaults'));

        $seo = json_decode(ApplicationSetting::query()->where('key', 'seo.defaults')->sole()->value, true);
        $path = $seo['og_image_path'];
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('seo/images/', $path);
        $this->assertSame('', $seo['og_image']);
        $this->assertSame('Notre équipe', $seo['og_image_alt']);
        $url = $this->publicUrl($path);
        $this->assertStringStartsWith('http://', $url);

        $this->get(route('seo.edit', 'defaults'))->assertInertia(fn (Assert $view) => $view
            ->where('images.og_image', $url)->where('images.twitter_image', null)
            ->where('defaultImages.og_image', $url)->where('settings.og_image', '')->missing('settings.og_image_path'));
        $this->get(route('seo.edit', 'home'))->assertInertia(fn (Assert $view) => $view
            ->where('images.og_image', null)->where('defaultImages.og_image', $url));
        $this->get(route('home'))->assertOk()
            ->assertSee('property="og:image" content="'.$url.'"', false)
            ->assertSee('property="og:image:alt" content="Notre équipe"', false)
            ->assertSee('name="twitter:image" content="'.$url.'"', false)
            ->assertDontSee('https://example.com/old.jpg');
    }

    public function test_uploads_can_be_replaced_and_removed_and_delete_their_files(): void
    {
        $this->administrator();
        $this->post(route('seo.images.store', ['home', 'twitter_image']), ['image' => $this->image('first.png')])->assertSessionHasNoErrors();
        $first = $this->storedPath('home', 'twitter_image');
        $this->post(route('seo.images.store', ['home', 'twitter_image']), ['image' => $this->image('second.webp')])->assertSessionHasNoErrors();
        $second = $this->storedPath('home', 'twitter_image');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->get(route('home'))->assertSee('name="twitter:image" content="'.$this->publicUrl($second).'"', false)
            ->assertDontSee('property="og:image"', false)->assertDontSee('name="twitter:image:alt"', false);

        $this->delete(route('seo.images.destroy', ['home', 'twitter_image']))->assertRedirect(route('seo.edit', 'home'));
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($this->storedPath('home', 'twitter_image'));
        $this->get(route('home'))->assertDontSee('name="twitter:image"', false);
        $this->delete(route('seo.images.destroy', ['home', 'twitter_image']))->assertRedirect(route('seo.edit', 'home'));
    }

    public function test_page_uploads_override_defaults_survive_form_saves_and_yield_to_pasted_urls(): void
    {
        $this->administrator();
        $page = Page::factory()->published()->create();
        $this->post(route('seo.images.store', ['defaults', 'og_image']), ['image' => $this->image()]);
        $this->post(route('seo.images.store', [$page->id, 'og_image']), ['image' => $this->image('page.jpg')])
            ->assertSessionHasNoErrors()->assertRedirect(route('seo.edit', $page->id));
        $path = $page->fresh()->seo['og_image_path'];
        $url = $this->publicUrl($path);

        $this->get(route('seo.edit', $page->id))->assertInertia(fn (Assert $view) => $view->where('images.og_image', $url));
        $this->get(route('content.show', $page->slug))->assertSee('property="og:image" content="'.$url.'"', false)
            ->assertSee('name="twitter:image" content="'.$url.'"', false);

        $this->put(route('seo.update', $page->id), ['meta_title' => 'Titre', 'og_image' => ''])->assertSessionHasNoErrors();
        $this->assertSame($path, $page->fresh()->seo['og_image_path']);
        $this->assertSame('Titre', $page->fresh()->seo['meta_title']);
        $this->get(route('content.show', $page->slug))->assertSee('property="og:image" content="'.$url.'"', false);

        $this->put(route('seo.update', $page->id), ['og_image' => 'https://example.com/pasted.jpg'])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('og_image_path', $page->fresh()->seo);
        Storage::disk('public')->assertMissing($path);
        $this->get(route('content.show', $page->slug))->assertSee('property="og:image" content="https://example.com/pasted.jpg"', false);
    }

    public function test_invalid_uploads_are_rejected_without_storing_files(): void
    {
        $this->administrator();
        $invalid = [
            null,
            UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630"></svg>'),
            $this->image('heavy.jpg')->size(5121),
            $this->image('small.png', 199, 630),
            $this->image('large.png', 4097, 630),
        ];
        foreach ($invalid as $file) {
            $this->post(route('seo.images.store', ['defaults', 'og_image']), ['image' => $file])->assertSessionHasErrors('image');
        }
        $this->post(route('seo.images.store', ['defaults', 'og_image']), ['image' => $this->image('small.png', 100, 100)])
            ->assertSessionHasErrors(['image' => 'L’image doit mesurer entre 200 × 200 et 4096 × 4096 pixels. Le format 1200 × 630 pixels est recommandé.']);
        $this->post(route('seo.images.store', ['defaults', 'og_image']), ['image' => $this->image('minimum.png', 200, 200)])->assertSessionHasNoErrors();
        $this->assertCount(1, Storage::disk('public')->allFiles('seo/images'));
    }

    public function test_unknown_targets_fields_and_disabled_features_return_not_found(): void
    {
        $this->administrator();
        $page = Page::factory()->create();
        $this->post('/seo/defaults/images/canonical_url', ['image' => $this->image()])->assertNotFound();
        $this->post(route('seo.images.store', ['999999', 'og_image']), ['image' => $this->image()])->assertNotFound();
        $this->delete(route('seo.images.destroy', ['999999', 'og_image']))->assertNotFound();
        config(['starter.features.pages' => false]);
        $this->post(route('seo.images.store', [$page->id, 'og_image']), ['image' => $this->image()])->assertNotFound();
        config(['starter.features.public_site' => false]);
        $this->post(route('seo.images.store', ['home', 'og_image']), ['image' => $this->image()])->assertNotFound();
        $this->delete(route('seo.images.destroy', ['home', 'og_image']))->assertNotFound();
        Storage::disk('public')->assertDirectoryEmpty('seo/images');
    }

    public function test_permanently_deleting_a_page_removes_its_social_images(): void
    {
        $this->administrator();
        $page = Page::factory()->create();
        $this->post(route('seo.images.store', [$page->id, 'og_image']), ['image' => $this->image()]);
        $this->post(route('seo.images.store', [$page->id, 'twitter_image']), ['image' => $this->image()]);
        $paths = Storage::disk('public')->allFiles('seo/images');
        $this->assertCount(2, $paths);

        $page->delete();
        app(ForceDeletePage::class)->handle($page);

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    private function image(string $name = 'share.jpg', int $width = 1200, int $height = 630): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    private function storedPath(string $target, string $field): ?string
    {
        $seo = json_decode(ApplicationSetting::query()->where('key', 'seo.'.$target)->sole()->value, true);

        return $seo[$field.'_path'] ?? null;
    }

    private function publicUrl(string $path): string
    {
        return url(Storage::disk('public')->url($path));
    }

    private function administrator(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(RolePolicy::ADMINISTRATOR_ROLE, 'web'));
        $this->actingAs($user);
    }
}
