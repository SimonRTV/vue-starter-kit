# Policy Permission Synchronization

The application treats permissions as code-defined capabilities. Policies declare the permissions they use, and the `permissions:sync` command creates those permissions for Spatie and grants them to the protected `Administrator` role.

## Quick start

When adding a model such as `Page`, define its permission names in the corresponding policy:

```php
<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public const VIEW = 'pages.view';

    public const CREATE = 'pages.create';

    public const UPDATE = 'pages.update';

    public const DELETE = 'pages.delete';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        self::VIEW,
        self::CREATE,
        self::UPDATE,
        self::DELETE,
    ];

    public const PERMISSION_DESCRIPTIONS = [
        self::VIEW => 'View pages and their content.',
        self::CREATE => 'Create new pages.',
        self::UPDATE => 'Edit existing pages.',
        self::DELETE => 'Delete, restore, and permanently delete pages.',
    ];

    public const SENSITIVE_PERMISSIONS = [
        self::DELETE,
    ];

    public function viewAny(User $user): bool
    {
        return $user->can(self::VIEW);
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can(self::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(self::CREATE);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can(self::UPDATE);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can(self::DELETE);
    }
}
```

Synchronize the declared permissions:

```bash
php artisan permissions:sync
```

The new permissions will appear automatically in Role Manager. Permission names are grouped by the segment before the first dot, so `pages.view` and `pages.create` appear under `Pages`.

`PERMISSION_DESCRIPTIONS` is optional, but recommended. Its text is displayed in Role Manager and can be searched. Permissions without an explicit description receive a generated fallback.

`SENSITIVE_PERMISSIONS` is also optional. Use it for destructive or privilege-granting capabilities that should be clearly highlighted before an administrator assigns them.

## Using permissions in Role Manager

Role Manager groups permissions by resource and shows their human-readable label, description, and underlying permission name. Use the search field to filter by any of those values, or by the `sensitive` and `orphaned` status labels.

The checkbox at the top of a resource group selects or clears that complete group. When a search is active, the group checkbox still applies to every permission in the resource, including permissions hidden by the current filter.

Sensitive permissions are highlighted before selection. Orphaned permissions remain selectable so existing assignments can be reviewed and migrated without an implicit access change.

## What the command does

The command:

- Recursively discovers concrete policy classes under `app/Policies`.
- Reads each public `PERMISSIONS` constant.
- Creates missing permissions for the `web` guard.
- Creates the protected `Administrator` role when necessary.
- Grants all discovered permissions to `Administrator`.
- Preserves permissions that are not declared by an application policy.
- Reports preserved permissions as orphaned so they can be reviewed safely.
- Clears Spatie's permission cache before and after synchronization.
- Reports policies that do not declare a `PERMISSIONS` constant.

The command is additive and safe to run repeatedly. It does not rename or delete permissions.

## Previewing changes and stale permissions

Run a dry report before synchronization or during deployment review:

```bash
php artisan permissions:sync --dry-run
```

The dry run reports:

- Permissions declared by policies but missing from the database.
- Permissions the `Administrator` role would receive.
- Orphaned database permissions that no policy currently declares.

It does not create roles or permissions, change assignments, clear the permission cache, or delete anything. Orphaned permissions also remain visible and clearly marked in Role Manager.

## Policy requirements

For a policy to participate:

1. Store it under `app/Policies` or one of its subdirectories.
2. Ensure its namespace matches its location under `App\Policies`.
3. Declare a public `PERMISSIONS` constant containing only non-empty strings.
4. Optionally declare `PERMISSION_DESCRIPTIONS` and `SENSITIVE_PERMISSIONS` metadata.
5. Use the same permission names in the policy's authorization methods.

Policies without `PERMISSIONS` are skipped and listed in the command output. An invalid constant causes the command to fail without partially synchronizing permissions.

## Seeding and deployment

`UserManagementSeeder` uses the same synchronization action as the command, so fresh databases and existing installations follow the same rules.

For a fresh database, use the normal database seeder. To update permissions after deploying a new policy, run:

```bash
php artisan permissions:sync --no-interaction
```

This command can be added to a deployment pipeline after migrations.

## Removing or renaming permissions

Removing a permission from a policy does not remove its database record. This is intentional because automatic deletion could revoke production access unexpectedly.

When renaming or retiring a permission:

1. Deploy the replacement permission and synchronize it.
2. Update the affected roles in Role Manager.
3. Run `php artisan permissions:sync --dry-run` and verify the old name is reported as orphaned.
4. Remove the old permission manually only after its role and user assignments are no longer needed.

## Useful commands

```bash
# Synchronize application policy permissions
php artisan permissions:sync

# Preview missing and orphaned permissions without changing anything
php artisan permissions:sync --dry-run

# Inspect Spatie roles and permissions
php artisan permission:show

# Reset Spatie's permission cache manually
php artisan permission:cache-reset

# Run the focused synchronization tests
php artisan test --compact tests/Feature/Console/Commands/SyncPermissionsTest.php
```
