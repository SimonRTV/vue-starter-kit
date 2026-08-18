<?php

namespace App\Models;

use Database\Factories\ApplicationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value'])]
class ApplicationSetting extends Model
{
    public const FRONTEND_NAVIGATION_DESTINATION_PATTERN = '/^(?:#[A-Za-z][A-Za-z0-9_:.-]*|\/(?!\/)[^\s\\\\]*)$/u';

    public const INTERNAL_SIDEBAR_FOOTER_LINK_PATTERN = '/^\/(?!\/)[^\s\\\\]*$/u';

    /**
     * @var list<array{
     *     type: 'link'|'group',
     *     label: string,
     *     url: string|null,
     *     children: list<array{label: string, url: string, description: string}>
     * }>
     */
    public const DEFAULT_FRONTEND_NAVIGATION = [
        [
            'type' => 'group',
            'label' => 'Platform',
            'url' => null,
            'children' => [
                [
                    'label' => 'Simple workflows',
                    'url' => '#simple-workflows',
                    'description' => 'Keep the next step obvious and the work moving.',
                ],
                [
                    'label' => 'Shared visibility',
                    'url' => '#shared-visibility',
                    'description' => 'Give everyone the right context at the right time.',
                ],
                [
                    'label' => 'Built to grow',
                    'url' => '#built-to-grow',
                    'description' => 'Add structure as your team and ambitions evolve.',
                ],
            ],
        ],
        [
            'type' => 'link',
            'label' => 'Features',
            'url' => '#features',
            'children' => [],
        ],
        [
            'type' => 'link',
            'label' => 'How it works',
            'url' => '#workflow',
            'children' => [],
        ],
        [
            'type' => 'group',
            'label' => 'Company',
            'url' => null,
            'children' => [
                [
                    'label' => 'About',
                    'url' => '#about',
                    'description' => '',
                ],
            ],
        ],
    ];

    /** @var list<array{title: string, url: string}> */
    public const DEFAULT_SIDEBAR_FOOTER_LINKS = [
        [
            'title' => 'Dépôt de code',
            'url' => 'https://github.com/laravel/vue-starter-kit',
        ],
        [
            'title' => 'Documentation',
            'url' => 'https://laravel.com/docs/starter-kits#vue',
        ],
    ];

    public const FULL_LOGO_PATH = 'branding.full_logo_path';

    public const FRONTEND_NAVIGATION = 'navigation.frontend';

    public const ICON_PATH = 'branding.logo_path';

    public const SIDEBAR_FOOTER_LINKS = 'navigation.sidebar_footer_links';

    /** @use HasFactory<ApplicationSettingFactory> */
    use HasFactory;

    public static function iconPath(): ?string
    {
        return self::imagePath(self::ICON_PATH);
    }

    public static function iconUrl(): ?string
    {
        return self::imageUrl(self::iconPath());
    }

    public static function fullLogoPath(): ?string
    {
        return self::imagePath(self::FULL_LOGO_PATH);
    }

    public static function fullLogoUrl(): ?string
    {
        return self::imageUrl(self::fullLogoPath());
    }

