<?php

namespace App\Http\Requests;

use App\Models\MediaFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SaveMediaFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folder = $this->route('folder');

        return $folder instanceof MediaFolder
            ? $this->user()?->can('update', $folder) ?? false
            : $this->user()?->can('create', MediaFolder::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    public function parentId(): ?int
    {
        $parentId = $this->validated('parent_id');

        return $parentId === null ? null : (int) $parentId;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => Str::squish($this->string('name')->toString())]);
        }
    }
}
