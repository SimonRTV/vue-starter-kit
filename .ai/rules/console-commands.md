---
paths:
  - '{config/starter.php,config/resources.php,app/Http/Middleware/EnsureFeatureEnabled.php,routes/**,resources/stubs/resource/**,app/Console/Commands/MakeResource.php}'
---

# Console Commands

## Keep optional resource routes stable for Wayfinder
Presets and generated resources keep their route definitions registered so Wayfinder imports remain buildable in every preset. EnsureFeatureEnabled returns 404 for disabled configuration keys; navigation must also check the feature and policy. Disabling preserves data and permission assignments. starter:resource generates editable title/description CRUD from resources/stubs/resource, refuses existing files, and registers routes/navigation in config/resources.php.
