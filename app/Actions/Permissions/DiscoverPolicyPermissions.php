<?php

namespace App\Actions\Permissions;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionClassConstant;
use RuntimeException;

final class DiscoverPolicyPermissions
{
    public function __construct(private Filesystem $files) {}

    public function handle(): PolicyPermissionCatalog
    {
        $permissionDefinitions = [];
        $policyClasses = [];
        $skippedPolicyClasses = [];

        foreach ($this->policyClasses() as $policyClass) {
            $reflection = new ReflectionClass($policyClass);
            $permissionsConstant = $reflection->getReflectionConstant('PERMISSIONS');

            if ($permissionsConstant === false) {
                $skippedPolicyClasses[] = $policyClass;

                continue;
            }

            $permissions = $this->stringList($permissionsConstant, $policyClass);
            $descriptions = $this->descriptions($reflection, $policyClass, $permissions);
            $sensitivePermissions = $this->optionalStringList(
                $reflection,
                'SENSITIVE_PERMISSIONS',
                $policyClass,
            );

            foreach ($sensitivePermissions as $sensitivePermission) {
                if (! in_array($sensitivePermission, $permissions, true)) {
                    throw new RuntimeException(sprintf(
                        '%s::SENSITIVE_PERMISSIONS contains undeclared permission %s.',
                        $policyClass,
                        $sensitivePermission,
                    ));
                }
            }

            foreach ($permissions as $permissionName) {
                $group = Str::before($permissionName, '.');
                $action = Str::afterLast($permissionName, '.');
                $group = $group === $permissionName ? 'other' : $group;
                $label = __(Str::of($action)->replace('_', ' ')->headline()->toString());
                $groupLabel = __(Str::of($group)->replace('_', ' ')->headline()->toString());

                $permissionDefinitions[$permissionName] = [
                    'label' => $label,
                    'description' => isset($descriptions[$permissionName])
                        ? __($descriptions[$permissionName])
                        : __('Allows users with this role to :action :resource.', [
                            'action' => Str::of($label)->lower()->toString(),
                            'resource' => Str::of($groupLabel)->lower()->toString(),
                        ]),
                    'group' => $group,
                    'group_label' => $groupLabel,
                    'sensitive' => in_array($permissionName, $sensitivePermissions, true),
                ];
            }

            $policyClasses[] = $policyClass;
        }

        ksort($permissionDefinitions);

        return new PolicyPermissionCatalog(
            permissions: $permissionDefinitions,
            policyClasses: $policyClasses,
            skippedPolicyClasses: $skippedPolicyClasses,
        );
    }

    /**
     * @param  class-string  $policyClass
     * @return list<string>
     */
    private function stringList(
        ReflectionClassConstant $constant,
        string $policyClass,
    ): array {
        if (! $constant->isPublic()) {
            throw new RuntimeException(sprintf(
                '%s::%s must be public.',
                $policyClass,
                $constant->getName(),
            ));
        }

        $values = $constant->getValue();

        if (! is_array($values)) {
            throw new RuntimeException(sprintf(
                '%s::%s must be an array.',
                $policyClass,
                $constant->getName(),
            ));
        }

        foreach ($values as $value) {
            if (! is_string($value) || Str::squish($value) === '') {
                throw new RuntimeException(sprintf(
                    '%s::%s must contain only non-empty strings.',
                    $policyClass,
                    $constant->getName(),
                ));
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @param  class-string  $policyClass
     * @return list<string>
     */
    private function optionalStringList(
        ReflectionClass $reflection,
        string $constantName,
        string $policyClass,
    ): array {
        $constant = $reflection->getReflectionConstant($constantName);

        if ($constant === false) {
            return [];
        }

        return $this->stringList($constant, $policyClass);
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @param  class-string  $policyClass
     * @param  list<string>  $permissions
     * @return array<string, string>
     */
    private function descriptions(
        ReflectionClass $reflection,
        string $policyClass,
        array $permissions,
    ): array {
        $constant = $reflection->getReflectionConstant('PERMISSION_DESCRIPTIONS');

        if ($constant === false) {
            return [];
        }

        if (! $constant->isPublic() || ! is_array($constant->getValue())) {
            throw new RuntimeException(sprintf(
                '%s::PERMISSION_DESCRIPTIONS must be a public array.',
                $policyClass,
            ));
        }

        $descriptions = [];

        foreach ($constant->getValue() as $permissionName => $description) {
            if (
                ! is_string($permissionName)
                || ! is_string($description)
                || Str::squish($description) === ''
            ) {
                throw new RuntimeException(sprintf(
                    '%s::PERMISSION_DESCRIPTIONS must map permission names to non-empty strings.',
                    $policyClass,
                ));
            }

            if (! in_array($permissionName, $permissions, true)) {
                throw new RuntimeException(sprintf(
                    '%s::PERMISSION_DESCRIPTIONS contains undeclared permission %s.',
                    $policyClass,
                    $permissionName,
                ));
            }

            $descriptions[$permissionName] = Str::squish($description);
        }

        return $descriptions;
    }

    /**
     * @return list<class-string>
     */
    private function policyClasses(): array
    {
        $policyClasses = [];

        foreach ($this->files->allFiles(app_path('Policies')) as $policyFile) {
            if ($policyFile->getExtension() !== 'php') {
                continue;
            }

            $relativeClass = Str::of($policyFile->getRelativePathname())
                ->beforeLast('.php')
                ->replace(['/', '\\'], '\\')
                ->toString();
            $policyClass = 'App\\Policies\\'.$relativeClass;

            if (! class_exists($policyClass)) {
                continue;
            }

            $reflection = new ReflectionClass($policyClass);

            if ($reflection->isAbstract()) {
                continue;
            }

            $policyClasses[] = $policyClass;
        }

        sort($policyClasses);

        return $policyClasses;
    }
}
