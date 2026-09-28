<?php

namespace App\Actions\Pages;

use App\Actions\Imports\ImportResource;
use App\Http\Requests\StorePageRequest;
use App\Models\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportPages extends ImportResource
{
    public const FIELDS = ['title' => 'Titre', 'slug' => 'Identifiant URL', 'excerpt' => 'Extrait', 'body' => 'Contenu', 'body_format' => 'Format du contenu', 'is_published' => 'Statut'];

    public function __construct(private CreatePage $createPage, private UpdatePage $updatePage) {}

    public function definition(): array
    {
        return ['key' => 'pages', 'label' => 'Pages', 'fields' => self::FIELDS, 'required' => ['title', 'slug'],
            'columns' => ['Titre', 'Identifiant URL', 'Statut'], 'model' => Page::class, 'updatePermission' => 'pages.update',
            'feature' => 'starter.features.pages', 'indexRoute' => 'pages.index',
            'help' => 'Titre et identifiant URL sont obligatoires. Les nouvelles pages sont des brouillons si le statut n’est pas associé. Statuts : Brouillon / Publiée ou 0 / 1. Le contenu est du texte par défaut ; associez un format text ou html si nécessaire.'];
    }

    public function prepare(array $mapped, bool $lock, string $mode): array
    {
        $slug = $mapped['slug'] ?? '';
        $query = Page::query()->where('slug', $slug);
        $existing = ($lock ? $query->lockForUpdate() : $query)->first();
        $errors = [];
        $attributes = [
            'title' => $mapped['title'] ?? '',
            'slug' => $slug,
            'excerpt' => array_key_exists('excerpt', $mapped) ? ($mapped['excerpt'] === '' ? null : $mapped['excerpt']) : $existing?->excerpt,
            'body' => array_key_exists('body', $mapped) ? ($mapped['body'] === '' ? null : $mapped['body']) : $existing?->body,
            'body_format' => $mapped['body_format'] ?? (array_key_exists('body', $mapped) ? 'text' : ($existing->body_format ?? 'text')),
            'is_published' => $existing->is_published ?? false,
        ];
        if (array_key_exists('is_published', $mapped)) {
            $status = Str::lower(Str::ascii($mapped['is_published']));
            if (! in_array($status, ['1', '0', 'true', 'false', 'oui', 'non', 'published', 'draft', 'publiee', 'publie', 'brouillon'], true)) {
                $errors[] = 'Statut invalide : utilisez Brouillon ou Publiée (ou 0 / 1).';
            }
            $attributes['is_published'] = in_array($status, ['1', 'true', 'oui', 'published', 'publiee', 'publie'], true);
        }
        $rules = (new StorePageRequest)->rules();
        $rules['slug'] = ['required', 'string', 'max:255'];
        $validator = Validator::make($attributes, $rules, [], self::FIELDS);
        $errors = array_values([...$errors, ...$validator->errors()->all()]);
        $action = $existing === null ? 'create' : ($mode === 'update' ? 'update' : 'skip');
        if ($existing !== null && (! Gate::allows('view', $existing) || ($action === 'update' && ! Gate::allows('update', $existing)))) {
            $errors[] = 'Vous ne disposez pas des droits nécessaires pour cette page.';
        }

        return ['identity' => $slug, 'attributes' => $attributes, 'errors' => $errors,
            'existing_id' => $existing?->id,
            'fingerprint' => $existing === null ? null : hash('sha256', serialize($existing->getRawOriginal())),
            'cells' => [$attributes['title'], $slug, $attributes['is_published'] ? 'Publiée' : 'Brouillon']];
    }

    public function apply(array $row): void
    {
        $data = $row['attributes'];
        $attributes = ['title' => Arr::string($data, 'title'), 'slug' => Arr::string($data, 'slug'),
            'excerpt' => $data['excerpt'] === null ? null : Arr::string($data, 'excerpt'),
            'body' => $data['body'] === null ? null : Arr::string($data, 'body'),
            'body_format' => Arr::string($data, 'body_format'), 'is_published' => Arr::boolean($data, 'is_published')];
        if ($row['action'] === 'create') {
            Gate::authorize('create', Page::class);
            $this->createPage->handle($attributes);
        } else {
            $page = Page::query()->findOrFail($row['existing_id']);
            Gate::authorize('update', $page);
            $this->updatePage->handle($page, $attributes);
        }
    }
}
