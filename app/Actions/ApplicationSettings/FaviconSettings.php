<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Head\Enums\ImageType;
use Laravel\Head\Facades\Head;

class FaviconSettings
{
    public const KEY = 'branding.favicons';

    public const CACHE_KEY = 'application-settings.branding.favicons';

    public const COLOR_PATTERN = '/^#[0-9a-f]{6}$/';

    public const DEFAULT_THEME_COLOR = '#ff2d20';

    public const DEFAULT_BACKGROUND_COLOR = '#ffffff';

    public const DIRECTORY = 'application/favicons';

    public const SVG_DIRECTORY = 'application/favicons/svg';

    public const SOURCE_FILE = 'source.png';

    public const PNG_FILE = 'favicon-96x96.png';

    public const ICO_FILE = 'favicon.ico';

    public const APPLE_TOUCH_ICON_FILE = 'apple-touch-icon.png';

    public const MANIFEST_SMALL_ICON_FILE = 'web-app-manifest-192x192.png';

    public const MANIFEST_LARGE_ICON_FILE = 'web-app-manifest-512x512.png';

    public static function isRasterDirectory(string $path): bool
    {
        return preg_match('/^'.preg_quote(self::DIRECTORY, '/').'\/[a-z0-9]{26}$/', $path) === 1;
    }

    public static function isSvgPath(string $path): bool
    {
        return preg_match('/^'.preg_quote(self::SVG_DIRECTORY, '/').'\/[a-z0-9]{26}\.svg$/', $path) === 1;
    }

    /** @return array{directory: string|null, svg_path: string|null, theme_color: string, background_color: string} */
    public function get(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 300, static function (): array {
            $value = ApplicationSetting::query()->where('key', self::KEY)->value('value');
            $decoded = is_string($value) ? json_decode($value, true) : null;

            return is_array($decoded) ? $decoded : [];
        });

        $directory = $stored['directory'] ?? null;
        $svgPath = $stored['svg_path'] ?? null;
        $themeColor = $stored['theme_color'] ?? null;
        $backgroundColor = $stored['background_color'] ?? null;

        return [
            'directory' => is_string($directory) && self::isRasterDirectory($directory) ? $directory : null,
            'svg_path' => is_string($svgPath) && self::isSvgPath($svgPath) ? $svgPath : null,
            'theme_color' => is_string($themeColor) && preg_match(self::COLOR_PATTERN, $themeColor) === 1 ? $themeColor : self::DEFAULT_THEME_COLOR,
            'background_color' => is_string($backgroundColor) && preg_match(self::COLOR_PATTERN, $backgroundColor) === 1 ? $backgroundColor : self::DEFAULT_BACKGROUND_COLOR,
        ];
    }

    /**
     * Resolve the public URLs of the favicon set currently in use.
     *
     * A custom SVG is used on its own terms; the default SVG is only kept alongside the default raster set.
     *
     * @return array{custom: bool, customSvg: bool, svg: string|null, png: string, ico: string, appleTouchIcon: string, manifestSmallIcon: string, manifestLargeIcon: string}
     */
    public function icons(): array
    {
        $settings = $this->get();
        $directory = $settings['directory'];
        $disk = Storage::disk('public');
        $customSvg = $settings['svg_path'] === null ? null : $disk->url($settings['svg_path']);

        if ($directory === null) {
            return [
                'custom' => false,
                'customSvg' => $customSvg !== null,
                'svg' => $customSvg ?? asset('favicon.svg'),
                'png' => asset(self::PNG_FILE),
                'ico' => asset(self::ICO_FILE),
                'appleTouchIcon' => asset(self::APPLE_TOUCH_ICON_FILE),
                'manifestSmallIcon' => asset(self::MANIFEST_SMALL_ICON_FILE),
                'manifestLargeIcon' => asset(self::MANIFEST_LARGE_ICON_FILE),
            ];
        }

        return [
            'custom' => true,
            'customSvg' => $customSvg !== null,
            'svg' => $customSvg,
            'png' => $disk->url($directory.'/'.self::PNG_FILE),
            'ico' => $disk->url($directory.'/'.self::ICO_FILE),
            'appleTouchIcon' => $disk->url($directory.'/'.self::APPLE_TOUCH_ICON_FILE),
            'manifestSmallIcon' => $disk->url($directory.'/'.self::MANIFEST_SMALL_ICON_FILE),
            'manifestLargeIcon' => $disk->url($directory.'/'.self::MANIFEST_LARGE_ICON_FILE),
        ];
    }

    /**
     * Add the favicon links, theme color and manifest link to the current request's head.
     */
    public function apply(): void
    {
        $icons = $this->icons();

        Head::themeColor($this->get()['theme_color']);

        if ($icons['svg'] !== null) {
            Head::favicon($icons['svg'], type: ImageType::Svg, sizes: 'any');
        }

        Head::favicon($icons['png'], type: ImageType::Png, sizes: '96x96');
        Head::link('shortcut icon', $icons['ico'], ['type' => ImageType::Ico->value]);
        Head::appleTouchIcon($icons['appleTouchIcon'], sizes: '180x180');
        Head::manifest(route('web-manifest'));
    }

    /**
     * Build the web application manifest for the configured favicon set.
     *
     * @return array{name: string, short_name: string, start_url: string, icons: list<array{src: string, sizes: string, type: string, purpose: string}>, theme_color: string, background_color: string, display: string}
     */
    public function manifest(string $applicationName): array
    {
        $settings = $this->get();
        $icons = $this->icons();

        return [
            'name' => $applicationName,
            'short_name' => $applicationName,
            'start_url' => '/',
            'icons' => [
                ['src' => $icons['manifestSmallIcon'], 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => $icons['manifestLargeIcon'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'theme_color' => $settings['theme_color'],
            'background_color' => $settings['background_color'],
            'display' => 'standalone',
        ];
    }
}
