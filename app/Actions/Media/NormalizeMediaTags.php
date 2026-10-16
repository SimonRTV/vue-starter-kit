<?php

namespace App\Actions\Media;

use Illuminate\Support\Str;

class NormalizeMediaTags
{
    /**
     * Normalize submitted tags into a unique list, keeping the first spelling of each tag.
     * Invalid input is returned unchanged so validation can reject it.
     */
    public function handle(mixed $tags): mixed
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }
        if (! is_array($tags)) {
            return $tags;
        }
        $normalized = [];
        foreach ($tags as $tag) {
            if ($tag === null) {
                continue;
            }
            if (! is_string($tag)) {
                return $tags;
            }
            $tag = Str::squish($tag);
            if ($tag !== '' && ! in_array(Str::lower($tag), array_map(Str::lower(...), $normalized), true)) {
                $normalized[] = $tag;
            }
        }

        return $normalized;
    }
}
