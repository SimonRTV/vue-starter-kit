<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageFieldSet;
use App\Models\PageTemplate;
use App\Models\User;
use App\Policies\PagePolicy;
use Database\Seeders\PageTemplateSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PageTemplateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_template_management_requires_its_own_permission_and_feature(): void
    {
        $this->get(route('page-templates.index'))->assertRedirect(route('login'));
        $user = $this->editor(false);
        $template = PageTemplate::factory()->create();
        $set = PageFieldSet::factory()->create();
        $this->actingAs($user)->get(route('page-templates.index'))->assertForbidden();
        foreach (['page-templates' => $template, 'page-field-sets' => $set] as $resource => $model) {
            $this->post(route($resource.'.store'), [])->assertForbidden();
            $this->patch(route($resource.'.update', $model), [])->assertForbidden();
            $this->delete(route($resource.'.destroy', $model))->assertForbidden();
        }
        $this->get(route('pages.index'))->assertInertia(fn (Assert $view) => $view->where('canManageTemplates', false));
        $this->actingAs($this->editor())->get(route('page-templates.index'))->assertOk()->assertInertia(fn (Assert $view) => $view->component('pages/Templates'));
        config(['starter.features.pages' => false]);
        $this->get(route('page-templates.index'))->assertNotFound();
        $this->post(route('page-field-sets.store'), [])->assertNotFound();
    }

    public function test_managers_can_create_reuse_reorder_edit_and_delete_templates_and_field_sets(): void
    {
        $this->actingAs($this->editor());
        $this->post(route('page-field-sets.store'), ['name' => 'Hero', 'key' => 'hero', 'fields' => [$this->field()]])->assertSessionHasNoErrors();
        $first = PageFieldSet::query()->sole();
        $second = PageFieldSet::factory()->create();
        $this->post(route('page-templates.store'), ['name' => 'Landing', 'renderer' => 'feature', 'field_set_ids' => [$second->id, $first->id]])->assertSessionHasNoErrors();
        $template = PageTemplate::query()->sole();
        $this->assertSame([$second->id, $first->id], $template->fieldSets->modelKeys());
        $this->patch(route('page-templates.update', $template), ['name' => 'Updated', 'renderer' => 'collection', 'field_set_ids' => [$first->id]])->assertSessionHasNoErrors();
        $this->assertSame('Updated', $template->refresh()->name);
        $this->assertSame([$first->id], $template->fieldSets->modelKeys());
        $this->patch(route('page-field-sets.update', $first), ['name' => 'Updated hero', 'key' => 'hero', 'fields' => [$this->field()]])->assertSessionHasNoErrors();
        $this->assertSame('Updated hero', $first->refresh()->name);
        $this->post(route('page-templates.store'), ['name' => 'Another', 'renderer' => 'feature', 'field_set_ids' => [$first->id]])->assertSessionHasNoErrors();
        $this->assertSame(2, $first->templates()->count());
        $this->delete(route('page-field-sets.destroy', $first))->assertSessionHasErrors('field_set');
        $this->delete(route('page-field-sets.destroy', $second))->assertSessionHasNoErrors();
        $this->assertModelMissing($second);
        $this->delete(route('page-templates.destroy', $template))->assertSessionHasNoErrors();
        $this->assertModelMissing($template);
    }

    public function test_definitions_reject_unsafe_keys_unknown_renderers_and_invalid_options(): void
    {
        $this->actingAs($this->editor());
        $this->postJson(route('page-templates.store'), ['name' => 'Unsafe', 'renderer' => '../../Dashboard', 'field_set_ids' => [999]])->assertUnprocessable()->assertJsonValidationErrors(['renderer', 'field_set_ids.0']);
        $this->postJson(route('page-field-sets.store'), ['name' => 'Unsafe', 'key' => 'constructor', 'fields' => [$this->field('heading.value', 'unknown')]])->assertUnprocessable()->assertJsonValidationErrors(['key', 'fields.0.key', 'fields.0.type']);
        $this->postJson(route('page-field-sets.store'), ['name' => 'Options', 'key' => 'options', 'fields' => [$this->field('choice', 'select')]])->assertUnprocessable()->assertJsonValidationErrors('fields.0.options');
        $this->postJson(route('page-field-sets.store'), ['name' => 'Duplicates', 'key' => 'duplicates', 'fields' => [$this->field(), $this->field()]])->assertUnprocessable()->assertJsonValidationErrors('fields.0.key');
    }

    public function test_editors_can_select_templates_and_persist_typed_fields(): void
    {
        $template = $this->template();
        $this->actingAs($this->editor(false));
        $this->get(route('pages.create'))->assertInertia(fn (Assert $view) => $view->has('templates', 1)->where('templates.0.id', $template->id)->has('templateSources', 1));
        $this->post(route('pages.store'), $this->pageData($template))->assertSessionHasNoErrors();
        $page = Page::query()->sole();
        $this->assertSame($template->id, $page->page_template_id);
        $this->assertSame('Welcome', $page->template_fields['hero']['heading']);
        $this->assertFalse($page->template_fields['hero']['enabled']);
        $this->assertEquals(4.5, $page->template_fields['hero']['amount']);
        $this->get(route('pages.edit', $page))->assertInertia(fn (Assert $view) => $view->where('page.page_template_id', $template->id)->where('page.template_fields.hero.heading', 'Welcome'));
        $this->patch(route('pages.update', $page), ['title' => 'Legacy update', 'slug' => $page->slug, 'is_published' => false])->assertSessionHasNoErrors();
        $this->assertSame('Welcome', $page->refresh()->template_fields['hero']['heading']);
        $other = PageTemplate::factory()->create();
        $this->patch(route('pages.update', $page), ['title' => 'Switch', 'slug' => $page->slug, 'is_published' => false, 'page_template_id' => $other->id, 'template_fields' => []])->assertSessionHasNoErrors();
        $this->assertSame([], $page->refresh()->template_fields);
        $this->patch(route('pages.update', $page), ['title' => 'Standard', 'slug' => $page->slug, 'is_published' => true, 'page_template_id' => null, 'template_fields' => []])->assertSessionHasNoErrors();
        $this->assertNull($page->refresh()->page_template_id);
        $this->assertNull($page->template_fields);
    }

    #[DataProvider('invalidValues')]
    public function test_page_fields_are_validated_against_the_selected_template(string $path, mixed $value, string $error): void
    {
        $template = $this->template();
        $data = $this->pageData($template);
        data_set($data, $path, $value);
        $this->actingAs($this->editor(false))->postJson(route('pages.store'), $data)->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertSame(0, Page::query()->count());
    }

    public static function invalidValues(): array
    {
        return [
            'missing heading' => ['template_fields.hero.heading', null, 'template_fields.hero.heading'],
            'unknown field' => ['template_fields.hero.secret', 'bad', 'template_fields.hero'],
            'unknown set' => ['template_fields.secret', [], 'template_fields'],
            'image script' => ['template_fields.hero.image', 'javascript:alert(1)', 'template_fields.hero.image'],
            'invalid choice' => ['template_fields.hero.style', 'other', 'template_fields.hero.style'],
            'invalid boolean' => ['template_fields.hero.enabled', 'yes', 'template_fields.hero.enabled'],
            'invalid number' => ['template_fields.hero.amount', [], 'template_fields.hero.amount'],
            'class injection' => ['template_fields.hero.items.source', User::class, 'template_fields.hero.items.source'],
            'column injection' => ['template_fields.hero.items.order_by', 'password', 'template_fields.hero.items.order_by'],
            'direction injection' => ['template_fields.hero.items.direction', 'raw', 'template_fields.hero.items.direction'],
            'limit too high' => ['template_fields.hero.items.limit', 101, 'template_fields.hero.items.limit'],
            'limit zero' => ['template_fields.hero.items.limit', 0, 'template_fields.hero.items.limit'],
            'missing template' => ['page_template_id', 9999, 'page_template_id'],
            'invalid template shape' => ['page_template_id', ['foo'], 'page_template_id'],
            'fields without template' => ['page_template_id', null, 'template_fields'],
        ];
    }

    public function test_custom_public_components_receive_only_current_fields_and_public_model_data(): void
    {
        $template = $this->template();
        $page = Page::factory()->published()->create(['page_template_id' => $template->id, 'template_fields' => $this->pageData($template)['template_fields']]);
        $alpha = Page::factory()->published()->create(['title' => 'Alpha']);
        Page::factory()->published()->create(['title' => 'Beta']);
        Page::factory()->draft()->create(['title' => 'A draft']);
        Page::factory()->published()->create(['title' => 'A scheduled', 'published_at' => now()->addDay()]);
        Page::factory()->create(['title' => 'A missing date', 'is_published' => true, 'published_at' => null]);
        $private = Media::factory()->create(['visibility' => 'private', 'mime_type' => 'image/png']);
        $image = Media::factory()->create(['visibility' => 'public', 'mime_type' => 'image/png', 'thumbnail_path' => 'thumbnail.jpg']);
        $alpha->attachments()->attach([$private->id, $image->id]);
        $this->get(route('content.show', $page->slug))->assertOk()->assertInertia(fn (Assert $view) => $view
            ->component('content/Collection')->where('fields.hero.heading', 'Welcome')
            ->has('collections.hero.items', 1)->where('collections.hero.items.0.title', 'Alpha')
            ->where('collections.hero.items.0.image', route('media-files.thumbnail', $image))
            ->missing('collections.hero.items.0.body')->missing('collections.hero.items.0.template_fields'));
        $fields = $page->template_fields;
        $fields['hero']['items']['direction'] = 'desc';
        $fields['hero']['image'] = 'javascript:alert(1)';
        $fields['hero']['obsolete'] = 'Private old data';
        $page->update(['template_fields' => $fields]);
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $view) => $view
            ->where('collections.hero.items.0.title', 'Beta')->where('fields.hero.image', null)->missing('fields.hero.obsolete'));
        config(['starter.features.media' => false]);
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $view) => $view->where('collections.hero.items.0.image', null));
    }

    public function test_unavailable_renderer_falls_back_and_in_use_templates_cannot_be_deleted(): void
    {
        $template = $this->template();
        $page = Page::factory()->published()->create(['page_template_id' => $template->id]);
        $this->actingAs($this->editor())->delete(route('page-templates.destroy', $template))->assertSessionHasErrors('template');
        $this->assertModelExists($template);
        $set = $template->fieldSets->first();
        $this->patch(route('page-field-sets.update', $set), ['name' => $set->name, 'key' => 'renamed', 'fields' => $set->fields])->assertSessionHasErrors('key');
        $template->update(['renderer' => 'missing']);
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $view) => $view->component('content/Show')->missing('fields')->missing('collections'));
        $this->postJson(route('pages.store'), $this->pageData($template))->assertUnprocessable()->assertJsonValidationErrors('page_template_id');
        $page->update(['is_published' => false]);
        $this->get(route('content.show', $page->slug))->assertNotFound();
    }

    public function test_template_changes_can_be_previewed_privately_before_publication(): void
    {
        $template = $this->template();
        $page = Page::factory()->published()->create(['title' => 'Live', 'slug' => 'live']);
        $this->actingAs($this->editor());
        $this->patch(route('pages.update', $page), [...$this->pageData($template), 'intent' => 'preview'])->assertSessionHasNoErrors();
        $this->assertNull($page->refresh()->page_template_id);
        $this->get(route('content.show', 'live'))->assertInertia(fn (Assert $view) => $view->component('content/Show')->missing('fields'));
        $this->get(route('content.preview', $page))->assertInertia(fn (Assert $view) => $view->component('content/Collection')->where('fields.hero.heading', 'Welcome')->where('preview.id', $page->id));
        $this->get(route('pages.edit', $page))->assertInertia(fn (Assert $view) => $view->where('page.page_template_id', $template->id)->where('page.template_fields.hero.heading', 'Welcome'));
        $this->delete(route('page-templates.destroy', $template))->assertSessionHasErrors('template');
        $this->patch(route('pages.update', $page), [...$this->pageData($template), 'intent' => 'publish'])->assertSessionHasNoErrors();
        $this->assertSame($template->id, $page->refresh()->page_template_id);
        $this->get(route('content.show', 'special'))->assertInertia(fn (Assert $view) => $view->component('content/Collection')->where('fields.hero.heading', 'Welcome')->where('preview', null));
    }

    public function test_removed_or_changed_fields_and_sources_do_not_expose_stale_values(): void
    {
        $template = $this->template();
        $page = Page::factory()->published()->create(['page_template_id' => $template->id, 'template_fields' => $this->pageData($template)['template_fields']]);
        $set = $template->fieldSets->first();
        $set->update(['fields' => [$this->field('heading', 'number'), $this->field('items', 'collection')]]);
        config(['page_templates.sources' => []]);
        $this->get(route('content.show', $page->slug))->assertInertia(fn (Assert $view) => $view->where('fields.hero.heading', null)->where('fields.hero.items', null)->missing('fields.hero.image')->where('collections', []));
    }

    public function test_seeded_examples_are_repeatable_and_preserve_custom_edits(): void
    {
        $this->seed(PageTemplateSeeder::class);
        $template = PageTemplate::query()->first();
        $template->fieldSets()->detach();
        $this->seed(PageTemplateSeeder::class);
        $this->assertSame(2, PageTemplate::query()->count());
        $this->assertSame(2, PageFieldSet::query()->count());
        $this->assertSame(0, $template->fieldSets()->count());
    }

    private function editor(bool $manageTemplates = true): User
    {
        $user = User::factory()->create();
        foreach ($manageTemplates ? PagePolicy::PERMISSIONS : [PagePolicy::VIEW, PagePolicy::CREATE, PagePolicy::UPDATE] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        return $user;
    }

    private function field(string $key = 'heading', string $type = 'text', bool $required = false): array
    {
        return ['key' => $key, 'label' => ucfirst($key), 'type' => $type, 'required' => $required, 'options' => []];
    }

    private function template(): PageTemplate
    {
        $template = PageTemplate::factory()->create(['renderer' => 'collection']);
        $set = PageFieldSet::factory()->create(['key' => 'hero', 'fields' => [
            $this->field('heading', 'text', true), $this->field('image', 'image'), $this->field('enabled', 'boolean'), $this->field('amount', 'number'),
            [...$this->field('style', 'select'), 'options' => ['light', 'dark']], $this->field('items', 'collection', true),
        ]]);
        $template->fieldSets()->attach($set, ['position' => 0]);

        return $template;
    }

    private function pageData(PageTemplate $template): array
    {
        return ['title' => 'Special', 'slug' => 'special', 'is_published' => true, 'page_template_id' => $template->id, 'template_fields' => ['hero' => [
            'heading' => 'Welcome', 'image' => 'https://example.com/image.jpg', 'enabled' => false, 'amount' => 4.5, 'style' => 'dark',
            'items' => ['source' => 'pages', 'limit' => 1, 'order_by' => 'title', 'direction' => 'asc'],
        ]]];
    }
}
