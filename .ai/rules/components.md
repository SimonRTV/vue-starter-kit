---
paths:
  - '{app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppSidebar.vue}'
---

# Components

## Gate resource navigation with policy abilities
Expose resource navigation abilities through auth.can in HandleInertiaRequests using Laravel policy checks. Filter AppSidebar items with those booleans, while keeping every corresponding route or request independently authorized on the server.
