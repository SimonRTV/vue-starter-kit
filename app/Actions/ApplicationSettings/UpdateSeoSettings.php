<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use App\Models\Page;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateSeoSettings
{
    /**
     * Save the form settings, keeping uploaded images unless a pasted URL replaces them.
     *
     * @param  array<string, string>  $attributes
     */
    public function handle(string $target, array $attributes, ?Page $page = null): void
    {
        $replacedPaths = $this->modify($target, $page, function (array $seo) use ($attributes): array {
            $replacedPaths = [];
            foreach (SeoSettings::imagePaths($seo) as $field => $path) {
                if (($attributes[$field] ?? '') !== '') {
                    $replacedPaths[] = $path;
                } else {
                    $attributes[$field.'_path'] = $path;
                }
            }

            return [$attributes, $replacedPaths];
        });

        Storage::disk('public')->delete($replacedPaths);
    }

    /**
     * Atomically read and rewrite the raw SEO settings of a target.
     *
     * @template TResult
     *
     * @param  Closure(array<string, mixed>): array{0: array<string, mixed>, 1: TResult}  $callback
     * @return TResult
     */
    public function modify(string $target, ?Page $page, Closure $callback): mixed
    {
        return DB::transaction(function () use ($target, $page, $callback): mixed {
            if ($page !== null) {
                $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
                [$seo, $result] = $callback($locked->seo ?? []);
                $locked->update(['seo' => $seo]);
                $page->setAttribute('seo', $seo);

                return $result;
            }

            $value = ApplicationSetting::query()->where('key', 'seo.'.$target)->lockForUpdate()->value('value');
            $decoded = is_string($value) ? json_decode($value, true) : null;
            [$seo, $result] = $callback(is_array($decoded) ? $decoded : []);
            ApplicationSetting::query()->updateOrCreate(
                ['key' => 'seo.'.$target],
                ['value' => json_encode($seo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
            );

            return $result;
        });
    }
}
