# Laravel + Vue Administration Starter Kit

This repository is an opinionated foundation for building secure administration applications with Laravel, Inertia, and Vue. It includes authentication, account lifecycle management, role-based authorization, reusable server-side CRUD patterns, and configurable application branding.

The interface and application messages are French-first. The codebase uses strict PHP and TypeScript checks, server-authoritative authorization, and focused PHPUnit coverage.

## Included features

### Authentication and account security

- Login, password reset, password confirmation, and email verification with Laravel Fortify.
- Passkey registration and authentication with WebAuthn.
- Two-factor authentication with recovery codes.
- Profile, password, passkey, and two-factor management from one security area.
- Disabled-account enforcement and session revocation.
- Optional public registration, disabled by default, alongside administrator-created accounts and secure password setup invitations.
- Interactive `make:admin` command for trusted first-administrator provisioning.

### Users, roles, and permissions

- Server-authorized user CRUD with search, filters, sorting, and pagination.
- Account suspension and reactivation, password reset, security reset, and permanent deletion.
- Per-user administrative activity history.
- Role management powered by Spatie Laravel Permission.
- Permissions declared by policies and synchronized into the database.
- Protected Administrator role with safeguards that preserve at least one active, verified administrator.
- Role-assignment boundaries that prevent operators from granting permissions they do not possess.

### Application foundation

- Responsive dashboard with representative sample panels.
- Complete Page CRUD reference implementation.
- Reusable server-side TanStack DataTable with URL-backed filtering, sorting, and pagination.
- Shared application components for headers, forms, empty states, resource tables, and pending-safe confirmations.
- Light, dark, and system appearance modes.
- Neutral, Ocean, and Forest administration themes.
- Administrator-managed application icon, authentication logo, and sidebar footer links.
- Browser, Apple, and installable-app favicon assets.
- Typed Laravel routes and controller actions through Wayfinder.

### Development quality

- Laravel 13, PHP 8.3, Inertia 3, Vue 3, TypeScript, Tailwind CSS 4, and shadcn-vue.
- PHPUnit feature coverage for authentication, authorization, CRUD, settings, localization, and seed data.
- PHPStan, Pint, ESLint, Prettier, and Vue TypeScript checks in one CI command.
- GitHub Actions workflow for the complete quality gate.

## Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js and npm
- A Laravel-supported database; SQLite is configured by default

## Quick start

Clone the repository and install the application:

```bash
git clone <repository-url> my-application
cd my-application
composer setup
php artisan storage:link
```

