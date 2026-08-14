---
paths:
  - '{app/Models/ApplicationSetting.php,app/Actions/ApplicationSettings/UpdateSidebarFooterLinks.php,app/Http/Requests/Settings/UpdateSidebarFooterLinksRequest.php,resources/js/components/AppSidebar.vue,resources/js/pages/settings/SidebarFooterLinks.vue,resources/js/types/navigation.ts,tests/Feature/Settings/SidebarFooterLinkTest.php}'
---

# Feature Settings

## Use one fixed icon for sidebar footer links
Sidebar footer link data contains only title and URL. Always render ExternalLink in AppSidebar; do not expose or accept an icon parameter. Continue ignoring legacy stored icon keys when reading existing settings.
