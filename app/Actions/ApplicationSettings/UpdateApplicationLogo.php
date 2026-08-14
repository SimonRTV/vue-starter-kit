<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateApplicationLogo
{
    public function handle(UploadedFile $logo, string $settingKey): ApplicationSetting
    {
        $newLogoPath = $logo->store('application/logos', 'public');

        if (! is_string($newLogoPath)) {
            throw new RuntimeException('The application logo could not be stored.');
        }

        try {
            [$applicationSetting, $previousLogoPath] = DB::transaction(
                function () use ($newLogoPath, $settingKey): array {
                    $applicationSetting = ApplicationSetting::query()
                        ->where('key', $settingKey)
                        ->lockForUpdate()
                        ->first();
                    $previousLogoPath = $applicationSetting?->value;

                    $applicationSetting ??= new ApplicationSetting([
                        'key' => $settingKey,
                    ]);
                    $applicationSetting->value = $newLogoPath;
                    $applicationSetting->save();

                    return [
                        $applicationSetting->refresh(),
                        is_string($previousLogoPath) ? $previousLogoPath : null,
                    ];
                },
            );
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newLogoPath);

            throw $exception;
        }

        if ($previousLogoPath !== null && $previousLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        return $applicationSetting;
    }
}
