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
- Public registration disabled in favor of administrator-created accounts and secure password setup invitations.
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

DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

`APP_URL` must match the origin used for passkeys. In production, configure a stable `PASSKEYS_USER_HANDLE_SECRET`; changing that value can invalidate the relationship between users and their WebAuthn credentials.

Application icons, the optional full authentication logo, and sidebar footer links are managed from the authenticated settings interface rather than environment variables.

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

| Area | Location |
| --- | --- |
| Domain actions | `app/Actions` |
| Console commands | `app/Console/Commands` |
| Form Requests | `app/Http/Requests` |
| Policies | `app/Policies` |
| Inertia pages | `resources/js/pages` |
| Shared application UI | `resources/js/components/application` |
| Reusable DataTable | `resources/js/components/data-table` |
| shadcn-vue primitives | `resources/js/components/ui` |
| Feature tests | `tests/Feature` |
| Shared agent conventions | `.ai/rules` |

## License

This starter kit is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
