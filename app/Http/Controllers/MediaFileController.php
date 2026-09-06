<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaFileController extends Controller
{
    public function download(Request $request, Media $media): StreamedResponse
    {
        $this->authorizeFile($request, $media);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }

    public function thumbnail(Request $request, Media $media): StreamedResponse
    {
        $this->authorizeFile($request, $media);
        abort_unless($media->thumbnail_path !== null && Storage::disk($media->disk)->exists($media->thumbnail_path), 404);

        return Storage::disk($media->disk)->response($media->thumbnail_path, 'thumbnail.jpg', [
            'Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeFile(Request $request, Media $media): void
    {
        if ($media->visibility === 'public') {
            return;
        }
        abort_unless($request->user()?->hasVerifiedEmail() && Gate::allows('view', $media), 404);
    }
}
