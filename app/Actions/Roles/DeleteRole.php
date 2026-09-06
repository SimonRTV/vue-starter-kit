<?php

namespace App\Actions\Roles;

use App\Actions\Activity\RecordActivity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class DeleteRole
{
    /**
     * Delete an unused role.
     */
    public function handle(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $lockedRole = Role::query()
                ->lockForUpdate()
                ->whereKey($role->getKey())
                ->firstOrFail();

            if ($lockedRole->users()->exists()) {
                throw ValidationException::withMessages([
                    'role' => __('A role assigned to users cannot be deleted.'),
                ]);
            }

            $actor = Auth::user();
            app(RecordActivity::class)->handle('roles', (string) $lockedRole->getKey(), 'deleted', [
                'name' => ['before' => $lockedRole->name, 'after' => null],
                'permissions' => ['before' => $lockedRole->permissions()->orderBy('name')->pluck('name')->all(), 'after' => null],
            ], $actor instanceof User ? $actor : null);
            $lockedRole->delete();
        });
    }
}
