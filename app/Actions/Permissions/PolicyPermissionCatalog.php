<?php

namespace App\Actions\Permissions;

final readonly class PolicyPermissionCatalog
{
    /**
     * @param  array<string, array{label: string, description: string, group: string, group_label: string, sensitive: bool}>  $permissions
     * @param  list<string>  $policyClasses
     * @param  list<string>  $skippedPolicyClasses
     */
    public function __construct(
        public array $permissions,
        public array $policyClasses,
        public array $skippedPolicyClasses,
    ) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->permissions);
    }

    /**
     * @return array{label: string, description: string, group: string, group_label: string, sensitive: bool}|null
     */
    public function find(string $permissionName): ?array
    {
        return $this->permissions[$permissionName] ?? null;
    }
}
