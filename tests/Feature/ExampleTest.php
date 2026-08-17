<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_public_branded_experience(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('name', config('app.name'))
                ->where('auth.user', null)
                ->where('branding.iconUrl', null)
                ->where('branding.fullLogoUrl', null),
            );
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
