---
paths:
  - '{app/Console/Commands/SetupStarter.php,app/Console/Commands/MakeResource.php,config/starter.php}'
---

# Commands Console Commands

## Setup preserves explicit project choices
starter:setup only resets module defaults when --preset is explicit; individual overrides preserve other environment settings and validate module dependencies before writing. Keep preview/list operations read-only. Resource template overrides fall back to bundled stubs file by file, and generation must refuse existing destinations even with custom templates.
