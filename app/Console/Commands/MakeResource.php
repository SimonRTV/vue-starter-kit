<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ParseError;
use PhpToken;
use RuntimeException;
use Throwable;

class MakeResource extends Command
{
    protected $signature = 'starter:resource {name : Singular PascalCase model name} {--label= : Human-readable plural label}
        {--disabled : Register the resource disabled until explicitly enabled in config/resources.php}
        {--stubs= : Directory of custom stub overrides; missing files use the bundled templates}
        {--dry-run : List files without writing them}';

    protected $description = 'Generate an editable, authorized Laravel and Vue title/description resource';

    protected $help = <<<'HELP'
Use when adding a new administrative resource to an installed project.
Generates editable title/description CRUD: model, migration, factory, seeder,
policy, requests, actions, controller, Vue pages/form/columns, types, and tests.
Routes and authorized navigation are registered in config/resources.php.

Preview, then remove --dry-run to create the files:
  php artisan starter:resource ProjectNote --label="Client Notes" --dry-run

Generate a module that remains hidden and returns 404 until enabled:
  php artisan starter:resource ProjectNote --disabled

Reuse custom templates (missing overrides fall back to bundled templates):
  php artisan starter:resource ProjectNote --stubs=resources/stubs/client

Use a singular PascalCase name. Existing files and reserved names are refused.
Labels accept 1-80 letters/numbers/spaces/underscores/hyphens, starting with a
letter or number. Templates use the filenames in resources/stubs/resource.
--dry-run validates and lists destinations without writing files or the registry.

Before migrating, customize generated fields, validation, actions, Vue form,
types, and tests. To enable a disabled resource, set its enabled value to true
in config/resources.php. Generation does not run migrations or seed demo data.

Next steps (replace ProjectNote/project-notes for your resource):
  php artisan config:clear
  php artisan route:clear
  php artisan migrate
  php artisan permissions:sync
  php artisan wayfinder:generate --with-form
  vendor/bin/pint --dirty --format agent
  npx vp fmt resources/js/pages/project-notes resources/js/components/project-notes resources/js/types/project-notes.ts
  php artisan test --compact tests/Feature/ProjectNoteManagementTest.php
  npm run build

Full customization workflow and template placeholders: README.md, Generating a resource.
HELP;

    public function handle(Filesystem $files): int
    {
        $name = (string) $this->argument('name');
        if (! preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/', $name) || Str::singular($name) !== $name) {
            $this->error('Use a singular PascalCase name, for example Project or ProjectNote.');

            return self::FAILURE;
        }
        try {
            $tokens = PhpToken::tokenize('<?php class '.$name.' {}', TOKEN_PARSE);
            if (! $tokens[3]->is(T_STRING)) {
                throw new ParseError('Invalid class name.');
            }
        } catch (ParseError) {
            $this->error('This name is reserved by PHP.');

            return self::FAILURE;
        }

        $plural = Str::pluralStudly($name);
        $route = Str::kebab($plural);
        $table = Str::snake($plural);
        $label = $this->option('label') ?? Str::headline($plural);
        if (! preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _-]{0,79}$/u', $label)) {
            $this->error('The label must contain 1–80 letters, numbers, spaces, underscores, or hyphens.');

            return self::FAILURE;
        }
        $stubDirectory = $this->option('stubs');
        if ($stubDirectory !== null && ! $files->isDirectory($stubDirectory)) {
            $this->error('The custom stub directory does not exist.');

            return self::FAILURE;
        }
        $registryPath = config_path('resources.php');
        /** @var array<string, array{model: string, controller: string, label: string, enabled: bool}> $registry */
        $registry = $files->getRequire($registryPath);
        if (isset($registry[$route]) || in_array($route, ['notifications', 'activity', 'media', 'files', 'pages', 'users', 'roles', 'settings', 'content', 'dashboard', 'login', 'register'], true)) {
            $this->error('This resource name is already registered or reserved.');

            return self::FAILURE;
        }

