---
paths:
  - '{app/Models/ApplicationSetting.php,app/Http/Controllers/Settings/FrontendNavigationController.php,app/Http/Requests/Settings/UpdateFrontendNavigationRequest.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/frontend/**,resources/js/components/navigation/FrontendNavigationBuilder.vue,resources/js/pages/settings/FrontendNavigation.vue}'
---

# Navigation Js Pages Settings

## Keep public navigation global and administrator-managed
Store ordered links and dropdown groups under navigation.frontend in application_settings. Only Administrators may manage it; share the validated resolved menu globally and render internal paths with Inertia, anchors against the homepage, and external HTTP(S) links with noopener noreferrer.
