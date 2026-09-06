<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SetupStarter extends Command
{
    protected $signature = 'starter:setup
        {--preset= : Apply website, portal, or business defaults}
        {--enable=* : Enable a module; repeat for multiple modules}
        {--disable=* : Disable a module; repeat for multiple modules}
        {--list : Show presets, modules, and customization locations}
        {--name= : Application name}
        {--locale= : Default language code}
        {--timezone= : IANA timezone, for example Europe/Zurich}
        {--dry-run : Preview changes without writing the environment file}';

    protected $description = 'Configure a project preset and identity without migrating or seeding data';

    protected $help = <<<'HELP'
Use after installing a new project, or later to change its built-in modules.

Explore:
  php artisan starter:setup --list

Preview a new project configuration, then remove --dry-run to apply it:
  php artisan starter:setup --preset=portal --name="Client Portal" --locale=fr --timezone=Europe/Zurich --dry-run

Change only selected modules on an existing project:
  php artisan starter:setup --enable=pages --enable=public_site --disable=activity

An explicit --preset resets ALL built-in module flags to that preset before
applying overrides. Without --preset, other environment choices are preserved.
Repeat --enable or --disable for multiple modules; do not use comma-separated lists.
public_site requires pages. Disable both together if removing page management.
--list takes precedence over other options and never writes configuration.

The command updates the environment file and clears cached configuration.
It does not migrate, seed, create an administrator, or delete module data.
--name sets APP_NAME; an identity already saved in admin settings takes precedence.
Generated resources are enabled separately in config/resources.php.

After applying: run php artisan migrate, php artisan permissions:sync, and
npm run build when needed. Restart long-running workers. Notification emails
need a queue worker; activity retention needs Laravel's scheduler.

Full workflow and preset comparison: README.md, Project presets and modules.
HELP;

    public function handle(Filesystem $files): int
    {
        if ($this->option('list')) {
            $modules = [];
            foreach (config('starter.modules') as $key => $module) {
                $modules[] = [$key, config('starter.features.'.$key) ? 'yes' : 'no', $module['customize']];
            }
            $presets = [];
            foreach (config('starter.presets') as $key => $features) {
                $presets[] = [$key, implode(', ', array_keys(array_filter($features)))];
            }
            $this->table(['Module', 'Enabled', 'Customize'], $modules);
            $this->table(['Preset', 'Enabled modules'], $presets);
            $this->line('Generated modules: config/resources.php. Templates: resources/stubs/resource.');
            $this->line('Example: starter:setup --preset=portal --disable=activity --dry-run');
            $this->line('Instructions: php artisan starter:setup --help or README.md.');

            return self::SUCCESS;
        }

        $values = [
            'preset' => $this->option('preset'),
            'name' => $this->option('name'),
            'locale' => $this->option('locale'),
            'timezone' => $this->option('timezone'),
            'enable' => $this->option('enable'),
            'disable' => $this->option('disable'),
        ];
        $validator = Validator::make($values, [
            'preset' => ['nullable', Rule::in(array_keys(config('starter.presets')))],
            'name' => ['nullable', 'string', 'max:100', 'regex:/^[^\x00-\x1F\x7F$]+$/u'],
            'locale' => ['nullable', 'string', 'regex:/^[a-z]{2,3}(?:[_-][A-Za-z]{2,4})?$/'],
            'timezone' => ['nullable', 'timezone'],
            'enable.*' => ['required', Rule::in(array_keys(config('starter.modules')))],
            'disable.*' => ['required', Rule::in(array_keys(config('starter.modules')))],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (array_intersect($values['enable'], $values['disable']) !== []) {
            $this->error('A module cannot be both enabled and disabled.');

            return self::FAILURE;
        }

        $path = app()->environmentFilePath();
        $source = $files->exists($path) ? $path : base_path('.env.example');
        $contents = $files->get($source);
        try {
            $environment = Dotenv::parse($contents);
        } catch (InvalidFileException) {
            $this->error('The environment file is invalid. Fix its syntax before applying setup changes.');

            return self::FAILURE;
        }
        $preset = $values['preset'] ?? $environment['STARTER_PRESET'] ?? config('starter.preset');
        if (! array_key_exists($preset, config('starter.presets'))) {
            $this->error('The environment contains an unknown preset. Choose --preset explicitly.');

            return self::FAILURE;
        }
        $features = config('starter.presets.'.$preset);
        $updates = [];
        if ($values['preset'] !== null) {
            $updates['STARTER_PRESET'] = $preset;
        } else {
            foreach ($features as $feature => $enabled) {
                $key = 'STARTER_'.strtoupper($feature);
                if (isset($environment[$key])) {
                    $features[$feature] = in_array(strtolower($environment[$key]), ['true', '(true)', '1'], true);
                }
            }
        }
        foreach (['enable' => true, 'disable' => false] as $option => $enabled) {
            foreach ($values[$option] as $feature) {
                $features[$feature] = $enabled;
            }
        }
        foreach ($features as $feature => $enabled) {
            foreach (config('starter.modules.'.$feature.'.requires', []) as $dependency) {
                if ($enabled && ! $features[$dependency]) {
                    $this->error($feature.' requires '.$dependency.'. Enable the dependency or disable '.$feature.'.');

                    return self::FAILURE;
                }
            }
            if ($values['preset'] !== null || in_array($feature, [...$values['enable'], ...$values['disable']], true)) {
                $updates['STARTER_'.strtoupper($feature)] = $enabled ? 'true' : 'false';
            }
        }
        foreach (['name' => 'APP_NAME', 'locale' => 'APP_LOCALE', 'timezone' => 'APP_TIMEZONE'] as $option => $key) {
            if (is_string($values[$option]) && $values[$option] !== '') {
                $updates[$key] = $values[$option];
            }
        }

        if ($updates === []) {
            $this->info('No changes requested. Use --list to explore presets and modules, or --help for the setup workflow.');

            return self::SUCCESS;
        }

        $this->table(['Setting', 'Value'], array_map(
            static fn (string $key, string $value): array => [$key, $value],
            array_keys($updates), array_values($updates),
        ));

        if ($this->option('dry-run')) {
            $this->info('Preview only. Re-run without --dry-run to apply these settings. See --help or README.md for next steps.');

            return self::SUCCESS;
        }

        foreach ($updates as $key => $value) {
            $encoded = str_starts_with($key, 'STARTER_') && in_array($value, ['true', 'false'], true)
                ? $value
                : '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
            $line = $key.'='.$encoded;
            $pattern = '/^[ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=.*$/m';
            $contents = preg_match($pattern, $contents)
                ? (string) preg_replace_callback($pattern, static fn (): string => $line, $contents)
                : rtrim($contents)."\n".$line."\n";
        }

        $files->replace($path, $contents, 0600);
        $this->call('config:clear');
        $this->info('Project configured. Restart long-running workers to load the new configuration.');

        $this->line('Next: php artisan migrate, php artisan permissions:sync, and npm run build.');
        $this->line('Customize saved application identity in admin settings. Use a queue worker for notification emails and the scheduler for activity retention.');

        return self::SUCCESS;
    }
}
