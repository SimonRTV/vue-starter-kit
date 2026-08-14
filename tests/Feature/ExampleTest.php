<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_layout_includes_the_complete_favicon_set(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('href="'.asset('favicon.svg').'"', false)
            ->assertSee('href="'.asset('favicon-96x96.png').'"', false)
            ->assertSee('href="'.asset('favicon.ico').'"', false)
            ->assertSee('href="'.asset('apple-touch-icon.png').'"', false)
            ->assertSee('href="'.asset('site.webmanifest').'"', false);

        foreach ([
            'favicon.svg',
            'favicon-96x96.png',
            'favicon.ico',
            'apple-touch-icon.png',
            'web-app-manifest-192x192.png',
            'web-app-manifest-512x512.png',
            'site.webmanifest',
        ] as $favicon) {
            $this->assertFileExists(public_path($favicon));
        }
    }
}
