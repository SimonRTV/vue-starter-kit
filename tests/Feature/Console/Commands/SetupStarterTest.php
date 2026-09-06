<?php

namespace Tests\Feature\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SetupStarterTest extends TestCase
{
    public function test_command_help_explains_usage_examples_and_next_steps(): void
    {
        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, Artisan::call('help', ['command_name' => 'starter:setup']));
        $output = Artisan::output();
        $this->assertStringContainsString('Use after installing a new project', $output);
        $this->assertStringContainsString('--preset resets ALL', $output);
        $this->assertStringContainsString('--enable=pages --enable=public_site', $output);
        $this->assertStringContainsString('README.md', $output);
    }

    public function test_module_overrides_preserve_existing_choices_and_reject_invalid_combinations(): void
    {
        $directory = sys_get_temp_dir().'/starter-setup-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $original = app()->environmentPath();
        app()->useEnvironmentPath($directory);
        try {
            $initial = "APP_KEY=secret\nSTARTER_PRESET=business\nSTARTER_MEDIA=false\n";
            file_put_contents($directory.'/.env', $initial);
            $this->artisan('starter:setup', ['--list' => true])->assertSuccessful();
            $this->artisan('starter:setup')->assertSuccessful();
            $this->assertSame($initial, file_get_contents($directory.'/.env'));
            $this->artisan('starter:setup', ['--enable' => ['pages'], '--disable' => ['activity']])->assertSuccessful();
            $values = Dotenv::parse(file_get_contents($directory.'/.env'));
            $this->assertSame('business', $values['STARTER_PRESET']);
            $this->assertSame('false', $values['STARTER_MEDIA']);
            $this->assertSame('true', $values['STARTER_PAGES']);
            $this->assertSame('false', $values['STARTER_ACTIVITY']);
            $before = file_get_contents($directory.'/.env');
            foreach ([['--enable' => ['missing']], ['--enable' => ['media'], '--disable' => ['media']], ['--preset' => 'website', '--disable' => ['pages']]] as $options) {
                $this->artisan('starter:setup', $options)->assertFailed();
                $this->assertSame($before, file_get_contents($directory.'/.env'));
            }
            $this->artisan('starter:setup', ['--preset' => 'business', '--enable' => ['public_site', 'pages'], '--disable' => ['notifications']])->assertSuccessful();
            $values = Dotenv::parse(file_get_contents($directory.'/.env'));
            $this->assertSame('true', $values['STARTER_PUBLIC_SITE']);
            $this->assertSame('true', $values['STARTER_PAGES']);
            $this->assertSame('false', $values['STARTER_NOTIFICATIONS']);
            file_put_contents($directory.'/.env', "export STARTER_MEDIA = false\nAPP_NAME=Old\n");
            $this->artisan('starter:setup', ['--enable' => ['media'], '--name' => 'true'])->assertSuccessful();
            $contents = file_get_contents($directory.'/.env');
            $this->assertSame('true', Dotenv::parse($contents)['STARTER_MEDIA']);
            $this->assertStringContainsString('APP_NAME="true"', $contents);
            $this->assertSame(1, substr_count($contents, 'STARTER_MEDIA'));
            file_put_contents($directory.'/.env', 'INVALID KEY=value');
            $this->artisan('starter:setup', ['--name' => 'Example'])->assertFailed();
            $this->assertSame('INVALID KEY=value', file_get_contents($directory.'/.env'));
        } finally {
            app()->useEnvironmentPath($original);
            (new Filesystem)->deleteDirectory($directory);
        }
    }

    public function test_setup_preserves_secrets_and_applies_preset_with_optional_identity(): void
    {
        $directory = sys_get_temp_dir().'/starter-setup-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $original = app()->environmentPath();
        app()->useEnvironmentPath($directory);
        try {
            file_put_contents($directory.'/.env', "APP_KEY=existing-secret\nAPP_NAME=Old\nSTARTER_PAGES=true\nSTARTER_PUBLIC_SITE=true\n");
            $this->artisan('starter:setup', ['--preset' => 'business', '--name' => 'My "Studio"', '--timezone' => 'Europe/Zurich', '--locale' => 'fr'])->assertSuccessful();
            $values = Dotenv::parse(file_get_contents($directory.'/.env'));
            $this->assertSame('existing-secret', $values['APP_KEY']);
            $this->assertSame('My "Studio"', $values['APP_NAME']);
            $this->assertSame('false', $values['STARTER_PAGES']);
            $this->assertSame('false', $values['STARTER_PUBLIC_SITE']);
            $this->assertSame('Europe/Zurich', $values['APP_TIMEZONE']);
            $before = file_get_contents($directory.'/.env');
            $this->artisan('starter:setup', ['--preset' => 'website', '--dry-run' => true])->assertSuccessful();
            $this->assertSame($before, file_get_contents($directory.'/.env'));
            foreach ([['--preset' => 'invalid'], ['--name' => "bad\nAPP_DEBUG=true"], ['--timezone' => 'invalid']] as $options) {
                $this->artisan('starter:setup', $options)->assertFailed();
                $this->assertSame($before, file_get_contents($directory.'/.env'));
            }
        } finally {
            app()->useEnvironmentPath($original);
            (new Filesystem)->deleteDirectory($directory);
        }
    }
}
