---
paths:
  - '{app/Providers/*.php,app/Http/Controllers/**,routes/**,resources/js/app.ts,resources/js/pages/**,resources/views/app.blade.php}'
---

# Views

## Keep Inertia document metadata server-owned
Laravel Head is the single authority for titles and metadata. Define defaults and static tags in AppServiceProvider, static page metadata on routes, and dynamic metadata in controllers before Inertia::render(). Keep createInertiaApp serverHead enabled and do not add Vue <Head> components for the same tags.
