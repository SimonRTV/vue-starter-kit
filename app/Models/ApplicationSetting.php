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
    public const INTERNAL_SIDEBAR_FOOTER_LINK_PATTERN = '/^\/(?!\/)[^\s\\\\]*$/u';

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
