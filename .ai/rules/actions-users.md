---
paths:
  - '{config/activity.php,app/Models/Activity.php,app/Observers/ActivityObserver.php,app/Actions/Activity/**,app/Actions/Roles/**,app/Actions/Users/RecordUserManagementEvent.php}'
---

# Actions Users

## Record activity with explicit field lists and safe retention
The central log captures future changes only. Opt models into config/activity.php with explicit fields and redacted values; never log credentials, request payloads, file paths, or content bodies. Eloquent observers do not cover bulk queries or quiet saves: use domain actions and transactions for audited writes. Role actions record permission diffs explicitly; RecordUserManagementEvent preserves legacy history and mirrors safe event identifiers with its explicit actor. The viewer is read-only and requires sensitive activity.view. Retention is disabled at zero; positive ACTIVITY_RETENTION_DAYS is enforced by Laravel's scheduled model:prune, which requires the normal scheduler. Disabling the feature retains history.
