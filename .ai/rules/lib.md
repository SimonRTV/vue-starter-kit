---
paths:
  - '{app/Actions/Exports/**,app/Http/Controllers/TableExportController.php,app/Http/Controllers/BulkPageController.php,resources/js/components/data-table/**,resources/js/components/application/ResourceTable.vue,resources/js/lib/tableViews.ts}'
---

# Lib

## Extend shared tables with bounded selection and safe exports
Reuse DataTable and ResourceTable for selection; selections are limited to the visible page and cleared when results change. Bulk page status changes lock and authorize every row before per-model updates in one transaction so observers keep audit history. CSV exports reuse each resource's validated listing query and view permission, omit bodies/secrets, cap results at 10,000 without silent truncation, and escape spreadsheet formulas. Named views store only filters/sort/page size (not page number) in browser storage scoped by user ID and table; validate parsed storage and handle unavailable storage.
