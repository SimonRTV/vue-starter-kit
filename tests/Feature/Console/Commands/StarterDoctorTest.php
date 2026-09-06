<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class StarterDoctorTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $originalStorage;

    private string $originalPublic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMockingConsoleOutput();
        $this->directory = sys_get_temp_dir().'/starter-doctor-'.bin2hex(random_bytes(8));
        $this->originalStorage = storage_path();
        $this->originalPublic = public_path();
        $files = new Filesystem;
        foreach (['framework/views', 'framework/sessions', 'logs', 'app/public', 'app/media', 'app/private', 'web/build'] as $path) {
            $files->ensureDirectoryExists($this->directory.'/'.$path);
        }
        app()->useStoragePath($this->directory);
        app()->usePublicPath($this->directory.'/web');
        symlink($this->directory.'/app/public', public_path('storage'));
        file_put_contents(public_path('build/manifest.json'), '{}');
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://example.test',
            'filesystems.default' => 'local',
            'filesystems.disks.local.root' => $this->directory.'/app/private',
            'filesystems.disks.public.root' => $this->directory.'/app/public',
            'filesystems.disks.media.root' => $this->directory.'/app/media',
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);
    }

    protected function tearDown(): void
    {
        app()->useStoragePath($this->originalStorage);
        app()->usePublicPath($this->originalPublic);
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_healthy_setup_reports_unverified_services_and_strict_mode_fails_on_warnings(): void
    {
        $this->assertSame(0, Artisan::call('starter:doctor', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $checks = array_column($report['checks'], null, 'name');
        $this->assertSame(0, $report['failures']);
        $this->assertSame('PASS', $checks['database.migrations']['status']);
        $this->assertSame('PASS', $checks['storage.public_link']['status']);
        $this->assertSame('WARN', $checks['mail.transport']['status']);
        $this->assertSame('WARN', $checks['queue.worker']['status']);
        $this->assertStringContainsString('unverified', $checks['scheduler.execution']['message']);
        $this->assertSame(1, Artisan::call('starter:doctor', ['--strict' => true]));
        $this->assertStringContainsString('No fixes applied', Artisan::output());
    }

    public function test_missing_migration_and_storage_are_reported_without_repairs(): void
    {
        DB::table('migrations')->where('id', DB::table('migrations')->max('id'))->delete();
        $missing = $this->directory.'/missing';
        config(['filesystems.disks.media.root' => $missing]);
        $this->assertSame(1, Artisan::call('starter:doctor', ['--json' => true]));
        $checks = array_column(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'], null, 'name');
        $this->assertSame('FAIL', $checks['database.migrations']['status']);
        $this->assertStringContainsString('1 pending', $checks['database.migrations']['message']);
        $this->assertSame('FAIL', $checks['disk.media']['status']);
        $this->assertDirectoryDoesNotExist($missing);
    }

    public function test_connection_errors_do_not_expose_secrets_or_prevent_remaining_checks(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('password=secret-credential'));
        $this->assertSame(1, Artisan::call('starter:doctor', ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringNotContainsString('secret-credential', $output);
        $checks = array_column(json_decode($output, true, flags: JSON_THROW_ON_ERROR)['checks'], null, 'name');
        $this->assertSame('FAIL', $checks['database.access']['status']);
        $this->assertArrayHasKey('scheduler.execution', $checks);
    }

    public function test_invalid_configuration_and_queue_table_have_actionable_failures(): void
    {
        config([
            'app.key' => 'invalid',
            'app.url' => 'invalid',
            'starter.features.pages' => false,
            'starter.features.public_site' => true,
            'queue.default' => 'database',
            'queue.connections.database.connection' => config('database.default'),
            'queue.connections.database.table' => 'missing_jobs',
            'mail.default' => 'missing',
        ]);
        $this->assertSame(1, Artisan::call('starter:doctor', ['--json' => true]));
        $checks = array_column(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'], null, 'name');
        foreach (['application.key', 'application.url', 'modules.dependencies', 'queue.table', 'mail.transport'] as $name) {
            $this->assertSame('FAIL', $checks[$name]['status'], $name);
        }
    }

    public function test_production_configuration_and_disabled_media_are_checked(): void
    {
        app()->instance('env', 'production');
        config([
            'app.debug' => true,
            'app.url' => 'http://example.test',
            'starter.features.media' => false,
            'filesystems.disks.media.root' => $this->directory.'/missing',
            'starter.features.activity' => true,
            'activity.retention_days' => 30,
        ]);
        file_put_contents(public_path('hot'), 'http://localhost:5173');
        $this->assertSame(1, Artisan::call('starter:doctor', ['--json' => true]));
        $checks = array_column(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'], null, 'name');
        $this->assertSame('FAIL', $checks['application.debug']['status']);
        $this->assertSame('WARN', $checks['application.url']['status']);
        $this->assertSame('WARN', $checks['frontend.assets']['status']);
        $this->assertArrayNotHasKey('disk.media', $checks);
        $this->assertStringContainsString('Activity retention needs', $checks['scheduler.execution']['message']);
    }

    public function test_missing_key_and_discarded_jobs_fail_without_crashing(): void
    {
        config(['app.key' => null, 'queue.default' => 'discard', 'queue.connections.discard' => ['driver' => 'null']]);
        $this->assertSame(1, Artisan::call('starter:doctor', ['--json' => true]));
        $checks = array_column(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'], null, 'name');
        $this->assertSame('FAIL', $checks['application.key']['status']);
        $this->assertSame('FAIL', $checks['queue.configuration']['status']);
    }

    public function test_status_colors_respect_output_decoration_and_json_stays_plain(): void
    {
        config(['app.key' => 'invalid']);
        $colored = new BufferedOutput(decorated: true);
        Artisan::call('starter:doctor', [], $colored);
        $output = $colored->fetch();
        foreach (['32' => 'PASS', '33' => 'WARN', '31' => 'FAIL'] as $color => $status) {
            $this->assertStringContainsString("\033[".$color.';1m'.$status, $output);
        }
        $plain = new BufferedOutput(decorated: false);
        Artisan::call('starter:doctor', [], $plain);
        $output = $plain->fetch();
        $this->assertStringNotContainsString("\033[", $output);
        $this->assertStringNotContainsString('<fg=', $output);
        $json = new BufferedOutput(decorated: true);
        Artisan::call('starter:doctor', ['--json' => true], $json);
        $output = $json->fetch();
        $this->assertStringNotContainsString("\033[", $output);
        $this->assertIsArray(json_decode($output, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_help_explains_scope_and_exit_codes(): void
    {
        $this->assertSame(0, Artisan::call('help', ['command_name' => 'starter:doctor']));
        $output = Artisan::output();
        $this->assertStringContainsString('--strict', $output);
        $this->assertStringContainsString('Exit code:', $output);
        $this->assertStringContainsString('No mail or test jobs are sent', $output);
    }
}
