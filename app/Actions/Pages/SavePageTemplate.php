<?php

namespace App\Actions\Pages;

use App\Models\PageTemplate;
use Illuminate\Support\Facades\DB;

class SavePageTemplate
{
    /** @param array{name: string, renderer: string, field_set_ids: list<int>} $attributes */
    public function handle(PageTemplate $template, array $attributes): PageTemplate
    {
        return DB::transaction(function () use ($template, $attributes): PageTemplate {
            $template->fill(['name' => $attributes['name'], 'renderer' => $attributes['renderer']])->save();
            $sets = [];
            foreach ($attributes['field_set_ids'] as $position => $id) {
                $sets[$id] = ['position' => $position];
            }
            $template->fieldSets()->sync($sets);

            return $template;
        });
    }
}
