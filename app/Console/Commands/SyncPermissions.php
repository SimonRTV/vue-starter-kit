<?php

namespace App\Console\Commands;

use App\Actions\Permissions\SyncPolicyPermissions;
use App\Policies\RolePolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync
    {--dry-run : Report missing and orphaned permissions without making changes}')]
#[Description('Discover policy permissions and synchronize them with Spatie')]
class SyncPermissions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncPolicyPermissions $syncPolicyPermissions): int
    {
        $result = $syncPolicyPermissions->handle((bool) $this->option('dry-run'));

        if ($result->dryRun) {
            $this->info('Permission synchronization dry run. No changes were made.');
            $this->line(sprintf(
                'Declared permissions: %d',
                $result->declaredPermissions(),
            ));
            $this->line(sprintf(
                'Missing permissions: %d',
                count($result->missingPermissionNames),
            ));
            $this->line(sprintf(
                'Would grant to %s: %d',
                RolePolicy::ADMINISTRATOR_ROLE,
                count($result->administratorMissingPermissionNames),
            ));
        } else {
            $this->info(sprintf(
                'Synchronized %d permissions from %d policies.',
                $result->totalPermissions(),
                count($result->policyClasses),
            ));
            $this->line(sprintf('Created: %d', $result->createdPermissions));
            $this->line(sprintf('Already existed: %d', $result->existingPermissions));
            $this->line(sprintf(
                'Granted to %s: %d',
                RolePolicy::ADMINISTRATOR_ROLE,
                $result->grantedToAdministrator,
            ));
        }

        $this->newLine();
        $this->line(sprintf(
            'Orphaned permissions: %d',
            count($result->orphanedPermissionNames),
        ));

        foreach ($result->orphanedPermissionNames as $permissionName) {
            $this->warn(' - '.$permissionName);
        }

        if ($result->orphanedPermissionNames !== []) {
            $this->line('No orphaned permissions were deleted.');
        }

        if ($result->skippedPolicyClasses !== []) {
            $this->newLine();
            $this->warn('Skipped policies without a public PERMISSIONS constant:');

            foreach ($result->skippedPolicyClasses as $policyClass) {
                $this->line(' - '.$policyClass);
            }
        }

        return self::SUCCESS;
    }
}
