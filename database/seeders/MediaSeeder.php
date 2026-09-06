<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();
        if ($user === null) {
            return;
        }
        Media::factory()->count(3)->create(['uploaded_by' => $user->id])->each(function (Media $media): void {
            Storage::disk($media->disk)->put($media->path, 'Example', 'private');
        });
    }
}
