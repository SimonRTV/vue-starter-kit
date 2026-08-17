<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_view_a_published_page_by_slug(): void
    {
        $publicPage = Page::factory()->published()->create([
            'title' => 'Company story',
            'slug' => 'company-story',
            'excerpt' => 'A short introduction to our company.',
            'body' => "We started with a simple idea.\n\nToday, we help teams do their best work.",
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('content.show', ['page' => $publicPage->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('content/Show')
                ->where('page.title', 'Company story')
                ->where('page.slug', 'company-story')
                ->where('page.excerpt', 'A short introduction to our company.')
                ->where('page.body', "We started with a simple idea.\n\nToday, we help teams do their best work.")
                ->where('page.published_at', $publicPage->published_at?->toISOString())
                ->where('auth.user', null)
                ->missing('page.id')
                ->missing('page.is_published'),
            );
    }

    public function test_draft_pages_are_not_publicly_accessible(): void
    {
        $draftPage = Page::factory()->draft()->create([
            'slug' => 'private-draft',
        ]);

        $this->get(route('content.show', ['page' => $draftPage->slug]))
            ->assertNotFound();
    }

    public function test_pages_with_a_future_publication_date_are_not_publicly_accessible(): void
    {
        $scheduledPage = Page::factory()->create([
            'slug' => 'scheduled-page',
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('content.show', ['page' => $scheduledPage->slug]))
            ->assertNotFound();
    }
}
