---
paths:
  - '{app/Models/ApplicationSetting.php,app/Policies/ApplicationSettingPolicy.php,app/Http/Controllers/Settings/ApplicationLogoController.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppLogoIcon.vue,resources/js/pages/settings/ApplicationLogo.vue}'
---

# Settings

## Keep application branding global and administrator-only
Persist the logo in application_settings without a user_id. Only the protected Administrator role may view or mutate the logo setting, while branding.logoUrl is shared with every Inertia response and AppLogoIcon retains the built-in fallback.

## Keep application branding global and administrator-only
Persist icon and full-logo paths in application_settings without a user_id. Only the protected Administrator role may view or mutate them. Share branding.iconUrl and branding.fullLogoUrl globally; compact chrome uses AppLogoIcon, while authentication branding falls back to it when no full logo is set.

## Supersede the legacy single-logo prop
The older branding.logoUrl wording above is obsolete. New work must use branding.iconUrl and branding.fullLogoUrl with the separate compact-icon and authentication-logo behavior.
