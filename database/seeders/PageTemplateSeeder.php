<?php

namespace Database\Seeders;

use App\Models\PageFieldSet;
use App\Models\PageTemplate;
use Illuminate\Database\Seeder;

class PageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PageFieldSetSeeder::class);
        foreach (['feature' => 'Page spéciale', 'collection' => 'Collection de pages'] as $renderer => $name) {
            $template = PageTemplate::query()->firstOrCreate(['name' => $name], ['renderer' => $renderer]);
            if ($template->wasRecentlyCreated) {
                $sets = [];
                foreach (['presentation', 'listing'] as $position => $key) {
                    $id = PageFieldSet::query()->where('key', $key)->sole()->id;
                    $sets[$id] = ['position' => $position];
                }
                $template->fieldSets()->sync($sets);
            }
        }
    }
}
