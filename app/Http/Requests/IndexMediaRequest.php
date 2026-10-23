<?php

namespace App\Http\Requests;

use App\Models\Media;
use App\Models\MediaFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndexMediaRequest extends FormRequest
{
    private const DEFAULT_SORT = 'created_at';

    private const DEFAULT_DIRECTION = 'desc';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Media::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['image', 'audio', 'video', 'document'])],
            'visibility' => ['nullable', Rule::in(['private', 'public'])],
            'tag' => ['nullable', 'string', 'max:'.config('media.max_tag_length')],
            'folder' => ['nullable', 'regex:/^(none|[1-9][0-9]*)$/'],
            'sort' => ['nullable', Rule::in(['created_at', 'title', 'size'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in(config('media.per_page_options'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the normalized filters for the media listing.
     *
     * @return array{search: string|null, type: 'image'|'audio'|'video'|'document'|null, visibility: 'private'|'public'|null, tag: string|null, folder: int|'none'|null, sort: 'created_at'|'title'|'size', direction: 'asc'|'desc', per_page: int}
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $search = $validated['search'] ?? null;
        $tag = $validated['tag'] ?? null;

        return [
            'search' => is_string($search) && $search !== '' ? $search : null,
            'type' => match ($validated['type'] ?? null) {
                'image' => 'image',
                'audio' => 'audio',
                'video' => 'video',
                'document' => 'document',
                default => null,
            },
            'visibility' => match ($validated['visibility'] ?? null) {
                'private' => 'private',
                'public' => 'public',
                default => null,
            },
            'tag' => is_string($tag) && $tag !== '' ? $tag : null,
            'folder' => $this->folder($validated['folder'] ?? null),
            'sort' => match ($validated['sort'] ?? null) {
                'title' => 'title',
                'size' => 'size',
                default => self::DEFAULT_SORT,
            },
            'direction' => ($validated['direction'] ?? null) === 'asc' ? 'asc' : self::DEFAULT_DIRECTION,
            'per_page' => (int) ($validated['per_page'] ?? config('media.per_page_options')[0]),
        ];
    }

    /**
     * Resolve the folder filter, ignoring folders deleted since the link was made.
     *
     * @return int|'none'|null
     */
    private function folder(mixed $folder): int|string|null
    {
        if ($folder === 'none') {
            return 'none';
        }

        return is_string($folder) && MediaFolder::query()->whereKey((int) $folder)->exists() ? (int) $folder : null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['search', 'tag'] as $key) {
            if ($this->has($key)) {
                $this->merge([$key => Str::squish($this->string($key)->toString())]);
            }
        }
    }
}
