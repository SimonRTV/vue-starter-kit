---
paths:
  - '{config/fortify.php,app/Providers/FortifyServiceProvider.php,app/Actions/Fortify/CreateNewUser.php,resources/js/pages/auth/**,tests/Feature/Auth/Registration*.php}'
---

# Feature Auth

## Keep public registration environment-gated
Public registration is disabled by default and enabled only with FORTIFY_REGISTRATION_ENABLED=true. Gate it through Fortify's registration feature so GET and POST routes disappear together; only show signup links when the feature is enabled, and keep disabled and enabled regression coverage.