        $replacements = [
            '{{model}}' => $name, '{{plural}}' => $plural, '{{route}}' => $route,
            '{{table}}' => $table, '{{variable}}' => Str::camel($name),
            '{{parameter}}' => Str::snake($name), '{{label}}' => $label,
        ];
        $manifest = [
            'model.php' => 'app/Models/'.$name.'.php',
            'migration.php' => 'database/migrations/'.date('Y_m_d_His').'_create_'.$table.'_table.php',
            'factory.php' => 'database/factories/'.$name.'Factory.php',
            'seeder.php' => 'database/seeders/'.$name.'Seeder.php',
            'policy.php' => 'app/Policies/'.$name.'Policy.php',
            'controller.php' => 'app/Http/Controllers/'.$name.'Controller.php',
            'index-request.php' => 'app/Http/Requests/Index'.$name.'Request.php',
            'store-request.php' => 'app/Http/Requests/Store'.$name.'Request.php',
            'update-request.php' => 'app/Http/Requests/Update'.$name.'Request.php',
            'create.php' => 'app/Actions/'.$plural.'/Create'.$name.'.php',
            'update.php' => 'app/Actions/'.$plural.'/Update'.$name.'.php',
            'delete.php' => 'app/Actions/'.$plural.'/Delete'.$name.'.php',
            'list.php' => 'app/Actions/'.$plural.'/List'.$plural.'.php',
            'types.ts' => 'resources/js/types/'.$route.'.ts',
            'form.vue' => 'resources/js/components/'.$route.'/'.$name.'Form.vue',
            'columns.ts' => 'resources/js/components/'.$route.'/columns.ts',
            'index.vue' => 'resources/js/pages/'.$route.'/Index.vue',
            'create.vue' => 'resources/js/pages/'.$route.'/Create.vue',
            'edit.vue' => 'resources/js/pages/'.$route.'/Edit.vue',
            'show.vue' => 'resources/js/pages/'.$route.'/Show.vue',
            'test.php' => 'tests/Feature/'.$name.'ManagementTest.php',
        ];
        if ($files->glob(base_path('database/migrations/*_create_'.$table.'_table.php')) !== []) {
            $this->error('A migration already exists for '.$table.'.');

            return self::FAILURE;
        }

        $rendered = [];
        foreach ($manifest as $stub => $path) {
            if ($files->exists(base_path($path))) {
                $this->error('Refusing to overwrite '.$path.'.');

                return self::FAILURE;
            }
            $stubPath = dirname(__DIR__, 3).'/resources/stubs/resource/'.$stub.'.stub';
            if ($stubDirectory !== null && $files->isFile($stubDirectory.'/'.$stub.'.stub')) {
                $stubPath = $stubDirectory.'/'.$stub.'.stub';
            }
            $rendered[$path] = strtr($files->get($stubPath), $replacements);
        }
        foreach (array_keys($rendered) as $path) {
            $this->line($path);
        }
        $this->line('Label: '.$label.'; enabled: '.($this->option('disabled') ? 'no' : 'yes').'.');
        $this->line('Update config/resources.php (routes and authorized navigation).');
        if ($this->option('dry-run')) {
            $this->info('Preview only. Re-run without --dry-run to generate these files. See --help or README.md for next steps.');

            return self::SUCCESS;
        }

        $registry[$route] = ['model' => 'App\\Models\\'.$name, 'controller' => 'App\\Http\\Controllers\\'.$name.'Controller', 'label' => $label, 'enabled' => ! $this->option('disabled')];
        $created = [];
        try {
            foreach ($rendered as $path => $contents) {
                $destination = base_path($path);
                $files->ensureDirectoryExists(dirname($destination));
                $stream = @fopen($destination, 'x');
                if ($stream === false) {
                    throw new RuntimeException('Could not create '.$path.'.');
                }
                $created[] = $destination;
                try {
                    if (fwrite($stream, $contents) !== strlen($contents)) {
                        throw new RuntimeException('Could not completely write '.$path.'.');
                    }
                } finally {
                    fclose($stream);
                }
            }
            $files->replace($registryPath, "<?php\n\nreturn ".var_export($registry, true).";\n");
        } catch (Throwable $exception) {
            $files->delete($created);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Resource generated. Next steps:');
        $this->line('Customize fields in the generated migration, requests, actions, form, and types.');
        $this->line('Enable or rename this module in config/resources.php. Add safe audit fields in config/activity.php if needed.');
        $this->line('Reuse templates with --stubs=/path/to/overrides (same filenames as resources/stubs/resource).');
        $this->line('php artisan config:clear');
        $this->line('php artisan route:clear');
        $this->line('php artisan migrate');
        $this->line('php artisan permissions:sync');
        $this->line('php artisan wayfinder:generate --with-form');
        $this->line('vendor/bin/pint --dirty --format agent');
        $this->line('npx vp fmt resources/js/pages/'.$route.' resources/js/components/'.$route.' resources/js/types/'.$route.'.ts');
        $this->line('php artisan test --compact tests/Feature/'.$name.'ManagementTest.php');
        $this->line('npm run build');

        return self::SUCCESS;
    }
}
