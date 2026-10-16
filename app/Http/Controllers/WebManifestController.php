<?php

namespace App\Http\Controllers;

use App\Actions\ApplicationSettings\FaviconSettings;
use App\Actions\ApplicationSettings\GeneralSettings;
use Illuminate\Http\JsonResponse;

class WebManifestController extends Controller
{
    public function __invoke(FaviconSettings $faviconSettings, GeneralSettings $generalSettings): JsonResponse
    {
        return response()
            ->json($faviconSettings->manifest($generalSettings->get()['name']), options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
