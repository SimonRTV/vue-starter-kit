<?php

namespace App\Actions\ApplicationSettings;

use App\Models\Page;
use Illuminate\Support\Str;

class FrontendNavigationDestinations
{
    /**
     * Sections rendered by the public homepage (resources/js/pages/Welcome.vue).
     *
     * @var list<array{label: string, url: string}>
     */
    public const HOME_SECTIONS = [
        ['label' => 'Fonctionnalités', 'url' => '#features'],
        ['label' => 'Workflows simples', 'url' => '#simple-workflows'],
        ['label' => 'Visibilité partagée', 'url' => '#shared-visibility'],
        ['label' => 'Conçu pour grandir', 'url' => '#built-to-grow'],
        ['label' => 'Fonctionnement', 'url' => '#workflow'],
        ['label' => 'À propos', 'url' => '#about'],
    ];

    public const PAGE_LIMIT = 200;

    /**
     * Destinations offered by the navigation manager's picker.
     *
     * @return array{
     *     home: array{label: string, url: string},
     *     sections: list<array{label: string, url: string}>,
     *     pages: list<array{label: string, url: string}>
     * }
     */
    public function all(): array
    {
        return [
            'home' => ['label' => 'Accueil', 'url' => route('home', absolute: false)],
            'sections' => self::HOME_SECTIONS,
            'pages' => $this->publishedPages(),
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function publishedPages(): array
    {
        if (! config('starter.features.pages')) {
            return [];
        }

        return Page::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('title')
            ->limit(self::PAGE_LIMIT)
            ->get(['title', 'slug'])
            ->sortBy(fn (Page $page): string => Str::lower(Str::ascii($page->title)))
            ->map(fn (Page $page): array => [
                'label' => $page->title,
                'url' => route('content.show', ['page' => $page->slug], absolute: false),
            ])
            ->values()
            ->all();
    }
}
