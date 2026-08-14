<?php

namespace App\Actions\Permissions;

final readonly class PermissionSyncResult
{
    /**
     * @param  list<string>  $policyClasses
     * @param  list<string>  $skippedPolicyClasses
     * @param  list<string>  $missingPermissionNames
     * @param  list<string>  $orphanedPermissionNames
     * @param  list<string>  $administratorMissingPermissionNames
     */
    public function __construct(
        public bool $dryRun,
        public int $createdPermissions,
        public int $existingPermissions,
        public int $grantedToAdministrator,
        public array $policyClasses,
        public array $skippedPolicyClasses,
        public array $missingPermissionNames,
        public array $orphanedPermissionNames,
        public array $administratorMissingPermissionNames,
    ) {}

    public function totalPermissions(): int
    {
        return $this->createdPermissions + $this->existingPermissions;
    }

    public function declaredPermissions(): int
    {
        return $this->existingPermissions + count($this->missingPermissionNames);
    }
}
