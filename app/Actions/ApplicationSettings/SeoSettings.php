<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use App\Models\Page;
use Laravel\Head\Facades\Head;

class SeoSettings
{
    public const DEFAULT_DESCRIPTION = 'A flexible, thoughtful workspace for bringing people, priorities, and progress together.';

    /** @var array<string, string> */
    public const FIELDS = [
        'meta_title' => '', 'meta_description' => '', 'canonical_url' => '',
        'robots_index' => '', 'robots_follow' => '', 'include_in_sitemap' => '',
        'og_title' => '', 'og_description' => '', 'og_image' => '', 'og_image_alt' => '', 'og_type' => '',
        'twitter_card' => '', 'twitter_title' => '', 'twitter_description' => '', 'twitter_image' => '',
        'structured_data' => '',
    ];

    /** @var array<string, array<string, string>> */
    private array $storedValues = [];

    public function __construct(private GeneralSettings $generalSettings) {}

    /** @return array<string, string> */
    public function defaults(): array
    {
        $name = $this->generalSettings->get()['name'];

        return $this->stored('defaults', [
            ...self::FIELDS,
            'site_name' => $name, 'title_suffix' => $name, 'locale' => 'fr_CH',
            'meta_title' => 'Welcome', 'meta_description' => self::DEFAULT_DESCRIPTION,
            'robots_index' => 'index', 'robots_follow' => 'follow', 'include_in_sitemap' => 'yes',
            'og_type' => 'website', 'twitter_card' => 'summary',
            'twitter_site' => '', 'google_verification' => '', 'bing_verification' => '',
        ]);
    }

    /** @return array<string, string> */
    public function values(?Page $page = null): array
    {
        return $page === null ? $this->stored('home', self::FIELDS) : $this->normalize($page->seo ?? [], self::FIELDS);
    }

    /** @return array<string, string> */
    public function resolve(?Page $page = null): array
    {
        $defaults = $this->defaults();
        $values = $this->values($page);
        $resolved = $defaults;
        foreach ($values as $key => $value) {
            if ($value !== '') {
                $resolved[$key] = $value;
            }
        }
        $resolved['meta_title'] = $values['meta_title'] ?: ($page->title ?? $defaults['meta_title']);
        $resolved['meta_description'] = $values['meta_description'] ?: ($page?->excerpt ?: $defaults['meta_description']);
        $resolved['canonical_url'] = $values['canonical_url'] ?: $this->publicUrl($page);
        $resolved['document_title'] = $resolved['meta_title'].($defaults['title_suffix'] !== '' ? ' - '.$defaults['title_suffix'] : '');
        $resolved['og_title'] = $values['og_title'] ?: $resolved['document_title'];
        $resolved['og_description'] = $values['og_description'] ?: $resolved['meta_description'];
        $resolved['twitter_title'] = $values['twitter_title'] ?: $resolved['og_title'];
        $resolved['twitter_description'] = $values['twitter_description'] ?: $resolved['og_description'];
        $resolved['twitter_image'] = $values['twitter_image'] ?: ($defaults['twitter_image'] ?: $resolved['og_image']);

        return $resolved;
    }

    public function apply(?Page $page = null): void
    {
        $seo = $this->resolve($page);
        Head::title($seo['document_title'], exact: true);
        Head::description($seo['meta_description']);
        Head::canonical($seo['canonical_url']);
        Head::robots($seo['robots_index'] === 'index' && $seo['robots_follow'] === 'follow'
            ? 'all' : $seo['robots_index'].', '.$seo['robots_follow']);
        Head::og(type: $seo['og_type'], title: $seo['og_title'], description: $seo['og_description'], url: $seo['canonical_url'], siteName: $seo['site_name'], locale: $seo['locale']);
        if ($seo['og_image'] !== '') {
            Head::ogImage($seo['og_image'], alt: $seo['og_image_alt']);
        }
        Head::twitter(card: $seo['twitter_card'], site: $seo['twitter_site'] ?: null, title: $seo['twitter_title'], description: $seo['twitter_description']);
        if ($seo['twitter_image'] !== '') {
            Head::twitterImage($seo['twitter_image'], alt: $seo['og_image_alt']);
        }
        foreach (['google_verification' => 'google-site-verification', 'bing_verification' => 'msvalidate.01'] as $key => $name) {
            if ($seo[$key] !== '') {
                Head::meta($name, $seo[$key]);
            }
        }
        foreach (array_unique([$this->defaults()['structured_data'], $this->values($page)['structured_data']]) as $json) {
            $schema = json_decode($json, true);
            if (is_array($schema) && $schema !== []) {
                Head::schema($schema);
            }
        }
    }

    public function publicUrl(?Page $page = null): string
    {
        return $page === null ? route('home') : route('content.show', ['page' => $page->slug]);
    }

    public function includesInSitemap(?Page $page = null): bool
    {
        $seo = $this->resolve($page);

        return $seo['robots_index'] === 'index' && $seo['include_in_sitemap'] === 'yes'
            && $seo['canonical_url'] === $this->publicUrl($page);
    }

    /** @param array<string, string> $defaults
     * @return array<string, string>
     */
    private function stored(string $target, array $defaults): array
    {
        if (isset($this->storedValues[$target])) {
            return $this->storedValues[$target];
        }
        $value = ApplicationSetting::query()->where('key', 'seo.'.$target)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return $this->storedValues[$target] = $this->normalize(is_array($decoded) ? $decoded : [], $defaults);
    }

    /** @param array<string, mixed> $values
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    private function normalize(array $values, array $defaults): array
    {
        foreach ($defaults as $key => $default) {
            $defaults[$key] = isset($values[$key]) && is_string($values[$key]) ? $values[$key] : $default;
        }

        return $defaults;
    }
}
