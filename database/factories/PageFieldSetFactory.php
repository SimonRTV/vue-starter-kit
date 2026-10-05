<?php

namespace Database\Factories;

use App\Models\PageFieldSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageFieldSet> */
class PageFieldSetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'key' => 'group_'.fake()->unique()->numerify('########'),
            'fields' => [['key' => 'heading', 'label' => 'Titre', 'type' => 'text', 'required' => false, 'options' => []]],
        ];
    }
}
