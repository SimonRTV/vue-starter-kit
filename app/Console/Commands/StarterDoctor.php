<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class StarterDoctor extends Command
{
    protected $signature = 'starter:doctor {--json : Output a machine-readable report} {--strict : Treat warnings as failures}';

    protected $description = 'Check project readiness and explain fixes without changing application data';

    protected $help = <<<'HELP'
Run after installation, after changing configuration, or during deployment troubleshooting:
  php artisan starter:doctor
  php artisan starter:doctor --json
  php artisan starter:doctor --strict

Checks loaded configuration, database connectivity and pending migrations,
local storage permissions, public storage link, frontend assets, mail setup,
queue configuration, and scheduler requirements. No mail or test jobs are sent,
no files are created, and no migrations or fixes are applied.

PASS means the stated check passed, WARN needs review, FAIL needs a fix.
Exit code: 0 when there are no failures; 1 on failures (or warnings with --strict).
Workers, scheduler execution, remote storage, and actual email delivery cannot
be verified by this read-only check. They are reported explicitly as unverified.
Storage permission checks use the CLI user, which may differ from the web user.
Connection attempts use your configured database driver's connection timeout.
See README.md, Project health checks, for details.
HELP;

    public function handle(Migrator $migrator): int
    {
        $checks = [];
        $key = config('app.key', '');
        $decoded = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $validKey = is_string($decoded) && Encrypter::supported($decoded, config('app.cipher'));
        $checks[] = $this->result('application.key', $validKey ? 'PASS' : 'FAIL', $validKey ? 'Encryption key is valid.' : 'Set a valid APP_KEY. For a new installation, run php artisan key:generate; preserve the key of an existing app.');
        $secure = ! app()->isProduction() || ! config('app.debug');
        $checks[] = $this->result('application.debug', $secure ? 'PASS' : 'FAIL', $secure ? 'Debug mode is appropriate for this environment.' : 'Set APP_DEBUG=false in production and refresh cached configuration.');
        $url = config('app.url', '');
        $validUrl = filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
        $checks[] = $this->result('application.url', ! $validUrl ? 'FAIL' : (app()->isProduction() && parse_url($url, PHP_URL_SCHEME) !== 'https' ? 'WARN' : 'PASS'), 'APP_URL must match the browser origin; use HTTPS in production for authentication and passkeys.');
        foreach (config('starter.modules', []) as $name => $module) {
            foreach ($module['requires'] as $dependency) {
                if (config('starter.features.'.$name) && ! config('starter.features.'.$dependency)) {
                    $checks[] = $this->result('modules.dependencies', 'FAIL', 'An enabled module is missing a dependency. Run starter:setup --list and enable its dependencies.');
                }
            }
        }

        try {
            DB::connection()->getPdo();
            $checks[] = $this->result('database.connection', 'PASS', 'Default database connection is accessible.');
            if (! $migrator->repositoryExists()) {
                $checks[] = $this->result('database.migrations', 'FAIL', 'Migration repository is missing. Run php artisan migrate.');
            } else {
                $pending = array_diff(array_keys($migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')])), $migrator->getRepository()->getRan());
                $checks[] = $this->result('database.migrations', $pending === [] ? 'PASS' : 'FAIL', $pending === [] ? 'No pending migrations.' : count($pending).' pending migrations. Review and run php artisan migrate.');
            }
        } catch (Throwable) {
            $checks[] = $this->result('database.access', 'FAIL', 'Database connection or migration inspection failed. Check DB configuration, service availability, and database permissions.');
        }

        foreach (['storage' => storage_path(), 'cache' => base_path('bootstrap/cache'), 'views' => storage_path('framework/views'), 'sessions' => storage_path('framework/sessions'), 'logs' => storage_path('logs')] as $name => $path) {
            $checks[] = $this->directory('storage.'.$name, $path);
        }
        foreach (array_unique([config('filesystems.default'), 'public', ...(config('starter.features.media') ? ['media'] : [])]) as $disk) {
            $definition = config('filesystems.disks.'.$disk);
            if (! is_array($definition)) {
                $checks[] = $this->result('disk.'.$disk, 'FAIL', 'Disk configuration is missing. Review config/filesystems.php.');
            } elseif (($definition['driver'] ?? null) === 'local') {
                $checks[] = $this->directory('disk.'.$disk, $definition['root'] ?? '');
            } else {
                $checks[] = $this->result('disk.'.$disk, 'WARN', 'Remote disk access is unverified. Check credentials and provider access separately.');
            }
        }
        $linked = realpath(public_path('storage')) !== false && realpath(public_path('storage')) === realpath(config('filesystems.disks.public.root', ''));
        $checks[] = $this->result('storage.public_link', $linked ? 'PASS' : 'WARN', $linked ? 'Public storage link resolves to the configured root.' : 'Public storage link is missing or incorrect. Review it and run php artisan storage:link.');
        $manifest = is_file(public_path('build/manifest.json'));
        $hot = is_file(public_path('hot'));
        $checks[] = $this->result('frontend.assets', $manifest ? ($hot && app()->isProduction() ? 'WARN' : 'PASS') : 'WARN', $manifest ? ($hot && app()->isProduction() ? 'Production has a Vite hot file. Remove stale public/hot after stopping the dev server.' : 'Build manifest exists; asset contents were not validated.') : 'No build manifest. Run npm run build for deployment; a local Vite server is not verified here.');

        $mailer = config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        try {
            Mail::mailer($mailer)->getSymfonyTransport();
            $checks[] = $this->result('mail.transport', 'WARN', in_array($transport, ['log', 'array'], true) ? 'Mail is captured locally and will not be delivered. Configure a delivery transport for invitations and password resets.' : 'Mail transport can be constructed; credentials and delivery are unverified. Verify delivery separately.');
        } catch (Throwable) {
            $checks[] = $this->result('mail.transport', 'FAIL', 'Mail transport cannot be constructed. Check MAIL configuration and required driver dependencies.');
        }
        $sender = config('mail.from.address');
        $validSender = is_string($sender) && filter_var($sender, FILTER_VALIDATE_EMAIL) && ! str_ends_with($sender, '@example.com');
        $checks[] = $this->result('mail.sender', $validSender ? 'PASS' : 'WARN', $validSender ? 'Sender address has a valid format; provider verification is unverified.' : 'Set MAIL_FROM_ADDRESS to a valid sender authorized by your mail provider.');

        $queue = config('queue.connections.'.config('queue.default'));
        $driver = is_array($queue) ? ($queue['driver'] ?? null) : null;
        if (! is_string($driver) || $driver === 'null') {
            $checks[] = $this->result('queue.configuration', 'FAIL', 'Queue connection is missing or discards jobs. Review QUEUE_CONNECTION and config/queue.php.');
        } else {
            $checks[] = $this->result('queue.worker', 'WARN', $driver === 'sync' ? 'Jobs run synchronously. Configure an asynchronous connection and supervised queue:work for background delivery.' : 'Worker execution is unverified. Run a supervised php artisan queue:work for the configured connection.');
            if ($driver === 'database') {
                try {
                    $exists = DB::connection($queue['connection'] ?? null)->getSchemaBuilder()->hasTable($queue['table'] ?? 'jobs');
                    $checks[] = $this->result('queue.table', $exists ? 'PASS' : 'FAIL', $exists ? 'Configured database queue table exists.' : 'Queue table is missing. Run pending migrations on the queue database.');
                } catch (Throwable) {
                    $checks[] = $this->result('queue.table', 'FAIL', 'Cannot inspect the queue database. Check its connection and permissions.');
                }
            }
        }
        $retention = config('starter.features.activity') && config('activity.retention_days') > 0;
        $checks[] = $this->result('scheduler.execution', 'WARN', $retention ? 'Activity retention needs the scheduler. Configure schedule:run every minute; execution is unverified.' : 'Scheduler execution is unverified. Activity retention is inactive; configure schedule:run every minute for scheduled tasks.');
        $failed = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'FAIL'));
        $warnings = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'WARN'));
        if ($this->option('json')) {
            $this->line(json_encode(['checks' => $checks, 'failures' => $failed, 'warnings' => $warnings], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $rows = array_map(static function (array $check): array {
                $color = match ($check['status']) {
                    'PASS' => 'green',
                    'WARN' => 'yellow',
                    default => 'red',
                };

                return [$check['name'], '<fg='.$color.';options=bold>'.$check['status'].'</>', $check['message']];
            }, $checks);
            $this->table(['Check', 'Status', 'Finding / next step'], $rows);
            $this->info($failed.' failures, '.$warnings.' warnings. No fixes applied. Use --help for scope and exit codes.');
        }

        return $failed > 0 || ($this->option('strict') && $warnings > 0) ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{name: string, status: string, message: string} */
    private function result(string $name, string $status, string $message): array
    {
        return compact('name', 'status', 'message');
    }

    /** @return array{name: string, status: string, message: string} */
    private function directory(string $name, string $path): array
    {
        $ready = is_dir($path) && is_writable($path);

        return $this->result($name, $ready ? 'PASS' : 'FAIL', $ready ? 'Directory exists and is writable by the CLI user; no write probe performed.' : 'Directory is missing or not writable. Create the configured directory and grant the application user write access.');
    }
}
