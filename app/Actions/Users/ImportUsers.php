<?php

namespace App\Actions\Users;

use App\Actions\Imports\ImportResource;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ImportUsers extends ImportResource
{
    public function __construct(
        private CreateUser $createUser,
        private UpdateUser $updateUser,
        private DisableUser $disableUser,
        private EnableUser $enableUser,
        private EnsureAdministratorContinuity $continuity,
    ) {}

    public function definition(): array
    {
        return ['key' => 'users', 'label' => 'Utilisateurs',
            'fields' => ['name' => 'Nom', 'email' => 'E-mail', 'active' => 'Statut', 'email_verified' => 'Vérifié', 'roles' => 'Rôles'],
            'required' => ['name', 'email'], 'columns' => ['Nom', 'E-mail', 'Statut', 'Vérifié', 'Rôles'],
            'model' => User::class, 'updatePermission' => UserPolicy::UPDATE, 'feature' => null, 'indexRoute' => 'users.index',
            'help' => 'Nom et e-mail sont obligatoires. L’e-mail identifie les doublons. Statut : Actif / Désactivé ; vérification : Oui / Non. Rôles existants séparés par des virgules (ou tableau JSON). Sans association : nouveau compte actif, non vérifié et sans rôle ; compte existant inchangé. Les nouveaux comptes recevront une invitation à choisir leur mot de passe après confirmation. Les dates de création et les mots de passe ne sont pas importés.'];
    }

    public function prepare(array $mapped, bool $lock, string $mode): array
    {
        $email = User::normalizeEmail($mapped['email'] ?? '');
        $query = User::query()->where('email', $email);
        $existing = ($lock ? $query->lockForUpdate() : $query)->first();
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $currentRoles = $existing?->roles()->where('guard_name', 'web')->pluck('name')->sort()->values()->all() ?? [];
        $roles = $currentRoles;
        $errors = [];
        if (array_key_exists('roles', $mapped)) {
            $input = $mapped['roles'];
            if (str_starts_with($input, '[')) {
                $decoded = json_decode($input, true);
                if (! is_array($decoded) || ! array_is_list($decoded) || count(array_filter($decoded, is_string(...))) !== count($decoded)) {
                    $errors[] = 'La colonne Rôles doit contenir une liste de noms ou un tableau JSON de chaînes.';
                    $roles = [];
                } else {
                    $roles = array_map(trim(...), $decoded);
                }
            } elseif ($input === '') {
                $roles = [];
            } elseif (Role::query()->where('guard_name', 'web')->where('name', $input)->exists()) {
                $roles = [$input];
            } else {
                $roles = array_map(trim(...), explode(',', $input));
            }
        }
        sort($roles);
        $active = $existing === null || $existing->disabled_at === null;
        $verified = $existing !== null && $existing->email_verified_at !== null;
        foreach (['active', 'email_verified'] as $field) {
            if (! array_key_exists($field, $mapped)) {
                continue;
            }
            $value = Str::lower(Str::ascii($mapped[$field]));
            $yes = $field === 'active' ? ['1', 'true', 'oui', 'actif', 'active'] : ['1', 'true', 'oui', 'yes'];
            $no = $field === 'active' ? ['0', 'false', 'non', 'desactive', 'disabled', 'inactive'] : ['0', 'false', 'non', 'no'];
            if (! in_array($value, [...$yes, ...$no], true)) {
                $errors[] = $field === 'active' ? 'Statut invalide : utilisez Actif ou Désactivé.' : 'Vérification invalide : utilisez Oui ou Non.';
            }
            if ($field === 'active') {
                $active = in_array($value, $yes, true);
            } else {
                $verified = in_array($value, $yes, true);
            }
        }
        $attributes = ['name' => $mapped['name'] ?? '', 'email' => $email, 'email_verified' => $verified, 'roles' => $roles, 'active' => $active];
        $request = $existing === null ? new StoreUserRequest : new UpdateUserRequest;
        $request->replace($attributes);
        $request->setUserResolver(fn (): User => $actor);
        $route = (new Route('PATCH', 'users/{user}', fn (): null => null))->bind(Request::create('/users/'.($existing->id ?? 0), 'PATCH'));
        $route->setParameter('user', $existing);
        $request->setRouteResolver(fn (): Route => $route);
        $validator = Validator::make($attributes, $request->rules());
        $willWrite = $existing === null || $mode === 'update';
        if ($willWrite) {
            $validator->after($request->after());
        }
        $errors = array_values([...$errors, ...$validator->errors()->all()]);
        if ($existing !== null && (! Gate::allows('view', $existing) || ($willWrite && ! Gate::allows('update', $existing)))) {
            $errors[] = 'Vous ne disposez pas des droits nécessaires pour ce compte.';
        }
        if ($willWrite && $active !== ($existing === null || $existing->disabled_at === null)) {
            $allowed = $existing === null ? $actor->can(UserPolicy::SUSPEND) : Gate::allows($active ? 'enable' : 'disable', $existing);
            if (! $allowed) {
                $errors[] = 'Vous ne pouvez pas modifier le statut de ce compte.';
            }
        }
        if ($willWrite && $existing !== null && $errors === []) {
            try {
                $this->continuity->updating($existing, ['name' => $attributes['name'], 'email' => $email, 'email_verified_at' => $verified ? ($existing->email_verified_at ?? now()) : null], $roles);
                if (! $active && $existing->disabled_at === null) {
                    $this->continuity->disabling($existing);
                }
            } catch (ValidationException $exception) {
                $errors = array_values([...$errors, ...Arr::flatten($exception->errors())]);
            }
        }

        return ['identity' => $email, 'attributes' => $attributes, 'errors' => $errors, 'existing_id' => $existing?->id,
            'fingerprint' => $existing === null ? null : hash('sha256', serialize([$existing->getRawOriginal(), $currentRoles])),
            'cells' => [$attributes['name'], $email, $active ? 'Actif' : 'Désactivé', $verified ? 'Oui' : 'Non', implode(', ', $roles)]];
    }

    public function apply(array $row): void
    {
        $data = $row['attributes'];
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $existing = $row['existing_id'] === null ? null : User::query()->findOrFail($row['existing_id']);
        $verified = Arr::boolean($data, 'email_verified');
        $attributes = ['name' => Arr::string($data, 'name'), 'email' => Arr::string($data, 'email'),
            'email_verified_at' => $verified ? ($existing->email_verified_at ?? now()) : null];
        $roles = array_values(array_filter(Arr::array($data, 'roles'), is_string(...)));
        if ($existing === null) {
            Gate::authorize('create', User::class);
            $user = $this->createUser->handle($attributes, $roles, $actor);
        } else {
            Gate::authorize('update', $existing);
            $user = $this->updateUser->handle($existing, $attributes, $roles, $actor);
        }
        $active = Arr::boolean($data, 'active');
        if ($active !== ($user->disabled_at === null)) {
            Gate::authorize($active ? 'enable' : 'disable', $user);
            if ($active) {
                $this->enableUser->handle($user, $actor);
            } else {
                $this->disableUser->handle($user, $actor);
            }
        }
    }
}
