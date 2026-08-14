---
paths:
  - '{app/Models/ApplicationSetting.php,app/Http/Requests/Settings/UpdateSidebarFooterLinksRequest.php,resources/js/components/AppSidebar.vue,resources/js/components/NavFooter.vue,resources/js/pages/settings/SidebarFooterLinks.vue,resources/js/types/navigation.ts,tests/Feature/Settings/SidebarFooterLinkTest.php}'
---

# Types Feature Settings

## Distinguish internal and external sidebar footer links
This supersedes the fixed-icon wording. Store only title and URL; accept internal paths beginning with one slash and external HTTP(S) URLs. Internal links use Inertia navigation in the same tab without an icon. Only external links open in a new tab and render ExternalLink. Continue ignoring legacy stored icon keys.
