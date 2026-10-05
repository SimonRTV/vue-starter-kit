<?php

namespace Database\Factories;

use App\Models\PageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageTemplate> */
class PageTemplateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'renderer' => 'feature'];
    }
}
