---
paths:
  - app/Http/Middleware/HandleInertiaRequests.php
---

# Http Middleware

## Explicitly share Laravel Head with Inertia
Keep the lazy Laravel Head payload in HandleInertiaRequests::share(). The package's auto-registered global middleware can miss the live Laravel 13 request lifecycle, leaving props.head empty so serverHead hydration removes the document title and metadata.
