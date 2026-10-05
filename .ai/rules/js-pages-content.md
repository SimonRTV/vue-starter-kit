---
paths:
  - '{config/page_templates.php,app/Actions/Pages/*Template*.php,app/Actions/Pages/*Source.php,app/Models/PageTemplate.php,app/Models/PageFieldSet.php,app/Http/Controllers/Page*Controller.php,resources/js/pages/content/**}'
---

# Js Pages Content

## Keep page template code and public data sources explicitly registered
Page layouts are developer-owned Vue/Inertia components registered in config/page_templates.php; admin users create templates and reusable field sets, and editors store values by field-set key and field key. Data sources extend TemplateDataSource and enforce public scopes, allowed ordering, bounded results and explicit serialization. Never instantiate model or component names from page input. Revalidate stored values against current definitions on public reads, omit removed fields, fall back to content/Show for unavailable renderers, and preserve publication, SEO, and private-preview behavior.
