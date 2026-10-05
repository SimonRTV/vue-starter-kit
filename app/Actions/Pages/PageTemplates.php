<?php

namespace App\Actions\Pages;

use App\Models\PageTemplate;

class PageTemplates
{
    /** @return array<string, array{label: string, component: string}> */
    public function renderers(): array
    {
        return config('page_templates.renderers', []);
    }

    public function component(?PageTemplate $template): ?string
    {
        $component = $this->renderers()[$template?->renderer]['component'] ?? null;

        return $component && str_starts_with($component, 'content/') && ! str_contains($component, '..')
            && is_file(resource_path('js/pages/'.$component.'.vue')) ? $component : null;
    }

    public function source(string $key): ?TemplateDataSource
    {
        /** @var array<string, array{label: string, resolver: class-string<TemplateDataSource>}> $sources */
        $sources = config('page_templates.sources', []);
        $class = $sources[$key]['resolver'] ?? null;

        return $class && is_subclass_of($class, TemplateDataSource::class) ? app($class) : null;
    }

    /** @return list<array{key: string, label: string, orders: array<string, string>}> */
    public function sources(): array
    {
        $options = [];
        foreach (config('page_templates.sources', []) as $key => $source) {
            if ($resolver = $this->source($key)) {
                $options[] = ['key' => $key, 'label' => $source['label'], 'orders' => $resolver->orders()];
            }
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function editorOptions(): array
    {
        return [
            'templates' => PageTemplate::query()->with('fieldSets')->orderBy('name')->get()
                ->filter(fn (PageTemplate $template): bool => $this->component($template) !== null)->values(),
            'templateSources' => $this->sources(),
        ];
    }
}
