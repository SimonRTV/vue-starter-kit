---
paths:
  - '{app/Models/ApplicationSetting.php,app/Policies/ApplicationSettingPolicy.php,app/Http/Controllers/Settings/ApplicationLogoController.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppLogoIcon.vue,resources/js/components/AppLogoFull.vue,resources/js/pages/settings/ApplicationLogo.vue,resources/js/layouts/auth/**}'
---

# Auth

## Use separate icon and full logo variants
Keep application branding global and Administrator-only. Use branding.iconUrl for square and compact application chrome, and branding.fullLogoUrl for authentication screens; full-logo consumers must fall back to AppLogoIcon when the optional full logo is not configured.
