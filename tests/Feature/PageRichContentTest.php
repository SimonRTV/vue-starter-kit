<?php

namespace Tests\Feature;

use App\Actions\Pages\PageContent;
use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Page;
use App\Models\User;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageRichContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rich_content_preserves_supported_formatting_and_removes_unsafe_markup(): void
    {
        $html = '<h2>Heading</h2><p><strong>Bold</strong> <em>Italic</em> <u>Underline</u> <s>Strike</s></p><ol start="3"><li>First</li></ol><blockquote>Quote</blockquote><pre><code>&lt;div&gt;</code></pre><hr><p><a href="https://example.com" onclick="alert(1)">Link</a></p><script>alert(1)</script><img src=x onerror=alert(1)><iframe src="https://example.com"></iframe><svg onload="alert(1)"></svg><p style="position:fixed" class="overlay" id="app">Text</p><a href="javascript:alert(1)">Bad</a>';
        $clean = app(PageContent::class)->sanitize($html);
        foreach (['<h2>Heading</h2>', '<strong>Bold</strong>', '<em>Italic</em>', '<u>Underline</u>', '<s>Strike</s>', '<ol start="3">', '<blockquote>Quote</blockquote>', '<pre><code>&lt;div&gt;</code></pre>', 'href="https://example.com"'] as $expected) {
            $this->assertStringContainsString($expected, $clean);
        }
        foreach (['script', 'onclick', 'onerror', '<img', '<iframe', '<svg', 'javascript:', 'style=', 'class=', 'id='] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $clean);
        }
        $this->assertSame($clean, app(PageContent::class)->sanitize($clean));
    }

    public function test_legacy_text_is_escaped_and_keeps_paragraphs_and_line_breaks(): void
    {
        $body = "Literal <strong>text</strong> & symbols\nNext line\n\nLast paragraph";
        $rendered = app(PageContent::class)->render($body, 'text');
        $this->assertStringContainsString('&lt;strong&gt;text&lt;/strong&gt; &amp; symbols', $rendered);
        $this->assertStringContainsString('<br>', $rendered);
        $this->assertStringContainsString('</p><p>Last paragraph</p>', $rendered);
        $this->assertSame('', app(PageContent::class)->render(null));
        $this->assertNull(app(PageContent::class)->sanitize('<p><br></p>'));
    }

    public function test_create_and_update_sanitize_html_and_render_it_in_admin_and_public_views(): void
    {
        $this->withoutVite();
        app(SyncPolicyPermissions::class)->handle();
        $user = User::factory()->create();
        $user->givePermissionTo(PagePolicy::PERMISSIONS);
        $attributes = ['title' => 'Rich page', 'slug' => 'rich-page', 'excerpt' => null, 'body' => '<h2>Welcome</h2><p><strong>Hello</strong><script>alert(1)</script></p>', 'body_format' => 'html', 'is_published' => true];
        $this->actingAs($user)->post(route('pages.store'), $attributes)->assertRedirect();
        $page = Page::query()->sole();
        $this->assertSame('html', $page->body_format);
        $this->assertSame('<h2>Welcome</h2><p><strong>Hello</strong></p>', $page->body);
        $this->get(route('pages.edit', $page))->assertInertia(fn (Assert $response) => $response->where('page.body_html', $page->body));
        $this->get(route('pages.show', $page))->assertInertia(fn (Assert $response) => $response->where('page.body_html', $page->body));
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $response) => $response->where('page.body_html', $page->body));
        $attributes['body'] = '<p><a href="javascript:alert(1)">Unsafe link</a><em>Updated</em></p>';
        $this->put(route('pages.update', $page), $attributes)->assertRedirect();
        $this->assertStringNotContainsString('javascript:', $page->refresh()->body);
        $this->assertStringContainsString('<em>Updated</em>', $page->body);
        $attributes['body_format'] = 'invalid';
        $this->put(route('pages.update', $page), $attributes)->assertSessionHasErrors('body_format');
    }

    public function test_public_render_sanitizes_html_even_when_storage_was_written_directly(): void
    {
        $this->withoutVite();
        $page = Page::factory()->published()->create(['body_format' => 'html', 'body' => '<p>Safe</p><script>alert(1)</script>']);
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $response) => $response->where('page.body_html', '<p>Safe</p>'));
    }

    public function test_legacy_pages_are_not_reinterpreted_as_html(): void
    {
        $this->withoutVite();
        $page = Page::factory()->published()->create(['body' => '<p>Literal HTML example</p>']);
        $this->assertSame('text', $page->body_format);
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $response) => $response->where('page.body_html', '<p>&lt;p&gt;Literal HTML example&lt;/p&gt;</p>'));
    }
}
