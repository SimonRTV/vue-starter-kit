<?php

namespace Tests\Feature\Settings;

use App\Actions\ApplicationSettings\FaviconSettings;
use App\Actions\ApplicationSettings\GenerateFavicons;
use App\Models\ApplicationSetting;
use App\Models\User;
use App\Policies\RolePolicy;
use GdImage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FaviconSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('favicons.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrators_cannot_view_or_change_the_favicons(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('favicons.edit'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('icon.png', 512, 512)])
            ->assertForbidden();
        $this->actingAs($user)
            ->put(route('favicons.colors.update'), ['theme_color' => '#000000', 'background_color' => '#000000'])
            ->assertForbidden();
        $this->actingAs($user)->delete(route('favicons.destroy'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('favicons.svg.update'), ['svg' => $this->svgUpload('<svg xmlns="http://www.w3.org/2000/svg"/>')])
            ->assertForbidden();
        $this->actingAs($user)->delete(route('favicons.svg.destroy'))->assertForbidden();

        $this->assertSame(0, ApplicationSetting::query()->count());
        Storage::disk('public')->assertDirectoryEmpty(FaviconSettings::DIRECTORY);
    }

    public function test_administrators_see_the_default_favicons_and_colors(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('favicons.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Favicons')
                ->where('icons.custom', false)
                ->where('icons.svg', asset('favicon.svg'))
                ->where('icons.ico', asset('favicon.ico'))
                ->where('colors.theme_color', FaviconSettings::DEFAULT_THEME_COLOR)
                ->where('colors.background_color', FaviconSettings::DEFAULT_BACKGROUND_COLOR)
                ->where('applicationName', config('app.name')),
            );
    }

    public function test_an_administrator_can_generate_the_favicon_set_from_one_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->administrator())
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('icon.png', 800, 600)])
            ->assertRedirect(route('favicons.edit'))
            ->assertInertiaFlash('toast.type', 'success');

        $directory = app(FaviconSettings::class)->get()['directory'];
        $this->assertIsString($directory);

        $disk = Storage::disk('public');
        $expectedSizes = [
            FaviconSettings::SOURCE_FILE => 512,
            FaviconSettings::PNG_FILE => 96,
            FaviconSettings::APPLE_TOUCH_ICON_FILE => 180,
            FaviconSettings::MANIFEST_SMALL_ICON_FILE => 192,
            FaviconSettings::MANIFEST_LARGE_ICON_FILE => 512,
        ];

        foreach ($expectedSizes as $file => $size) {
            $dimensions = getimagesizefromstring((string) $disk->get($directory.'/'.$file));
            $this->assertIsArray($dimensions, $file);
            $this->assertSame([$size, $size, IMAGETYPE_PNG], [$dimensions[0], $dimensions[1], $dimensions[2]], $file);
        }

        $ico = (string) $disk->get($directory.'/'.FaviconSettings::ICO_FILE);
        /** @var array{reserved: int, type: int, count: int} $header */
        $header = unpack('vreserved/vtype/vcount', $ico);
        $this->assertSame(['reserved' => 0, 'type' => 1, 'count' => count(GenerateFavicons::ICO_SIZES)], $header);

        foreach (GenerateFavicons::ICO_SIZES as $index => $size) {
            /** @var array{width: int, height: int, length: int, offset: int} $entry */
            $entry = unpack('Cwidth/Cheight/x6/Vlength/Voffset', substr($ico, 6 + ($index * 16), 16));
            $this->assertSame([$size, $size], [$entry['width'], $entry['height']]);
            $this->assertSame("\x89PNG", substr($ico, $entry['offset'], 4));
        }
    }

    public function test_generated_icons_keep_transparency_and_apply_the_background_color(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->put(route('favicons.colors.update'), ['theme_color' => '#112233', 'background_color' => '#336699'])
            ->assertRedirect(route('favicons.edit'));
        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => $this->transparentImage()])
            ->assertRedirect(route('favicons.edit'));

        $directory = (string) app(FaviconSettings::class)->get()['directory'];
        $disk = Storage::disk('public');

        $favicon = imagecreatefromstring((string) $disk->get($directory.'/'.FaviconSettings::PNG_FILE));
        $appleTouchIcon = imagecreatefromstring((string) $disk->get($directory.'/'.FaviconSettings::APPLE_TOUCH_ICON_FILE));
        $this->assertNotFalse($favicon);
        $this->assertNotFalse($appleTouchIcon);

        $this->assertSame(127, $this->pixel($favicon)['alpha']);
        $this->assertSame(
            ['red' => 0x33, 'green' => 0x66, 'blue' => 0x99, 'alpha' => 0],
            $this->pixel($appleTouchIcon),
        );
    }

    public function test_uploading_a_new_image_replaces_the_previous_set(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('first.png', 512, 512)]);
        $firstDirectory = (string) app(FaviconSettings::class)->get()['directory'];

        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('second.png', 512, 512)]);
        $secondDirectory = (string) app(FaviconSettings::class)->get()['directory'];

        $this->assertNotSame($firstDirectory, $secondDirectory);
        Storage::disk('public')->assertMissing($firstDirectory.'/'.FaviconSettings::ICO_FILE);
        Storage::disk('public')->assertExists($secondDirectory.'/'.FaviconSettings::ICO_FILE);
        $this->assertSame(1, ApplicationSetting::query()->where('key', FaviconSettings::KEY)->count());
    }

    public function test_the_favicon_source_is_validated(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $invalidUploads = [
            'missing' => null,
            'too small' => UploadedFile::fake()->image('small.png', 256, 256),
            'too large' => UploadedFile::fake()->image('large.png', 2049, 2049),
            'svg' => UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            'gif' => UploadedFile::fake()->image('icon.gif', 512, 512),
            'not an image' => UploadedFile::fake()->create('icon.pdf', 10, 'application/pdf'),
        ];

        foreach ($invalidUploads as $case => $upload) {
            $this->actingAs($administrator)
                ->from(route('favicons.edit'))
                ->post(route('favicons.update'), ['favicon' => $upload])
                ->assertRedirect(route('favicons.edit'))
                ->assertSessionHasErrors('favicon', errorBag: 'default');
        }

        $this->assertSame(0, ApplicationSetting::query()->count());
        Storage::disk('public')->assertDirectoryEmpty(FaviconSettings::DIRECTORY);
    }

    public function test_colors_are_normalized_and_validated(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->put(route('favicons.colors.update'), ['theme_color' => ' #AABBCC ', 'background_color' => '#000000'])
            ->assertRedirect(route('favicons.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['directory' => null, 'svg_path' => null, 'theme_color' => '#aabbcc', 'background_color' => '#000000'],
            app(FaviconSettings::class)->get(),
        );

        foreach (['red', '#abc', '#gggggg', 'aabbcc', ''] as $invalidColor) {
            $this->actingAs($administrator)
                ->from(route('favicons.edit'))
                ->put(route('favicons.colors.update'), ['theme_color' => $invalidColor, 'background_color' => $invalidColor])
                ->assertSessionHasErrors(['theme_color', 'background_color']);
        }

        $this->assertSame('#aabbcc', app(FaviconSettings::class)->get()['theme_color']);
    }

    public function test_changing_the_background_color_regenerates_custom_icons(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => $this->transparentImage()]);
        $previousDirectory = (string) app(FaviconSettings::class)->get()['directory'];

        $this->actingAs($administrator)
            ->put(route('favicons.colors.update'), ['theme_color' => '#ff2d20', 'background_color' => '#ff0000'])
            ->assertRedirect(route('favicons.edit'));

        $directory = (string) app(FaviconSettings::class)->get()['directory'];
        $this->assertNotSame($previousDirectory, $directory);
        Storage::disk('public')->assertMissing($previousDirectory.'/'.FaviconSettings::SOURCE_FILE);

        $appleTouchIcon = imagecreatefromstring((string) Storage::disk('public')->get($directory.'/'.FaviconSettings::APPLE_TOUCH_ICON_FILE));
        $this->assertNotFalse($appleTouchIcon);
        $this->assertSame(
            ['red' => 0xFF, 'green' => 0, 'blue' => 0, 'alpha' => 0],
            $this->pixel($appleTouchIcon),
        );

        $this->actingAs($administrator)
            ->put(route('favicons.colors.update'), ['theme_color' => '#000000', 'background_color' => '#ff0000']);
        $this->assertSame($directory, app(FaviconSettings::class)->get()['directory']);
    }

    public function test_an_administrator_can_restore_the_default_favicons_and_keep_the_colors(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->put(route('favicons.colors.update'), ['theme_color' => '#123456', 'background_color' => '#654321']);
        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('icon.png', 512, 512)]);
        $directory = (string) app(FaviconSettings::class)->get()['directory'];

        $this->actingAs($administrator)
            ->delete(route('favicons.destroy'))
            ->assertRedirect(route('favicons.edit'));

        Storage::disk('public')->assertMissing($directory.'/'.FaviconSettings::ICO_FILE);
        $this->assertSame(
            ['directory' => null, 'svg_path' => null, 'theme_color' => '#123456', 'background_color' => '#654321'],
            app(FaviconSettings::class)->get(),
        );
    }

    public function test_restoring_defaults_without_a_custom_set_is_harmless(): void
    {
        $this->actingAs($this->administrator())
            ->delete(route('favicons.destroy'))
            ->assertRedirect(route('favicons.edit'));

        $this->assertSame(0, ApplicationSetting::query()->count());
    }

    public function test_the_document_head_uses_the_custom_favicons_and_theme_color(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->put(route('favicons.colors.update'), ['theme_color' => '#0a0b0c', 'background_color' => '#ffffff']);
        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('icon.png', 512, 512)]);
        $icons = app(FaviconSettings::class)->icons();

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.$icons['png'].'"', false)
            ->assertSee('href="'.$icons['ico'].'"', false)
            ->assertSee('href="'.$icons['appleTouchIcon'].'"', false)
            ->assertSee('href="'.route('web-manifest').'"', false)
            ->assertSee('name="theme-color" content="#0a0b0c"', false)
            ->assertDontSee(asset('favicon.svg'), false)
            ->assertDontSee('href="'.asset('favicon.ico').'"', false)
            ->assertDontSee('#ff2d20', false);
    }

    public function test_the_web_manifest_describes_the_default_icons(): void
    {
        $this->get(route('web-manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertExactJson([
                'name' => config('app.name'),
                'short_name' => config('app.name'),
                'start_url' => '/',
                'icons' => [
                    ['src' => asset('web-app-manifest-192x192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                    ['src' => asset('web-app-manifest-512x512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ],
                'theme_color' => FaviconSettings::DEFAULT_THEME_COLOR,
                'background_color' => FaviconSettings::DEFAULT_BACKGROUND_COLOR,
                'display' => 'standalone',
            ]);
    }

    public function test_the_web_manifest_uses_the_custom_icons_and_colors(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->put(route('favicons.colors.update'), ['theme_color' => '#101010', 'background_color' => '#202020']);
        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('icon.png', 512, 512)]);
        $icons = app(FaviconSettings::class)->icons();

        $this->get(route('web-manifest'))
            ->assertOk()
            ->assertJsonPath('icons.0.src', $icons['manifestSmallIcon'])
            ->assertJsonPath('icons.1.src', $icons['manifestLargeIcon'])
            ->assertJsonPath('theme_color', '#101010')
            ->assertJsonPath('background_color', '#202020');
    }

    public function test_tampered_settings_fall_back_to_safe_defaults(): void
    {
        ApplicationSetting::query()->create([
            'key' => FaviconSettings::KEY,
            'value' => json_encode(['directory' => '../../etc', 'svg_path' => 'application/favicons/../../.env', 'theme_color' => 'red', 'background_color' => '#fff']),
        ]);

        $this->assertSame(
            ['directory' => null, 'svg_path' => null, 'theme_color' => FaviconSettings::DEFAULT_THEME_COLOR, 'background_color' => FaviconSettings::DEFAULT_BACKGROUND_COLOR],
            app(FaviconSettings::class)->get(),
        );
    }

    public function test_an_administrator_can_upload_an_svg_favicon(): void
    {
        Storage::fake('public');

        $this->actingAs($this->administrator())
            ->post(route('favicons.svg.update'), ['svg' => $this->svgUpload(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><style>@media (prefers-color-scheme: dark) { circle { fill: #fff; } }</style><circle cx="16" cy="16" r="12" fill="#ff2d20"/></svg>',
            )])
            ->assertRedirect(route('favicons.edit'))
            ->assertInertiaFlash('toast.type', 'success');

        $svgPath = app(FaviconSettings::class)->get()['svg_path'];
        $this->assertIsString($svgPath);

        $stored = (string) Storage::disk('public')->get($svgPath);
        $this->assertStringContainsString('<circle cx="16" cy="16" r="12" fill="#ff2d20"/>', $stored);
        $this->assertStringContainsString('prefers-color-scheme: dark', $stored);

        $icons = app(FaviconSettings::class)->icons();
        $this->assertTrue($icons['customSvg']);
        $this->assertSame(Storage::disk('public')->url($svgPath), $icons['svg']);
    }

    public function test_svg_favicons_are_stripped_of_scripting(): void
    {
        Storage::fake('public');

        $this->actingAs($this->administrator())
            ->post(route('favicons.svg.update'), ['svg' => $this->svgUpload(<<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)" viewBox="0 0 32 32">
                    <script>alert(2)</script>
                    <foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject>
                    <defs><circle id="dot" r="4"/></defs>
                    <a href="javascript:alert(3)"><rect width="10" height="10" onclick="alert(4)"/></a>
                    <use xlink:href="#dot"/>
                    <image href="https://evil.example/tracker.png"/>
                    <set attributeName="onmouseover" to="alert(5)"/>
                    <animate attributeName="href" values="javascript:alert(6)"/>
                </svg>
                SVG)])
            ->assertRedirect(route('favicons.edit'))
            ->assertSessionHasNoErrors();

        $stored = (string) Storage::disk('public')->get((string) app(FaviconSettings::class)->get()['svg_path']);

        foreach (['alert', 'onload', 'onclick', '<script', 'foreignObject', 'javascript:', 'evil.example', '<set', '<animate'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $stored);
        }

        $this->assertStringContainsString('xlink:href="#dot"', $stored);
        $this->assertStringContainsString('<rect width="10" height="10"/>', $stored);
    }

    public function test_the_svg_favicon_is_validated(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $invalidUploads = [
            'missing' => null,
            'png' => UploadedFile::fake()->image('icon.png', 512, 512),
            'malformed' => $this->svgUpload('<svg xmlns="http://www.w3.org/2000/svg"><circle>'),
            'not svg root' => $this->svgUpload('<html xmlns="http://www.w3.org/1999/xhtml"/>'),
            'doctype entities' => $this->svgUpload('<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'),
            'too large' => UploadedFile::fake()->create('icon.svg', 513, 'image/svg+xml'),
        ];

        foreach ($invalidUploads as $case => $upload) {
            $this->actingAs($administrator)
                ->from(route('favicons.edit'))
                ->post(route('favicons.svg.update'), ['svg' => $upload])
                ->assertRedirect(route('favicons.edit'))
                ->assertSessionHasErrors('svg');
        }

        $this->assertSame(0, ApplicationSetting::query()->count());
        Storage::disk('public')->assertDirectoryEmpty(FaviconSettings::SVG_DIRECTORY);
    }

    public function test_the_svg_favicon_is_independent_from_the_generated_set(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('favicons.svg.update'), ['svg' => $this->svgUpload('<svg xmlns="http://www.w3.org/2000/svg"/>')]);
        $firstSvgPath = (string) app(FaviconSettings::class)->get()['svg_path'];

        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('icon.png', 512, 512)]);
        $this->actingAs($administrator)->delete(route('favicons.destroy'));
        $this->assertSame($firstSvgPath, app(FaviconSettings::class)->get()['svg_path']);
        Storage::disk('public')->assertExists($firstSvgPath);

        $this->actingAs($administrator)
            ->post(route('favicons.svg.update'), ['svg' => $this->svgUpload('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>')]);
        $secondSvgPath = (string) app(FaviconSettings::class)->get()['svg_path'];
        $this->assertNotSame($firstSvgPath, $secondSvgPath);
        Storage::disk('public')->assertMissing($firstSvgPath);

        $this->actingAs($administrator)
            ->delete(route('favicons.svg.destroy'))
            ->assertRedirect(route('favicons.edit'));
        Storage::disk('public')->assertMissing($secondSvgPath);
        $this->assertNull(app(FaviconSettings::class)->get()['svg_path']);
        $this->assertSame(asset('favicon.svg'), app(FaviconSettings::class)->icons()['svg']);
    }

    public function test_the_document_head_lists_the_custom_svg_with_the_generated_set(): void
    {
        Storage::fake('public');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('favicons.update'), ['favicon' => UploadedFile::fake()->image('icon.png', 512, 512)]);
        $this->actingAs($administrator)
            ->post(route('favicons.svg.update'), ['svg' => $this->svgUpload('<svg xmlns="http://www.w3.org/2000/svg"/>')]);
        $icons = app(FaviconSettings::class)->icons();

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('rel="icon" href="'.$icons['svg'].'" type="image/svg+xml" sizes="any"', false)
            ->assertSee('href="'.$icons['ico'].'"', false)
            ->assertDontSee(asset('favicon.svg'), false);
    }

    private function svgUpload(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('icon.svg', $contents);
    }

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    private function pixel(GdImage $image): array
    {
        $color = imagecolorat($image, 0, 0);
        $this->assertIsInt($color);

        return imagecolorsforindex($image, $color);
    }

    private function transparentImage(): UploadedFile
    {
        $image = imagecreatetruecolor(600, 600);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, 599, 599, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse($image, 300, 300, 300, 300, (int) imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent('icon.png', (string) ob_get_clean());
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
