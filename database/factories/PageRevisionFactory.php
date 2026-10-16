<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageRevision> */
class PageRevisionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'event' => 'saved',
            'content' => [
                'title' => fake()->sentence(3), 'slug' => fake()->unique()->slug(),
                'excerpt' => null, 'body' => fake()->paragraph(), 'body_format' => 'text',
                'page_template_id' => null, 'template_fields' => null,
            ],
        ];
    }
}
