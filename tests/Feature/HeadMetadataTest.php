<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Support\Header;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadManager;
use Tests\TestCase;

class HeadMetadataTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_homepage_renders_server_managed_metadata_and_application_icons(): void
    {
        $appName = (string) config('app.name');

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee("<title data-inertia=\"title\">Welcome - {$appName}</title>", false)
            ->assertSee('name="description" content="A flexible, thoughtful workspace for bringing people, priorities, and progress together."', false)
            ->assertSee('name="robots" content="all"', false)
            ->assertSee('property="og:title" content="Welcome - '.$appName.'"', false)
            ->assertSee('name="twitter:card" content="summary"', false)
            ->assertSee('href="'.asset('favicon.svg').'"', false)
            ->assertSee('href="'.asset('favicon-96x96.png').'"', false)
            ->assertSee('href="'.asset('favicon.ico').'"', false)
            ->assertSee('href="'.asset('apple-touch-icon.png').'"', false)
            ->assertSee('href="'.asset('site.webmanifest').'"', false);

        $documentHead = Str::before($response->getContent(), '</head>');

        $this->assertSame(1, substr_count($documentHead, 'name="viewport"'));
        $this->assertSame(1, substr_count($documentHead, '<title'));
    }

    public function test_inertia_middleware_explicitly_shares_server_managed_head_elements(): void
    {
        $shared = app(HandleInertiaRequests::class)->share(Request::create('/'));

        $this->assertArrayHasKey(HeadManager::INERTIA_PROP, $shared);
        $this->assertIsCallable($shared[HeadManager::INERTIA_PROP]);
        $this->assertNotEmpty($shared[HeadManager::INERTIA_PROP]());
    }

    public function test_public_page_metadata_is_shared_with_inertia(): void
    {
        $publicPage = Page::factory()->published()->create([
            'title' => 'Company story',
            'slug' => 'company-story',
            'excerpt' => 'A short introduction to our company.',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(
            route('content.show', ['page' => $publicPage->slug]),
            $this->inertiaHeaders(),
        )->assertOk();

        $head = implode("\n", $response->json('props.'.HeadManager::INERTIA_PROP));

        $this->assertStringContainsString('>Company story - '.config('app.name').'</title>', $head);
        $this->assertStringContainsString('name="description" content="A short introduction to our company."', $head);
        $this->assertStringContainsString('name="robots" content="all"', $head);
        $this->assertStringContainsString('rel="canonical" href="'.route('content.show', ['page' => $publicPage->slug]).'"', $head);
        $this->assertStringContainsString('property="og:title" content="Company story - '.config('app.name').'"', $head);
    }

    public function test_dashboard_uses_server_managed_title_and_is_hidden_from_robots(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'), $this->inertiaHeaders())
            ->assertOk();

        $head = implode("\n", $response->json('props.'.HeadManager::INERTIA_PROP));

        $this->assertStringContainsString('>Tableau de bord - '.config('app.name').'</title>', $head);
        $this->assertStringContainsString('name="robots" content="none"', $head);
    }

    public function test_authentication_views_use_server_managed_metadata(): void
    {
        $response = $this->get(route('login'), $this->inertiaHeaders())
            ->assertOk();

        $head = implode("\n", $response->json('props.'.HeadManager::INERTIA_PROP));

        $this->assertStringContainsString('>Connexion - '.config('app.name').'</title>', $head);
        $this->assertStringContainsString('name="robots" content="none"', $head);
    }

    public function test_error_metadata_is_localized_and_hidden_from_robots(): void
    {
        $head = implode("\n", Head::toInertiaElements(404));

        $this->assertStringContainsString('>Page introuvable - '.config('app.name').'</title>', $head);
        $this->assertStringContainsString('name="robots" content="none"', $head);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

        return [
            Header::INERTIA => 'true',
            Header::VERSION => $version ?? '',
        ];
    }
}
