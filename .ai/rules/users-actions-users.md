---
paths:
  - '{app/Actions/Notifications/**,app/Notifications/InboxEmail.php,app/Http/Controllers/NotificationController.php,app/Actions/Users/RecordUserManagementEvent.php,app/Actions/Users/DeleteUser.php,config/notifications.php}'
---

# Users Actions Users

## Keep notification inboxes private and optional email queued
Use SendNotification for application messages: it writes a workspace database notification transactionally and queues optional InboxEmail after commit. Categories come from config/notifications.php; email is opt-in and requires an active verified recipient, rechecked before delivery. InboxEmail contains only a generic prompt and links to the inbox; never copy tokens or sensitive payloads into email or user notifications. All inbox queries and mutations must scope through the authenticated user's notifications relationship. Account lifecycle messages use a fixed safe mapping in RecordUserManagementEvent; mandatory setup/reset/verification emails remain independent. DeleteUser removes the recipient's notifications. The STARTER_NOTIFICATIONS flag disables this module without deleting history.
