<?php

namespace Database\Factories;

use App\Models\MediaFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaFolder> */
class MediaFolderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['parent_id' => null, 'name' => fake()->unique()->words(2, true), 'created_by' => null];
    }

    public function childOf(MediaFolder $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }
}
