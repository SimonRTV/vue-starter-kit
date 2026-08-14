<?php

namespace App\Actions\Roles;

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

            $lockedRole->delete();
        });
    }
}
