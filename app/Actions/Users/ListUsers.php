<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    /**
     * List users using validated server-side filters.
     *
     * @param  array{search: string|null, role: string|null, verification: 'verified'|'unverified'|null, status: 'active'|'disabled'|null, sort: 'name'|'email'|'email_verified_at'|'disabled_at'|'last_login_at'|'created_at'|'updated_at', direction: 'asc'|'desc', per_page: 10|25|50}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->select([
                'id',
                'name',
                'email',
                'email_verified_at',
                'disabled_at',
                'invitation_sent_at',
                'last_login_at',
                'created_at',
                'updated_at',
            ])
            ->with('roles:id,name,guard_name');

        if ($filters['search'] !== null) {
            $query->where(function (Builder $searchQuery) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $searchQuery
                    ->whereLike('name', $search)
                    ->orWhereLike('email', $search);
            });
        }

        if ($filters['role'] !== null) {
            $query->whereHas('roles', function (Builder $roleQuery) use ($filters): void {
                $roleQuery
                    ->where('name', $filters['role'])
                    ->where('guard_name', 'web');
            });
        }

        if ($filters['verification'] === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($filters['verification'] === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        if ($filters['status'] === 'active') {
            $query->whereNull('disabled_at');
        } elseif ($filters['status'] === 'disabled') {
            $query->whereNotNull('disabled_at');
        }

        return $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id', $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();
    }
}
