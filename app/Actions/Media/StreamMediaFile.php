<?php

namespace App\Actions\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamMediaFile
{
    private const CHUNK_SIZE = 1048576;

    /**
     * Serve a playable file inline, honouring single byte ranges so media players can seek and preload metadata.
     */
    public function handle(Media $media, ?string $rangeHeader): StreamedResponse
    {
        $disk = Storage::disk($media->disk);
        $size = $disk->size($media->path);
        $headers = [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $media->original_name, $this->fallbackName($media)),
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        $status = Response::HTTP_OK;
        [$start, $end] = [0, $size - 1];

        if ($rangeHeader !== null) {
            $range = $this->parseRange($rangeHeader, $size);
            if ($range === null) {
                return new StreamedResponse(fn () => null, Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, ['Content-Range' => 'bytes */'.$size]);
            }
            [$start, $end] = $range;
            $status = Response::HTTP_PARTIAL_CONTENT;
            $headers['Content-Range'] = sprintf('bytes %d-%d/%d', $start, $end, $size);
        }
        $headers['Content-Length'] = (string) max(0, $end - $start + 1);

        return new StreamedResponse(function () use ($disk, $media, $start, $end): void {
            $stream = $disk->readStream($media->path);
            try {
                if ($start > 0) {
                    fseek($stream, $start);
                }
                $remaining = $end - $start + 1;
                while ($remaining > 0 && ! feof($stream)) {
                    $chunk = fread($stream, min(self::CHUNK_SIZE, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                    flush();
                }
            } finally {
                fclose($stream);
            }
        }, $status, $headers);
    }

    /**
     * Resolve a "bytes=start-end" header to inclusive offsets, or null when it cannot be satisfied.
     *
     * @return array{int, int}|null
     */
    private function parseRange(string $header, int $size): ?array
    {
        if ($size === 0 || preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches) !== 1 || ($matches[1] === '' && $matches[2] === '')) {
            return null;
        }
        if ($matches[1] === '') {
            $suffix = min((int) $matches[2], $size);

            return $suffix === 0 ? null : [$size - $suffix, $size - 1];
        }
        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);

        return $start > $end ? null : [$start, $end];
    }

    private function fallbackName(Media $media): string
    {
        $name = str_replace(['%', '/', '\\', '"'], '', Str::ascii($media->original_name));

        return trim($name) !== '' ? $name : 'media';
    }
}