Choose your project preset before continuing (see [Project presets and modules](#project-presets-and-modules)):

```bash
php artisan starter:setup --list
php artisan starter:setup --preset=website --name="My Application" --dry-run
```

Remove `--dry-run` to apply the configuration.

Create the first administrator:

```bash
php artisan make:admin
```

The command asks for a name, email address, and hidden password. It validates the values with the application's normal account rules, marks the trusted CLI-created email as verified, synchronizes all policy-defined permissions, and assigns the protected `Administrator` role.

Start the local application:

```bash
composer run dev
```

The development command runs the Laravel server, Vite, the database queue listener, and the application log viewer. The default URL is `http://localhost:8000`.

## Configuration

Review `.env` before using the application. The most important settings are:

```dotenv
APP_NAME="My Application"
APP_URL=http://localhost:8000
APP_LOCALE=fr
FORTIFY_REGISTRATION_ENABLED=false

DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

`APP_URL` must match the origin used for passkeys. In production, configure a stable `PASSKEYS_USER_HANDLE_SECRET`; changing that value can invalidate the relationship between users and their WebAuthn credentials.

Set `FORTIFY_REGISTRATION_ENABLED=true` to expose the public registration page and account-creation endpoint. Leave it `false` when accounts must only be created by administrators.

Application icons, the optional full authentication logo, and sidebar footer links are managed from the authenticated settings interface rather than environment variables.

## Project presets and modules

Use `starter:setup` after installing a new project or when changing the built-in modules of an existing project. Run commands from the project root. Instructions are also available directly in Artisan:

```bash
php artisan starter:setup --help
php artisan starter:setup --list
```

| Preset     | Page management | Public pages | Media | Activity log | Notifications |
| ---------- | --------------- | ------------ | ----- | ------------ | ------------- |
| `website`  | Yes             | Yes          | Yes   | Yes          | Yes           |
| `portal`   | Yes             | No           | Yes   | Yes          | Yes           |
| `business` | No              | No           | Yes   | Yes          | Yes           |

Preview a configuration, then apply the same command without `--dry-run`:

```bash
php artisan starter:setup --preset=portal --name="Client Portal" --locale=fr --timezone=Europe/Zurich --dry-run
php artisan starter:setup --preset=portal --name="Client Portal" --locale=fr --timezone=Europe/Zurich
```

**Passing `--preset` resets every built-in module flag to that preset's defaults**, then applies any `--enable` and `--disable` overrides. Omit `--preset` to preserve other choices on an existing project:

```bash
php artisan starter:setup --enable=pages --enable=public_site --disable=activity
php artisan starter:setup --disable=public_site --disable=pages --dry-run
```

Repeat the option for each module; comma-separated lists are not supported. Available module keys are `pages`, `public_site`, `media`, `activity`, and `notifications`. Public pages require page management, so enable `pages` with `public_site`, or disable both together. Unknown modules, conflicting overrides, and missing dependencies are rejected before writing.

With no options, the command makes no changes. `--list` shows the currently loaded configuration and takes precedence over other options. `--dry-run` shows proposed settings without writing. Applying setup updates the environment file (using `.env.example` if none exists), preserves unrelated settings, and clears the configuration cache. Environment variables supplied by your server still take precedence over the file.

`--name`, `--locale`, and `--timezone` set `APP_NAME`, `APP_LOCALE`, and `APP_TIMEZONE`. The interface remains French-first; setting a locale does not generate translations. An application name already saved through admin settings takes precedence over `APP_NAME`; update it there when rebranding an existing installation.

Setup does not migrate, seed, or create accounts. After configuration, apply any pending migrations and synchronize permissions, then rebuild assets as needed:

```bash
php artisan migrate
php artisan permissions:sync
npm run build
```

Restart long-running workers to load configuration changes. Notification emails require a queue worker; activity retention requires Laravel's scheduler. Use `make:admin` for initial administrator provisioning. Disabling a module hides its navigation and blocks its routes while preserving data and permission assignments. Routes remain registered so Wayfinder imports continue to build.

| Customize                                   | Location                                     |
| ------------------------------------------- | -------------------------------------------- |
| Presets, module defaults, dependencies      | `config/starter.php`                         |
| Generated resource labels and enabled flags | `config/resources.php`                       |
| Page editing                                | `resources/js/components/pages/PageForm.vue` |
| Public page presentation                    | `resources/js/pages/content`                 |
| Upload limits and accepted types            | `config/media.php`                           |
| Audited models and allowed fields           | `config/activity.php`                        |
| Notification categories                     | `config/notifications.php`                   |

## Creating administrators

Run the interactive command whenever a trusted operator needs to create an administrator directly from the server:

```bash
php artisan make:admin
```

The command can create the first administrator on a clean database or an additional administrator later. Email addresses are normalized before uniqueness validation, passwords are never displayed in the terminal, and the creation is recorded in the user's administrative activity history.

Administrators created through the web interface follow the normal invitation workflow instead: the account receives a time-limited link for choosing its own password. Configure a real mail transport and keep a queue worker running outside local development so these invitations are delivered.

## Demo data

The default database seeder provides reproducible local demonstration data:

```bash
php artisan db:seed
```

It creates sample Pages and six verified accounts. `test@example.com` receives the Administrator role; the other accounts use first-name email addresses. All demo accounts use `password`.

Do not run the demo seeder in production. Use `php artisan make:admin` to provision real administrators with private passwords.

## Permissions

Application permissions are defined in policy `PERMISSIONS` constants. Optional `PERMISSION_DESCRIPTIONS` and `SENSITIVE_PERMISSIONS` metadata powers the role-management interface.

Synchronize policy permissions after adding or changing a policy:

```bash
php artisan permissions:sync
```

Preview the result without changing the database:

```bash
php artisan permissions:sync --dry-run
```

Synchronization creates missing permissions, grants every declared permission to the protected Administrator role, and reports orphaned permissions without deleting them. See [PERMISSIONS.md](PERMISSIONS.md) for the complete extension workflow.

## Reusing the CRUD pattern

The Page resource is the reference for new administrative resources. It demonstrates:

- Form Request validation and authorization.
- Single-purpose create, update, delete, and list actions.
- Policies and code-defined permission metadata.
- Deterministic server-side filtering, sorting, and pagination.
- Typed Wayfinder actions in Vue forms and navigation.
- Resource-specific columns and filters composed with shared application components.
- Focused feature tests for allowed and denied workflows.

See [REUSABLE_DATA_TABLE.md](REUSABLE_DATA_TABLE.md) for a worked Product example and the frontend/server data contract.

After adding or changing routes, regenerate the typed route files when they have not already been generated by Vite:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

## Generating a resource

Use `starter:resource` when adding a new administrative module. It generates a title/description CRUD starting point with authorization, search, pagination, Vue forms/pages, and tests. The generated files are yours to customize.

```bash
php artisan starter:resource --help
php artisan starter:resource ProjectNote --label="Client Notes" --dry-run
php artisan starter:resource ProjectNote --label="Client Notes"
```

Use a singular PascalCase model name such as `ProjectNote`. The generator refuses reserved names, existing files, and existing registrations; it does not overwrite an existing resource. Labels accept 1–80 letters, numbers, spaces, underscores, or hyphens and must start with a letter or number. Omit `--label` to derive it from the model name.

The command creates a model, migration, factory, seeder, policy, three Form Requests, four actions, controller, TypeScript types, Vue form and columns, four Vue pages, and a PHPUnit test. It also updates `config/resources.php` for routes and authorized navigation. It does not execute migrations, synchronize permissions, or run the generated seeder.

Pass `--disabled` to scaffold a module before making it accessible:

```bash
php artisan starter:resource ProjectNote --disabled
```

Set `enabled` to `true` on its entry in `config/resources.php` when ready, and clear cached configuration. Generated resources are independent of the built-in preset flags and cannot be toggled with `starter:setup --enable`. Changing the registry label changes navigation; edit the generated page labels for matching headings.

Before migrating, adapt the fields in the migration, model, requests, actions, Vue form, types, and tests. Then follow the resource-specific next steps printed by the command:

```bash
php artisan config:clear
php artisan route:clear
php artisan migrate
php artisan permissions:sync
php artisan wayfinder:generate --with-form
vendor/bin/pint --dirty --format agent
npx vp fmt resources/js/pages/project-notes resources/js/components/project-notes resources/js/types/project-notes.ts
php artisan test --compact tests/Feature/ProjectNoteManagementTest.php
npm run build
```

Permission synchronization grants the new permissions to the protected Administrator role. Assign appropriate permissions to other roles through role management. Media attachments, rich text, notifications, and audit logging are separate integrations; the generator does not automatically wire them into a new resource. For auditing, explicitly allow safe fields in `config/activity.php`.

### Reusing custom templates

Bundled templates live in `resources/stubs/resource`. Edit them to change future generation in this starter, or keep a reusable override directory:

```bash
mkdir -p resources/stubs/client
cp resources/stubs/resource/form.vue.stub resources/stubs/client/form.vue.stub
# Customize the copied template, then preview generation:
php artisan starter:resource ProjectNote --stubs=resources/stubs/client --dry-run
```

Use the same filenames as the bundled templates. Only matching files are overridden; missing files fall back to the bundled templates. Relative paths resolve from the working directory. Templates affect newly generated resources only.

| Placeholder     | Example for `ProjectNote`                  |
| --------------- | ------------------------------------------ |
| `{{model}}`     | `ProjectNote`                              |
| `{{plural}}`    | `ProjectNotes`                             |
| `{{route}}`     | `project-notes`                            |
| `{{table}}`     | `project_notes`                            |
| `{{variable}}`  | `projectNote`                              |
| `{{parameter}}` | `project_note`                             |
| `{{label}}`     | `Project Notes`, or the supplied `--label` |

## Project health checks

Run the read-only doctor after installing a project, changing configuration, or troubleshooting a deployment:

```bash
php artisan starter:doctor
php artisan starter:doctor --help
```

Each row shows a check, its status, and a suggested next step. Checks cover the encryption key, application URL and production debug setting, module dependencies, default database access, pending migrations, local storage directories, public storage link, frontend build manifest, mail transport configuration, sender address, queue configuration, and scheduler requirements. The database queue table is checked on its configured connection when using the database driver.

`PASS` means the stated check passed, `WARN` requires review, and `FAIL` indicates a setup problem. For automated tooling:

```bash
php artisan starter:doctor --json
php artisan starter:doctor --json --strict
```

JSON contains `checks` (each with `name`, `status`, and `message`), `failures`, and `warnings`. The command exits with **0** if there are no failures, or **1** if a check fails. `--strict` also exits with 1 on warnings. Operational checks remain warnings until verified separately, so strict mode is a review gate rather than proof that services are unhealthy.

The doctor uses loaded configuration, including any configuration cache. It does not send email, dispatch jobs, create files, change configuration, or run migrations. Error output omits database exception details and credentials. Database connection attempts use the driver's configured timeout.

Local storage checks inspect permissions as the CLI user without writing a probe; verify the web/worker user has equivalent access. A build manifest's presence does not validate every asset. Remote storage access, email delivery, queue worker execution, and scheduler execution remain explicitly unverified. Verify those through your hosting provider or service monitoring. In particular, supervise `php artisan queue:work` and run `php artisan schedule:run` every minute when using scheduled tasks; activity retention needs the scheduler when enabled with positive retention days.

Apply suggested fixes separately and rerun the doctor. On an existing installation, preserve its encryption key instead of generating a replacement.

## Development checks

Run the complete project quality gate:

```bash
composer ci:check
```

This runs ESLint, Prettier, Vue TypeScript checks, Pint, PHPStan, and the full PHPUnit suite.

Run an individual PHPUnit file while developing:

```bash
php artisan test --compact tests/Feature/PageManagementTest.php
```

Build production frontend assets:

```bash
npm run build
```

Run tests and frontend builds sequentially. Both operations use generated Vite assets, so running them concurrently can produce transient manifest failures.

## Production checklist

1. Configure production values for `APP_ENV`, `APP_URL`, `APP_KEY`, the database, sessions, cache, mail, queues, and `PASSKEYS_USER_HANDLE_SECRET`.
2. Install optimized PHP dependencies and build frontend assets.
3. Run migrations and synchronize permissions.
4. Create the storage symlink used by uploaded application branding.
5. Provision the first administrator with `make:admin` if the database has none.
6. Run a supervised queue worker so invitations and password setup notifications are delivered.
7. Point the web server at `public/` and verify the `/up` health endpoint.

A typical deployment sequence is:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force --no-interaction
php artisan permissions:sync --no-interaction
php artisan storage:link --no-interaction
php artisan optimize
```

Run `php artisan make:admin` separately when initial administrator provisioning is required because the command is intentionally interactive.

## Project map

| Area                     | Location                              |
| ------------------------ | ------------------------------------- |
| Domain actions           | `app/Actions`                         |
| Console commands         | `app/Console/Commands`                |
| Form Requests            | `app/Http/Requests`                   |
| Policies                 | `app/Policies`                        |
| Inertia pages            | `resources/js/pages`                  |
| Shared application UI    | `resources/js/components/application` |
| Reusable DataTable       | `resources/js/components/data-table`  |
| shadcn-vue primitives    | `resources/js/components/ui`          |
| Feature tests            | `tests/Feature`                       |
| Shared agent conventions | `.ai/rules`                           |

## License

This starter kit is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
