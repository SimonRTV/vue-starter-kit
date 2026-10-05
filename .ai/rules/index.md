# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| {config/activity.php,app/Models/Activity.php,app/Observers/ActivityObserver.php,app/Actions/Activity/**,app/Actions/Roles/**,app/Actions/Users/RecordUserManagementEvent.php} | .ai/rules/actions-users.md |
| {app/Models/ApplicationSetting.php,app/Policies/ApplicationSettingPolicy.php,app/Http/Controllers/Settings/ApplicationLogoController.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppLogoIcon.vue,resources/js/components/AppLogoFull.vue,resources/js/pages/settings/ApplicationLogo.vue,resources/js/layouts/auth/**} | .ai/rules/auth.md |
| {app/Console/Commands/SetupStarter.php,app/Console/Commands/MakeResource.php,config/starter.php} | .ai/rules/commands-console-commands.md |
| {app/Console/Commands/MakeAdmin.php,app/Actions/Users/CreateAdministrator.php,tests/Feature/Console/Commands/MakeAdminTest.php,README.md} | .ai/rules/commands.md |
| {app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppSidebar.vue} | .ai/rules/components.md |
| {config/starter.php,config/resources.php,app/Http/Middleware/EnsureFeatureEnabled.php,routes/**,resources/stubs/resource/**,app/Console/Commands/MakeResource.php} | .ai/rules/console-commands.md |
| {app/Http/Middleware/HandleAppearance.php,app/Models/User.php,resources/js/composables/use*Appearance.ts,resources/js/composables/useAdminTheme.ts,resources/js/pages/Welcome.vue,resources/js/pages/content/**} | .ai/rules/content.md |
| {config/fortify.php,app/Providers/FortifyServiceProvider.php,app/Actions/Fortify/CreateNewUser.php,resources/js/pages/auth/**,tests/Feature/Auth/Registration*.php} | .ai/rules/feature-auth.md |
| {app/Models/ApplicationSetting.php,app/Actions/ApplicationSettings/UpdateSidebarFooterLinks.php,app/Http/Requests/Settings/UpdateSidebarFooterLinksRequest.php,resources/js/components/AppSidebar.vue,resources/js/pages/settings/SidebarFooterLinks.vue,resources/js/types/navigation.ts,tests/Feature/Settings/SidebarFooterLinkTest.php} | .ai/rules/feature-settings.md |
| {vite.config.ts,package.json,composer.json,eslint.config.js,.prettierrc} | .ai/rules/general.md |
| app/Http/Middleware/HandleInertiaRequests.php | .ai/rules/http-middleware.md |
| {config/page_templates.php,app/Actions/Pages/*Template*.php,app/Actions/Pages/*Source.php,app/Models/PageTemplate.php,app/Models/PageFieldSet.php,app/Http/Controllers/Page*Controller.php,resources/js/pages/content/**} | .ai/rules/js-pages-content.md |
| resources/js/pages/settings/SidebarFooterLinks.vue | .ai/rules/js-pages-settings.md |
| {app/Models/ApplicationSetting.php,app/Http/Controllers/Settings/ApplicationLogoController.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppLogoFull.vue,resources/js/pages/settings/ApplicationLogo.vue,resources/js/layouts/auth/**} | .ai/rules/layouts-auth.md |
| {app/Actions/Exports/**,app/Http/Controllers/TableExportController.php,app/Http/Controllers/BulkPageController.php,resources/js/components/data-table/**,resources/js/components/application/ResourceTable.vue,resources/js/lib/tableViews.ts} | .ai/rules/lib.md |
| {app/Actions/Users/**,app/Policies/UserPolicy.php,app/Http/Controllers/{UserController.php,Settings/ProfileController.php},app/Http/Middleware/EnsureUserIsActive.php} | .ai/rules/middleware.md |
| {app/Models/ApplicationSetting.php,app/Http/Controllers/Settings/FrontendNavigationController.php,app/Http/Requests/Settings/UpdateFrontendNavigationRequest.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/frontend/**,resources/js/components/navigation/FrontendNavigationBuilder.vue,resources/js/pages/settings/FrontendNavigation.vue} | .ai/rules/navigation-js-pages-settings.md |
| {app/Actions/Pages/**,app/Http/Requests/*PageRequest.php,app/Http/Controllers/PageController.php,app/Http/Controllers/PublicPageController.php,resources/js/components/RichTextEditor.vue,resources/js/components/pages/PageForm.vue,resources/js/pages/pages/Show.vue,resources/js/pages/content/Show.vue} | .ai/rules/pages-content.md |
| {app/Models/ApplicationSetting.php,app/Http/Controllers/Settings/SidebarFooterLinkController.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppSidebar.vue,resources/js/pages/settings/SidebarFooterLinks.vue} | .ai/rules/pages-settings.md |
| {app/Actions/Media/**,app/Concerns/HasMediaAttachments.php,app/Http/Controllers/*Media*Controller.php,app/Http/Controllers/PageAttachmentController.php,app/Actions/Pages/DeletePage.php,app/Policies/MediaPolicy.php,config/media.php,config/filesystems.php} | .ai/rules/policies.md |
| {app/Policies/**,app/Actions/Permissions/**,app/Console/Commands/SyncPermissions.php,app/Http/Controllers/RoleController.php,resources/js/components/roles/RoleForm.vue} | .ai/rules/roles.md |
| {app/Models/ApplicationSetting.php,app/Policies/ApplicationSettingPolicy.php,app/Http/Controllers/Settings/ApplicationLogoController.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/components/AppLogoIcon.vue,resources/js/pages/settings/ApplicationLogo.vue} | .ai/rules/settings.md |
| {app/Models/ApplicationSetting.php,app/Http/Requests/Settings/UpdateSidebarFooterLinksRequest.php,resources/js/components/AppSidebar.vue,resources/js/components/NavFooter.vue,resources/js/pages/settings/SidebarFooterLinks.vue,resources/js/types/navigation.ts,tests/Feature/Settings/SidebarFooterLinkTest.php} | .ai/rules/types-feature-settings.md |
| {app/Actions/Notifications/**,app/Notifications/InboxEmail.php,app/Http/Controllers/NotificationController.php,app/Actions/Users/RecordUserManagementEvent.php,app/Actions/Users/DeleteUser.php,config/notifications.php} | .ai/rules/users-actions-users.md |
| {app/Policies/UserPolicy.php,app/Policies/RolePolicy.php,app/Http/Requests/*UserRequest.php,app/Actions/Users/**,resources/js/components/users/UserForm.vue} | .ai/rules/users.md |
| {app/Providers/*.php,app/Http/Controllers/**,routes/**,resources/js/app.ts,resources/js/pages/**,resources/views/app.blade.php} | .ai/rules/views.md |
