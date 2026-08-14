---
paths:
  - '{app/Actions/Users/**,app/Policies/UserPolicy.php,app/Http/Controllers/{UserController.php,Settings/ProfileController.php},app/Http/Middleware/EnsureUserIsActive.php}'
---

# Middleware

## Centralize user account lifecycle safeguards
Admin-created accounts use password setup invitations; public registration stays disabled. Suspension is the normal reversible offboarding path and revokes sessions, while explicit permanent deletion remains available. All admin and self-service mutations must preserve at least one active, verified Administrator and use the shared user actions so auditing and session revocation are not bypassed.
