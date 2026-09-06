<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MakeResourceTest extends TestCase
{
    public function test_command_help_explains_usage_examples_and_next_steps(): void
    {
        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, Artisan::call('help', ['command_name' => 'starter:resource']));
        $output = Artisan::output();
        $this->assertStringContainsString('Use when adding a new administrative resource', $output);
        $this->assertStringContainsString('--label="Client Notes" --dry-run', $output);
        $this->assertStringContainsString('config/resources.php', $output);
        $this->assertStringContainsString('php artisan permissions:sync', $output);
        $this->assertStringContainsString('README.md', $output);
    }

    public function test_generator_previews_rejects_invalid_names_and_never_overwrites(): void
    {
        $original = base_path();
        $directory = sys_get_temp_dir().'/starter-resource-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->ensureDirectoryExists($directory.'/config');
        $files->put($directory.'/config/resources.php', '<?php return [];');
        app()->setBasePath($directory);
        try {
            $this->artisan('starter:resource', ['name' => 'ProjectNote', '--dry-run' => true])->assertSuccessful();
            $this->assertDirectoryDoesNotExist($directory.'/app');
            foreach (['../Unsafe', 'class', 'Class', 'Projects', 'Page'] as $name) {
                $this->artisan('starter:resource', ['name' => $name])->assertFailed();
            }
            foreach ([['--label' => "Unsafe'Label"], ['--stubs' => $directory.'/missing']] as $options) {
                $this->artisan('starter:resource', ['name' => 'ProjectNote', ...$options])->assertFailed();
            }
            $files->ensureDirectoryExists($directory.'/overrides');
            $files->put($directory.'/overrides/types.ts.stub', 'export type {{model}}Marker = "{{label}}";');
            $this->artisan('starter:resource', ['name' => 'CustomNote', '--label' => 'Client Notes', '--disabled' => true, '--stubs' => $directory.'/overrides'])->assertSuccessful();
            $registry = $files->getRequire($directory.'/config/resources.php');
            $this->assertFalse($registry['custom-notes']['enabled']);
            $this->assertSame('Client Notes', $registry['custom-notes']['label']);
            $this->assertSame('export type CustomNoteMarker = "Client Notes";', $files->get($directory.'/resources/js/types/custom-notes.ts'));
            $this->assertFileExists($directory.'/app/Models/CustomNote.php');
            $files->ensureDirectoryExists($directory.'/app/Models');
            $files->put($directory.'/app/Models/ProjectNote.php', 'keep');
            $this->artisan('starter:resource', ['name' => 'ProjectNote'])->assertFailed();
            $this->assertSame('keep', $files->get($directory.'/app/Models/ProjectNote.php'));
            $this->assertFileDoesNotExist($directory.'/database/factories/ProjectNoteFactory.php');
        } finally {
            app()->setBasePath($original);
            if (is_link($directory.'/node_modules')) {
                unlink($directory.'/node_modules');
            }
            if (is_link($directory.'/vendor')) {
                unlink($directory.'/vendor');
            }
            $files->deleteDirectory($directory);
        }
    }

    public function test_generated_multiword_resource_passes_its_crud_and_authorization_tests(): void
    {
        $original = base_path();
        $directory = sys_get_temp_dir().'/starter-integration-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->ensureDirectoryExists($directory);
        $directory = realpath($directory);
        try {
            foreach (['app', 'config', 'bootstrap', 'database/factories', 'database/migrations', 'resources/views', 'resources/js', 'lang', 'routes'] as $path) {
                $files->copyDirectory($original.'/'.$path, $directory.'/'.$path);
            }
            $files->cleanDirectory($directory.'/bootstrap/cache');
            symlink($original.'/vendor', $directory.'/vendor');
            symlink($original.'/node_modules', $directory.'/node_modules');
            $files->copy($original.'/tsconfig.json', $directory.'/tsconfig.json');
            foreach (['tests', 'storage/framework/views', 'storage/framework/cache', 'storage/logs'] as $path) {
                $files->ensureDirectoryExists($directory.'/'.$path);
            }
            $files->copy($original.'/tests/TestCase.php', $directory.'/tests/TestCase.php');
            $files->copy($original.'/composer.json', $directory.'/composer.json');
            $files->put($directory.'/config/resources.php', '<?php return [];');
            app()->setBasePath($directory);
            $this->artisan('starter:resource', ['name' => 'ProjectNote', '--label' => 'Client Notes'])->assertSuccessful();
            $registry = $files->getRequire($directory.'/config/resources.php');
            $this->assertSame('App\\Models\\ProjectNote', $registry['project-notes']['model']);
            $this->artisan('starter:resource', ['name' => 'ProjectNote'])->assertFailed();
            app()->setBasePath($original);

            $autoload = '<?php $loader = require '.var_export($original.'/vendor/autoload.php', true).';';
            foreach (['App\\' => '/app', 'Database\\Factories\\' => '/database/factories', 'Database\\Seeders\\' => '/database/seeders', 'Tests\\' => '/tests'] as $namespace => $path) {
                $autoload .= '$loader->addPsr4('.var_export($namespace, true).', '.var_export($directory.$path, true).', true);';
            }
            $files->put($directory.'/autoload.php', $autoload);
            $process = new Process([
                PHP_BINARY, $original.'/vendor/phpunit/phpunit/phpunit',
                '--configuration', $original.'/phpunit.xml', '--bootstrap', $directory.'/autoload.php',
                $directory.'/tests/Feature/ProjectNoteManagementTest.php',
            ], $directory, [
                'APP_BASE_PATH' => $directory,
                'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
                'APP_ENV' => 'testing',
            ]);
            $process->setTimeout(60);
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
            $files->put($directory.'/artisan', '<?php require __DIR__."/autoload.php"; exit((require __DIR__."/bootstrap/app.php")->handleCommand(new Symfony\\Component\\Console\\Input\\ArgvInput));');
            $wayfinder = new Process([PHP_BINARY, $directory.'/artisan', 'wayfinder:generate', '--with-form'], $directory, ['APP_BASE_PATH' => $directory]);
            $wayfinder->mustRun();
            $typescript = new Process([$original.'/node_modules/.bin/vue-tsc', '--noEmit'], $directory);
            $typescript->setTimeout(60);
            $typescript->run();
            $this->assertSame(0, $typescript->getExitCode(), $typescript->getOutput().$typescript->getErrorOutput());
            $files->put($directory.'/phpstan.neon', $files->get($original.'/phpstan.neon')."\n    bootstrapFiles:\n        - ".$directory."/autoload.php\n");
            $analysis = new Process([PHP_BINARY, $original.'/vendor/bin/phpstan', 'analyse', '--configuration='.$directory.'/phpstan.neon', '--memory-limit=512M', '--no-progress'], $directory, ['APP_BASE_PATH' => $directory]);
            $analysis->setTimeout(60);
            $analysis->run();
            $this->assertSame(0, $analysis->getExitCode(), $analysis->getOutput().$analysis->getErrorOutput());
        } finally {
            app()->setBasePath($original);
            if (is_link($directory.'/node_modules')) {
                unlink($directory.'/node_modules');
            }
            if (is_link($directory.'/vendor')) {
                unlink($directory.'/vendor');
            }
            $files->deleteDirectory($directory);
        }
    }
}
