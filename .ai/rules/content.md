---
paths:
  - '{app/Http/Middleware/HandleAppearance.php,app/Models/User.php,resources/js/composables/use*Appearance.ts,resources/js/composables/useAdminTheme.ts,resources/js/pages/Welcome.vue,resources/js/pages/content/**}'
---

# Content

## Keep dashboard and public appearance independent
Dashboard appearance and admin_theme are per-user database preferences and must be applied only on authenticated workspace pages. Public pages always use the neutral palette and the separate frontend_appearance cookie/localStorage preference; public controls must never overwrite dashboard preferences.