    /**
     * @return list<array{
     *     type: 'link'|'group',
     *     label: string,
     *     url: string|null,
     *     children: list<array{label: string, url: string, description: string}>
     * }>
     */
    public static function frontendNavigation(): array
    {
        $storedNavigation = static::query()
            ->where('key', self::FRONTEND_NAVIGATION)
            ->value('value');

        if (! is_string($storedNavigation)) {
            return self::DEFAULT_FRONTEND_NAVIGATION;
        }

        try {
            $decodedNavigation = json_decode($storedNavigation, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::DEFAULT_FRONTEND_NAVIGATION;
        }

        if (! is_array($decodedNavigation) || ! array_is_list($decodedNavigation) || count($decodedNavigation) > 10) {
            return self::DEFAULT_FRONTEND_NAVIGATION;
        }

        $navigation = [];

        foreach ($decodedNavigation as $decodedItem) {
            $item = self::normalizeFrontendNavigationItem($decodedItem);

            if ($item === null) {
                return self::DEFAULT_FRONTEND_NAVIGATION;
            }

            $navigation[] = $item;
        }

        return $navigation;
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    public static function sidebarFooterLinks(): array
    {
        $storedLinks = static::query()
            ->where('key', self::SIDEBAR_FOOTER_LINKS)
            ->value('value');

        if (! is_string($storedLinks)) {
            return self::DEFAULT_SIDEBAR_FOOTER_LINKS;
        }

        try {
            $decodedLinks = json_decode($storedLinks, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::DEFAULT_SIDEBAR_FOOTER_LINKS;
        }

        if (! is_array($decodedLinks) || ! array_is_list($decodedLinks)) {
            return self::DEFAULT_SIDEBAR_FOOTER_LINKS;
        }

        $links = [];

        foreach ($decodedLinks as $decodedLink) {
            if (! is_array($decodedLink)) {
                return self::DEFAULT_SIDEBAR_FOOTER_LINKS;
            }

            $title = $decodedLink['title'] ?? null;
            $url = $decodedLink['url'] ?? null;

            if (
                ! is_string($title)
                || $title === ''
                || ! is_string($url)
                || ! self::isValidSidebarFooterUrl($url)
            ) {
                return self::DEFAULT_SIDEBAR_FOOTER_LINKS;
            }

            $links[] = [
                'title' => $title,
                'url' => $url,
            ];
        }

        return $links;
    }

    public static function isValidSidebarFooterUrl(string $url): bool
    {
        if (preg_match(self::INTERNAL_SIDEBAR_FOOTER_LINK_PATTERN, $url) === 1) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && Str::startsWith(Str::lower($url), ['http://', 'https://']);
    }

    public static function isValidFrontendNavigationUrl(string $url): bool
    {
        if (preg_match(self::FRONTEND_NAVIGATION_DESTINATION_PATTERN, $url) === 1) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && Str::startsWith(Str::lower($url), ['http://', 'https://']);
    }

    /**
     * @return array{
     *     type: 'link'|'group',
     *     label: string,
     *     url: string|null,
     *     children: list<array{label: string, url: string, description: string}>
     * }|null
     */
    private static function normalizeFrontendNavigationItem(mixed $decodedItem): ?array
    {
        if (! is_array($decodedItem)) {
            return null;
        }

        $type = $decodedItem['type'] ?? null;
        $label = $decodedItem['label'] ?? null;
        $url = $decodedItem['url'] ?? null;
        $decodedChildren = $decodedItem['children'] ?? null;

        if (
            ! in_array($type, ['link', 'group'], true)
            || ! is_string($label)
            || $label === ''
            || Str::length($label) > 80
            || ! is_array($decodedChildren)
            || ! array_is_list($decodedChildren)
            || count($decodedChildren) > 8
        ) {
            return null;
        }

        if ($type === 'link') {
            if (! is_string($url) || ! self::isValidFrontendNavigationUrl($url) || $decodedChildren !== []) {
                return null;
            }

            return [
                'type' => 'link',
                'label' => $label,
                'url' => $url,
                'children' => [],
            ];
        }

        if ($url !== null || $decodedChildren === []) {
            return null;
        }

        $children = [];

        foreach ($decodedChildren as $decodedChild) {
            if (! is_array($decodedChild)) {
                return null;
            }

            $childLabel = $decodedChild['label'] ?? null;
            $childUrl = $decodedChild['url'] ?? null;
            $childDescription = $decodedChild['description'] ?? null;

            if (
                ! is_string($childLabel)
                || $childLabel === ''
                || Str::length($childLabel) > 80
                || ! is_string($childUrl)
                || ! self::isValidFrontendNavigationUrl($childUrl)
                || ! is_string($childDescription)
                || Str::length($childDescription) > 160
            ) {
                return null;
            }

            $children[] = [
                'label' => $childLabel,
                'url' => $childUrl,
                'description' => $childDescription,
            ];
        }

        return [
            'type' => 'group',
            'label' => $label,
            'url' => null,
            'children' => $children,
        ];
    }

    private static function imagePath(string $key): ?string
    {
        $imagePath = static::query()
            ->where('key', $key)
            ->value('value');

        return is_string($imagePath) && $imagePath !== ''
            ? $imagePath
            : null;
    }

    private static function imageUrl(?string $imagePath): ?string
    {
        return $imagePath === null
            ? null
            : Storage::disk('public')->url($imagePath);
    }
}
