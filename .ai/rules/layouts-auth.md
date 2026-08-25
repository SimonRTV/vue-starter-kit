---
paths:
  - '{app/Models/ApplicationSetting.php,app/Http/Controllers/Settings/ApplicationLogoController.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppLogoFull.vue,resources/js/pages/settings/ApplicationLogo.vue,resources/js/layouts/auth/**}'
---

# Layouts Auth

## Use background-aware full-logo variants
Keep full-logo branding global and Administrator-only. branding.fullLogoUrl is the default for light backgrounds; branding.darkFullLogoUrl is optional for dark backgrounds. AppLogoFull switches automatically with dark mode, accepts an explicit background for fixed surfaces, and falls back from the dark variant to the light logo and then the application icon.
