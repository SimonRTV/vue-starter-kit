---
paths:
  - '{app/Models/ApplicationSetting.php,app/Http/Controllers/Settings/SidebarFooterLinkController.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppSidebar.vue,resources/js/pages/settings/SidebarFooterLinks.vue}'
---

# Pages Settings

## Keep sidebar footer links global and administrator-managed
Store the ordered sidebar footer link list in application_settings and fall back to the built-in repository and documentation links until customized. Only Administrators may view or update the manager; validate HTTP(S) URLs and supported icons server-side, then share the resolved list with every Inertia response.
