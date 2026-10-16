<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UpdateFaviconSvg
{
    public function __construct(private SanitizeFaviconSvg $sanitizeFaviconSvg) {}

    public function handle(string $svg): void
    {
        $disk = Storage::disk('public');
        $newPath = FaviconSettings::SVG_DIRECTORY.'/'.Str::lower((string) Str::ulid()).'.svg';

        if (! $disk->put($newPath, $this->sanitizeFaviconSvg->handle($svg))) {
            throw new RuntimeException('The SVG favicon could not be stored.');
        }

        try {
            $previousPath = DB::transaction(function () use ($newPath): ?string {
                $applicationSetting = ApplicationSetting::query()
                    ->where('key', FaviconSettings::KEY)
                    ->lockForUpdate()
                    ->first();
                $stored = is_string($applicationSetting?->value) ? json_decode($applicationSetting->value, true) : null;
                $stored = is_array($stored) ? $stored : [];
                $previousPath = $stored['svg_path'] ?? null;

                $applicationSetting ??= new ApplicationSetting(['key' => FaviconSettings::KEY]);
                $applicationSetting->value = json_encode([...$stored, 'svg_path' => $newPath], JSON_THROW_ON_ERROR);
                $applicationSetting->save();

                return is_string($previousPath) ? $previousPath : null;
            });
        } catch (Throwable $exception) {
            $disk->delete($newPath);

            throw $exception;
        }

        Cache::forget(FaviconSettings::CACHE_KEY);

        if ($previousPath !== null && FaviconSettings::isSvgPath($previousPath)) {
            $disk->delete($previousPath);
        }
    }
}
