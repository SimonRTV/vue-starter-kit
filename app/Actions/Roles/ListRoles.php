<?php

namespace App\Actions\Roles;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

class ListRoles
{
    /**
     * List roles using validated server-side filters.
     *
     * @param  array{search: string|null, assignment: 'assigned'|'unused'|null, sort: 'name'|'users_count'|'permissions_count'|'created_at'|'updated_at', direction: 'asc'|'desc', per_page: 10|25|50}  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = Role::query()
            ->select(['id', 'name', 'guard_name', 'created_at', 'updated_at'])
            ->where('guard_name', 'web')
            ->withCount(['users', 'permissions']);

        if ($filters['search'] !== null) {
            $query->whereLike('name', '%'.$filters['search'].'%');
        }

        if ($filters['assignment'] === 'assigned') {
            $query->whereHas('users');
        } elseif ($filters['assignment'] === 'unused') {
            $query->whereDoesntHave('users');
        }

        return $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id', $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();
    }
}
