<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $id = (string) Str::uuid();

        return [
            'id' => $id, 'uploaded_by' => User::factory(), 'title' => fake()->sentence(3),
            'original_name' => 'document.txt', 'mime_type' => 'text/plain', 'size' => 7,
            'disk' => 'media', 'path' => $id.'/original.txt', 'visibility' => 'private',
        ];
    }

    public function publiclyShared(): static
    {
        return $this->state(fn (): array => ['visibility' => 'public']);
    }
}
