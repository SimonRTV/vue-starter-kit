<?php

namespace App\Http\Controllers;

use App\Actions\Exports\ExportCsv;
use App\Actions\Pages\ListPages;
use App\Actions\Roles\ListRoles;
use App\Actions\Users\ListUsers;
use App\Http\Requests\IndexPageRequest;
use App\Http\Requests\IndexRoleRequest;
use App\Http\Requests\IndexUserRequest;
use App\Models\Page;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableExportController extends Controller
{
    public function pages(IndexPageRequest $request, ListPages $list, ExportCsv $csv): StreamedResponse
    {
        return $csv->download($list->query($request->filters())->limit(ExportCsv::MAX_ROWS + 1)->get()->map(fn (Page $page): array => [
            $page->title, $page->slug, $page->is_published ? 'Publiée' : 'Brouillon', $page->published_at?->toISOString(), $page->updated_at?->toISOString(),
        ]), 'pages.csv', ['Titre', 'Identifiant URL', 'Statut', 'Publication', 'Modification']);
    }

    public function users(IndexUserRequest $request, ListUsers $list, ExportCsv $csv): StreamedResponse
    {
        return $csv->download($list->query($request->filters())->limit(ExportCsv::MAX_ROWS + 1)->get()->map(fn (User $user): array => [
            $user->name, $user->email, $user->disabled_at === null ? 'Actif' : 'Désactivé', $user->email_verified_at === null ? 'Non' : 'Oui', $user->roles->pluck('name')->implode(', '), $user->created_at?->toISOString(),
        ]), 'utilisateurs.csv', ['Nom', 'E-mail', 'Statut', 'Vérifié', 'Rôles', 'Création']);
    }

    public function roles(IndexRoleRequest $request, ListRoles $list, ExportCsv $csv): StreamedResponse
    {
        return $csv->download($list->query($request->filters())->limit(ExportCsv::MAX_ROWS + 1)->get()->map(fn (Role $role): array => [
            $role->name, $role->getAttribute('users_count'), $role->getAttribute('permissions_count'), $role->created_at?->toISOString(),
        ]), 'roles.csv', ['Nom', 'Utilisateurs', 'Permissions', 'Création']);
    }
}
