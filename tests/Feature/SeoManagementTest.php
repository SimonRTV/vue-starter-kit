<?php

namespace Tests\Feature;

use App\Actions\ApplicationSettings\SeoSettings;
use App\Models\ApplicationSetting;
use App\Models\Page;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_administrators_can_view_and_update_seo(): void
    {
        $page = Page::factory()->create();
        foreach (['defaults', 'home', (string) $page->id] as $target) {
            $this->get(route('seo.edit', $target))->assertRedirect(route('login'));
            $this->put(route('seo.update', $target), [])->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create());
        foreach (['defaults', 'home', (string) $page->id] as $target) {
            $this->get(route('seo.edit', $target))->assertForbidden();
            $this->put(route('seo.update', $target), ['meta_title' => 'Forbidden'])->assertForbidden();
        }
        $this->assertNull($page->fresh()->seo);
        $this->assertDatabaseCount('application_settings', 0);
    }

    public function test_manager_loads_defaults_home_and_draft_page_settings(): void
    {
        $this->administrator();
        $page = Page::factory()->draft()->create();
        $this->get(route('seo.edit'))->assertInertia(fn (Assert $view) => $view
            ->component('seo/Edit')->where('target', 'defaults')->where('settings.robots_index', 'index')->has('pages', 1));
        $this->get(route('seo.edit', 'home'))->assertInertia(fn (Assert $view) => $view
            ->where('target', 'home')->where('settings.meta_title', '')->where('publicUrl', route('home')));
        $this->get(route('seo.edit', $page->id))->assertInertia(fn (Assert $view) => $view
            ->where('fallback.title', $page->title)->where('isPublished', false));
        $this->get(route('seo.edit', '999999'))->assertNotFound();
        $this->put(route('seo.update', '999999'), [])->assertNotFound();
    }

    public function test_global_settings_are_validated_persisted_and_applied_only_to_public_pages(): void
    {
        $this->administrator();
        $settings = [...app(SeoSettings::class)->defaults(), 'site_name' => 'Atelier', 'title_suffix' => 'Atelier',
            'meta_title' => 'Bienvenue', 'meta_description' => 'Notre atelier', 'og_image' => 'https://example.com/share.jpg',
            'og_image_alt' => 'Notre équipe', 'twitter_site' => '@atelier', 'twitter_card' => 'summary_large_image',
            'google_verification' => 'google-token', 'bing_verification' => 'bing-token', 'secret' => 'ignore'];
        $this->put(route('seo.update', 'defaults'), $settings)->assertSessionHasNoErrors()->assertRedirect(route('seo.edit', 'defaults'));
        $stored = ApplicationSetting::query()->where('key', 'seo.defaults')->sole();
        $this->assertStringNotContainsString('secret', $stored->value);
        $this->get(route('home'))->assertOk()
            ->assertSee('>Bienvenue - Atelier</title>', false)
            ->assertSee('property="og:site_name" content="Atelier"', false)
            ->assertSee('property="og:image" content="https://example.com/share.jpg"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('name="twitter:site" content="@atelier"', false)
            ->assertSee('name="google-site-verification" content="google-token"', false)
            ->assertSee('name="msvalidate.01" content="bing-token"', false);
        $this->get(route('dashboard'))->assertOk()->assertSee('name="robots" content="none"', false)
            ->assertDontSee('google-token')->assertDontSee('>Bienvenue - Atelier</title>', false);
    }

    public function test_global_required_fields_and_url_protocols_are_validated_without_writes(): void
    {
        $this->administrator();
        $this->put(route('seo.update', 'defaults'), [])->assertSessionHasErrors(['site_name', 'locale', 'meta_title', 'robots_index']);
        $this->put(route('seo.update', 'home'), [
            'canonical_url' => 'javascript:alert(1)', 'og_image' => 'file:///etc/passwd', 'twitter_image' => '//example.com/image.jpg',
            'robots_index' => 'invalid', 'robots_follow' => 'invalid', 'include_in_sitemap' => 'sometimes',
            'og_type' => 'invalid', 'twitter_card' => 'invalid', 'meta_title' => str_repeat('a', 256),
        ])->assertSessionHasErrors(['canonical_url', 'og_image', 'twitter_image', 'robots_index', 'robots_follow', 'include_in_sitemap', 'og_type', 'twitter_card', 'meta_title']);
        $this->assertDatabaseCount('application_settings', 0);
    }

    public function test_homepage_overrides_can_be_cleared_to_restore_defaults(): void
    {
        $this->administrator();
        $this->put(route('seo.update', 'home'), ['meta_title' => 'Custom home', 'robots_index' => 'noindex'])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertSee('>Custom home - '.config('app.name').'</title>', false)->assertSee('name="robots" content="noindex, follow"', false);
        $this->put(route('seo.update', 'home'), ['meta_title' => '', 'robots_index' => ''])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertSee('>Welcome - '.config('app.name').'</title>', false)->assertSee('name="robots" content="all"', false);
    }

    public function test_page_overrides_render_single_tags_without_changing_content_and_can_be_reset(): void
    {
        $this->administrator();
        $page = Page::factory()->published()->create(['title' => 'Original', 'excerpt' => 'Original summary']);
        $this->put(route('seo.update', $page->id), [
            'meta_title' => 'SEO title', 'meta_description' => 'SEO description', 'canonical_url' => 'https://example.com/preferred',
            'og_title' => 'Social title', 'og_description' => 'Social summary', 'og_image' => 'https://example.com/social.jpg',
            'og_image_alt' => 'Image alt', 'og_type' => 'article', 'twitter_title' => 'X title',
            'twitter_description' => 'X description', 'twitter_image' => 'https://example.com/x.jpg', 'robots_follow' => 'nofollow',
            'title' => 'Do not change content',
        ])->assertSessionHasNoErrors()->assertRedirect(route('seo.edit', $page->id));
        $this->assertSame('Original', $page->fresh()->title);
        $response = $this->get(route('content.show', $page->slug))->assertOk()
            ->assertSee('>SEO title - '.config('app.name').'</title>', false)
            ->assertSee('name="description" content="SEO description"', false)
            ->assertSee('rel="canonical" href="https://example.com/preferred"', false)
            ->assertSee('property="og:title" content="Social title"', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('property="og:image:alt" content="Image alt"', false)
            ->assertSee('name="twitter:title" content="X title"', false)
            ->assertSee('name="twitter:image" content="https://example.com/x.jpg"', false)
            ->assertSee('name="robots" content="index, nofollow"', false);
        $head = Str::before($response->getContent(), '</head>');
        foreach (['<title', 'name="description"', 'rel="canonical"', 'property="og:title"'] as $tag) {
            $this->assertSame(1, substr_count($head, $tag));
        }
        $this->put(route('seo.update', $page->id), [])->assertSessionHasNoErrors();
        $this->get(route('content.show', $page->slug))->assertSee('>Original - '.config('app.name').'</title>', false)
            ->assertSee('name="description" content="Original summary"', false);
    }

    public function test_empty_public_page_description_uses_global_fallback_and_suffix_can_be_removed(): void
    {
        $this->administrator();
        $this->put(route('seo.update', 'defaults'), [...app(SeoSettings::class)->defaults(), 'meta_description' => 'Fallback', 'title_suffix' => '']);
        $page = Page::factory()->published()->create(['excerpt' => null]);
        $this->get(route('content.show', $page->slug))->assertSee('>'.$page->title.'</title>', false)->assertSee('name="description" content="Fallback"', false);
    }

    public function test_structured_data_is_validated_and_rendered_without_executable_markup(): void
    {
        $this->administrator();
        foreach (['invalid', '"string"', '[]', '{"name":"Missing context"}', '{"@context":"https://other.test","@type":"WebPage"}', '{"@context":"https://schema.org","@graph":[]}', '{"@context":"https://schema.org","@type":"WebPage","name":null}', '{"@context":"https://schema.org","@type":"WebPage","author":{"name":""}}'] as $json) {
            $this->put(route('seo.update', 'home'), ['structured_data' => $json])->assertSessionHasErrors('structured_data');
        }
        $attack = '</script><script>alert(1)</script>';
        $this->put(route('seo.update', 'home'), ['meta_title' => $attack, 'structured_data' => json_encode([
            '@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $attack,
        ], JSON_THROW_ON_ERROR)])->assertSessionHasNoErrors();
        $response = $this->get(route('home'))->assertOk()->assertSee('type="application/ld+json"', false);
        $head = Str::before($response->getContent(), '</head>');
        $this->assertStringNotContainsString($attack, $head);
        $this->assertStringContainsString('\\u003C/script\\u003E', $head);
    }

    public function test_global_and_page_structured_data_are_both_rendered(): void
    {
        $this->administrator();
        $this->put(route('seo.update', 'defaults'), [...app(SeoSettings::class)->defaults(), 'structured_data' => '{"@context":"https://schema.org","@type":"Organization","name":"Atelier"}']);
        $this->put(route('seo.update', 'home'), ['structured_data' => '{"@context":"https://schema.org","@type":"WebSite","name":"Homepage"}']);
        $head = Str::before($this->get(route('home'))->getContent(), '</head>');
        $this->assertSame(2, substr_count($head, 'type="application/ld+json"'));
        $this->assertStringContainsString('Organization', $head);
        $this->assertStringContainsString('WebSite', $head);
    }

    public function test_sitemap_only_includes_published_indexable_canonical_pages(): void
    {
        $published = Page::factory()->published()->create();
        $draft = Page::factory()->draft()->create();
        $future = Page::factory()->published()->create(['published_at' => now()->addDay()]);
        $hidden = Page::factory()->published()->create(['seo' => ['robots_index' => 'noindex']]);
        $excluded = Page::factory()->published()->create(['seo' => ['include_in_sitemap' => 'no']]);
        $canonical = Page::factory()->published()->create(['seo' => ['canonical_url' => 'https://example.com/other']]);
        $response = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(2, $xml->url);
        $response->assertSee(route('home'))->assertSee(route('content.show', $published->slug));
        foreach ([$draft, $future, $hidden, $excluded, $canonical] as $page) {
            $response->assertDontSee(route('content.show', $page->slug));
        }
        $this->get(route('robots'))->assertOk()->assertSee('Sitemap: '.route('sitemap'));
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_sitemap_respects_global_indexing_and_homepage_overrides(): void
    {
        $this->administrator();
        Page::factory()->published()->create();
        $this->put(route('seo.update', 'defaults'), [...app(SeoSettings::class)->defaults(), 'robots_index' => 'noindex']);
        $this->get(route('sitemap'))->assertOk()->assertDontSee('<url>', false);
        $this->put(route('seo.update', 'home'), ['robots_index' => 'index']);
        $this->get(route('sitemap'))->assertSee('<loc>'.route('home').'</loc>', false);
    }

    public function test_public_and_pages_feature_flags_are_respected(): void
    {
        $this->administrator();
        $page = Page::factory()->published()->create();
        config(['starter.features.pages' => false]);
        $this->get(route('seo.edit'))->assertInertia(fn (Assert $view) => $view->has('pages', 0));
        $this->get(route('seo.edit', $page->id))->assertNotFound();
        $this->put(route('seo.update', $page->id), [])->assertNotFound();
        $this->get(route('sitemap'))->assertDontSee(route('content.show', $page->slug));
        config(['starter.features.public_site' => false]);
        $this->get(route('seo.edit'))->assertNotFound();
        $this->put(route('seo.update', 'home'), [])->assertNotFound();
        $this->get(route('sitemap'))->assertNotFound();
        $this->get(route('robots'))->assertSee('Disallow: /')->assertDontSee('Sitemap:');
    }

    private function administrator(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(RolePolicy::ADMINISTRATOR_ROLE, 'web'));
        $this->actingAs($user);
    }
}
