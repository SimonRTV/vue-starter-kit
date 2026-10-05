<?php

namespace Database\Seeders;

use App\Models\PageFieldSet;
use Illuminate\Database\Seeder;

class PageFieldSetSeeder extends Seeder
{
    public function run(): void
    {
        PageFieldSet::query()->firstOrCreate(['key' => 'presentation'], [
            'name' => 'Présentation',
            'fields' => [
                ['key' => 'heading', 'label' => 'Titre de section', 'type' => 'text', 'required' => false, 'options' => []],
                ['key' => 'image', 'label' => 'Image de présentation', 'type' => 'image', 'required' => false, 'options' => []],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'options' => []],
            ],
        ]);
        PageFieldSet::query()->firstOrCreate(['key' => 'listing'], [
            'name' => 'Collection de contenus',
            'fields' => [['key' => 'items', 'label' => 'À découvrir', 'type' => 'collection', 'required' => true, 'options' => []]],
        ]);
    }
}
