---
paths:
  - '{app/Policies/**,app/Actions/Permissions/**,app/Console/Commands/SyncPermissions.php,app/Http/Controllers/RoleController.php,resources/js/components/roles/RoleForm.vue}'
---

# Roles

## Keep permission metadata code-defined and orphan cleanup explicit
Policies may declare PERMISSION_DESCRIPTIONS and SENSITIVE_PERMISSIONS alongside PERMISSIONS. DiscoverPolicyPermissions is the shared catalog for Role Manager and permissions:sync. Orphaned permissions must be marked and reported, never removed automatically; --dry-run must remain mutation-free.
