<?php

namespace Tests\Feature;

use App\Actions\Imports\ReadCsv;
use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Activity;
use App\Models\CsvImport;
use App\Models\Page;
use App\Models\User;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_upload_mapping_preview_and_confirmation_create_pages_only_once(): void
    {
        $this->operator();
        $batch = $this->upload("Titre;Identifiant URL;Statut;Contenu;Format du contenu\nBienvenue;bienvenue;Publiée;\"<p>Bonjour</p><script>alert(1)</script>\";html\nAutre;autre;Brouillon;Texte;text\n");
        $this->assertDatabaseCount('pages', 0);
        $this->assertStringNotContainsString('Bienvenue', DB::table('csv_imports')->value('document'));
        $this->get(route('csv-imports.show', ['resource' => 'pages', 'csvImport' => $batch]))->assertInertia(fn (Assert $page) => $page->component('imports/Import')->where('batch.total', 2)->where('batch.mapping.title', 0));
        $this->preview($batch, ['title' => 0, 'slug' => 1, 'is_published' => 2, 'body' => 3, 'body_format' => 4]);
        $this->assertSame(2, $batch->refresh()->preview['counts']['create']);
        $this->assertDatabaseCount('pages', 0);
        $before = Activity::query()->count();
        $token = $batch->preview_token;
        $this->post(route('csv-imports.commit', ['resource' => 'pages', 'csvImport' => $batch]), ['preview_token' => $token, 'mapping' => ['title' => 9]])->assertRedirect(route('csv-imports.show', ['resource' => 'pages', 'csvImport' => $batch]));
        $page = Page::query()->where('slug', 'bienvenue')->firstOrFail();
        $this->assertTrue($page->is_published);
        $this->assertNotNull($page->published_at);
        $this->assertStringNotContainsString('<script', $page->body);
        $this->assertSame($before + 2, Activity::query()->count());
        $this->assertNull($batch->refresh()->document);
        $this->assertNotNull($batch->completed_at);
        $this->get(route('csv-imports.show', ['resource' => 'pages', 'csvImport' => $batch]))->assertInertia(fn (Assert $page) => $page->where('batch.completed', true)->where('batch.preview.counts.create', 2));
        $this->post(route('csv-imports.commit', ['resource' => 'pages', 'csvImport' => $batch]), ['preview_token' => $token])->assertRedirect();
        $this->assertDatabaseCount('pages', 2);
        $this->assertSame($before + 2, Activity::query()->count());
    }

    public function test_skip_and_update_preserve_unmapped_fields_and_audit_updates(): void
    {
        $this->operator();
        $existing = Page::factory()->published()->create(['slug' => 'existing', 'title' => 'Original', 'body' => '<p>Keep</p>', 'body_format' => 'html', 'excerpt' => 'Keep excerpt']);
        $batch = $this->upload("title,slug\nChanged,existing\nNew,new\n", ',');
        $this->preview($batch);
        $this->assertSame(['create' => 1, 'update' => 0, 'skip' => 1, 'error' => 0], $batch->refresh()->preview['counts']);
        $this->confirm($batch)->assertRedirect();
        $this->assertSame('Original', $existing->refresh()->title);
        $this->assertFalse(Page::query()->where('slug', 'new')->firstOrFail()->is_published);
        $batch = $this->upload("title,slug\nChanged,existing\n", ',');
        $this->preview($batch, mode: 'update');
        $before = Activity::query()->count();
        $date = $existing->published_at;
        $this->confirm($batch)->assertRedirect();
        $this->assertSame('Changed', $existing->refresh()->title);
        $this->assertSame('<p>Keep</p>', $existing->body);
        $this->assertSame('html', $existing->body_format);
        $this->assertSame('Keep excerpt', $existing->excerpt);
        $this->assertEquals($date, $existing->published_at);
        $this->assertSame($before + 1, Activity::query()->count());
    }

    public function test_any_invalid_row_blocks_every_write_and_exports_all_errors_safely(): void
    {
        $this->operator();
        $batch = $this->upload("Titre;Identifiant URL;=extra\nValid;valid;ok\n=1+1;;@formula\nAgain;valid;ok\nToo;many;cells;here\n");
        $this->preview($batch);
        $this->assertSame(3, $batch->refresh()->preview['counts']['error']);
        $this->confirm($batch)->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('pages', 0);
        $report = $this->get(route('csv-imports.errors', ['resource' => 'pages', 'csvImport' => $batch]))->assertDownload('erreurs-import-pages.csv')->streamedContent();
        $this->assertStringContainsString("'=1+1", $report);
        $this->assertStringContainsString("'@formula", $report);
        $this->assertStringContainsString("'=extra", $report);
        $this->assertStringContainsString('Again', $report);
        $this->assertStringContainsString('here', $report);
        $this->assertStringNotContainsString('Valid,valid', $report);
    }

    public function test_permissions_ownership_and_feature_flags_protect_every_endpoint(): void
    {
        $this->get(route('csv-imports.create', ['resource' => 'pages']))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('csv-imports.create', ['resource' => 'pages']))->assertForbidden();
        $owner = $this->operator();
        $batch = $this->upload("title;slug\nNew;new\n");
        $this->preview($batch);
        $this->operator();
        $this->get(route('csv-imports.show', ['resource' => 'pages', 'csvImport' => $batch]))->assertNotFound();
        $this->get(route('csv-imports.errors', ['resource' => 'pages', 'csvImport' => $batch]))->assertNotFound();
        $this->delete(route('csv-imports.destroy', ['resource' => 'pages', 'csvImport' => $batch]))->assertNotFound();
        $this->patch(route('csv-imports.preview', ['resource' => 'pages', 'csvImport' => $batch]), ['mapping' => ['title' => 0, 'slug' => 1], 'duplicate_mode' => 'skip'])->assertNotFound();
        $this->confirm($batch)->assertNotFound();
        $this->actingAs($owner);
        config(['starter.features.pages' => false]);
        $this->get(route('csv-imports.create', ['resource' => 'pages']))->assertNotFound();
        $this->get(route('csv-imports.show', ['resource' => 'pages', 'csvImport' => $batch]))->assertNotFound();
        $this->confirm($batch)->assertNotFound();
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_update_authorization_is_checked_per_record_at_preview_and_confirmation(): void
    {
        $user = $this->operator();
        $existing = Page::factory()->create(['slug' => 'existing']);
        $batch = $this->upload("title;slug\nNew;new\nChanged;existing\n");
        $this->preview($batch, mode: 'update');
        $user->revokePermissionTo(PagePolicy::UPDATE);
        $this->confirm($batch)->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('pages', 1);
        $user->givePermissionTo(PagePolicy::UPDATE);
        Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $ability === 'update' && ($arguments[0] ?? null) instanceof Page ? false : null);
        $this->preview($batch, mode: 'update');
        $this->assertSame(1, $batch->refresh()->preview['counts']['error']);
        $this->assertNotSame('Changed', $existing->refresh()->title);
    }

    public function test_stale_preview_and_old_preview_tokens_cannot_overwrite_changes(): void
    {
        $this->operator();
        $existing = Page::factory()->create(['slug' => 'existing']);
        $batch = $this->upload("title;slug\nChanged;existing\nNew;new\n");
        $this->preview($batch, mode: 'update');
        $existing->update(['title' => 'Someone else edited this']);
        $this->confirm($batch)->assertSessionHasErrors('preview_token');
        $this->assertSame('Someone else edited this', $existing->refresh()->title);
        $this->assertDatabaseCount('pages', 1);
        $oldToken = $batch->refresh()->preview_token;
        $this->preview($batch, mode: 'update');
        $this->post(route('csv-imports.commit', ['resource' => 'pages', 'csvImport' => $batch]), ['preview_token' => $oldToken])->assertSessionHasErrors('preview_token');
        $this->confirm($batch)->assertRedirect();
        $this->assertSame('Changed', $existing->refresh()->title);
    }

    public function test_new_duplicate_after_preview_requires_a_new_preview(): void
    {
        $this->operator();
        $batch = $this->upload("title;slug\nNew;new\n");
        $this->preview($batch);
        Page::factory()->create(['slug' => 'new', 'title' => 'Concurrent']);
        $this->confirm($batch)->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('pages', 1);
    }

    public function test_mapping_rejects_missing_unknown_reused_and_out_of_range_columns(): void
    {
        $this->operator();
        $batch = $this->upload("title;slug\nNew;new\n");
        foreach ([[], ['title' => 0], ['title' => 0, 'slug' => 0], ['title' => 0, 'slug' => 2], ['title' => 0, 'slug' => 1, 'admin' => 2]] as $mapping) {
            $this->patch(route('csv-imports.preview', ['resource' => 'pages', 'csvImport' => $batch]), ['mapping' => $mapping, 'duplicate_mode' => 'skip'])->assertSessionHasErrors();
        }
        $this->assertNull($batch->refresh()->preview);
        $this->post(route('csv-imports.commit', ['resource' => 'pages', 'csvImport' => $batch]), ['preview_token' => fake()->uuid()])->assertSessionHasErrors('preview_token');
    }

    public function test_expired_imports_are_unusable_and_pruned_and_cancel_deletes_data(): void
    {
        $this->operator();
        $batch = $this->upload("title;slug\nNew;new\n");
        $this->preview($batch);
        $batch->update(['expires_at' => now()->subMinute()]);
        $this->get(route('csv-imports.show', ['resource' => 'pages', 'csvImport' => $batch]))->assertStatus(410);
        $this->confirm($batch)->assertStatus(410);
        $this->artisan('model:prune', ['--model' => [CsvImport::class]])->assertSuccessful();
        $this->assertModelMissing($batch);
        $batch = $this->upload("title;slug\nNew;new\n");
        $this->delete(route('csv-imports.destroy', ['resource' => 'pages', 'csvImport' => $batch]))->assertRedirect(route('csv-imports.create', ['resource' => 'pages']));
        $this->assertModelMissing($batch);
    }

    public function test_bom_quoted_multiline_fields_blank_lines_and_tab_delimiters_are_supported(): void
    {
        $this->operator();
        $batch = $this->upload("\xEF\xBB\xBFtitle\tslug\tbody\n\n\"Hello, \"\"world\"\"\"\thello\t\"First line\nSecond line\"\n", 'tab');
        $this->preview($batch, ['title' => 0, 'slug' => 1, 'body' => 2]);
        $this->confirm($batch)->assertRedirect();
        $page = Page::query()->firstOrFail();
        $this->assertSame('Hello, "world"', $page->title);
        $this->assertSame("First line\nSecond line", $page->body);
        $this->assertSame('text', $page->body_format);
    }

    public function test_invalid_files_are_rejected_without_persisting_imports(): void
    {
        $this->operator();
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['', "title;slug\n", "title;title\nA;a", "title;\nA;a", "title;slug\n\xFF;a", "title;slug\n\0;a", "title;slug\n\"Unclosed;a", "title;slug\nA\"bad;a", "title;slug\n\"Closed\"bad;a", "title;slug\n".str_repeat("A;a\n", ReadCsv::MAX_ROWS + 1)] as $contents) {
            $this->post(route('csv-imports.store', ['resource' => 'pages']), ['file' => UploadedFile::fake()->createWithContent('data.csv', $contents), 'delimiter' => ';'])->assertSessionHasErrors('file');
        }
        $this->post(route('csv-imports.store', ['resource' => 'pages']), ['file' => UploadedFile::fake()->create('data.csv', 2049, 'text/csv'), 'delimiter' => ';'])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('csv_imports', 0);
    }

    public function test_invalid_publication_values_and_duplicate_slugs_are_reported(): void
    {
        $this->operator();
        $batch = $this->upload("title;slug;status\nFirst;my-page;perhaps\nSecond;my-page;0\n");
        $this->preview($batch, ['title' => 0, 'slug' => 1, 'is_published' => 2]);
        $this->assertSame(2, $batch->refresh()->preview['counts']['error']);
        $this->confirm($batch)->assertSessionHasErrors();
    }

    public function test_late_errors_are_validated_and_downloaded_beyond_the_preview_limit(): void
    {
        $this->operator();
        $csv = "title;slug\n";
        for ($index = 1; $index <= 105; $index++) {
            $csv .= "Page {$index};page-{$index}\n";
        }
        $csv .= "Missing identifier;\n";
        $batch = $this->upload($csv);
        $this->preview($batch);
        $this->get(route('csv-imports.show', ['resource' => 'pages', 'csvImport' => $batch]))->assertInertia(fn (Assert $page) => $page
            ->where('batch.total', 106)->has('batch.preview.rows', 100)->where('batch.preview.counts.error', 1));
        $this->assertStringContainsString('Missing identifier', $this->get(route('csv-imports.errors', ['resource' => 'pages', 'csvImport' => $batch]))->streamedContent());
        $this->confirm($batch)->assertSessionHasErrors();
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_mapped_empty_cells_clear_content_but_zero_is_preserved(): void
    {
        $this->operator();
        $existing = Page::factory()->published()->create(['slug' => 'existing', 'excerpt' => 'Old', 'body' => 'Old']);
        $batch = $this->upload("title;slug;excerpt;body;status\nChanged;existing;;0;0\n");
        $this->preview($batch, ['title' => 0, 'slug' => 1, 'excerpt' => 2, 'body' => 3, 'is_published' => 4], 'update');
        $this->confirm($batch)->assertRedirect();
        $this->assertNull($existing->refresh()->excerpt);
        $this->assertSame('0', $existing->body);
        $this->assertFalse($existing->is_published);
        $this->assertNull($existing->published_at);
    }

    public function test_a_write_failure_rolls_back_pages_audit_history_and_completion(): void
    {
        $this->operator();
        $batch = $this->upload("title;slug\nFirst;first\nSecond;second\n");
        $this->preview($batch);
        $before = Activity::query()->count();
        Page::creating(function (Page $page): void {
            if ($page->slug === 'second') {
                throw new \RuntimeException('Simulated write failure');
            }
        });
        $this->confirm($batch)->assertStatus(500);
        $this->assertDatabaseCount('pages', 0);
        $this->assertSame($before, Activity::query()->count());
        $this->assertNull($batch->refresh()->completed_at);
        $this->assertNotNull($batch->document);
    }

    private function operator(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PagePolicy::PERMISSIONS);
        $this->actingAs($user);

        return $user;
    }

    private function upload(string $contents, string $delimiter = ';'): CsvImport
    {
        $response = $this->post(route('csv-imports.store', ['resource' => 'pages']), ['file' => UploadedFile::fake()->createWithContent('data.csv', $contents), 'delimiter' => $delimiter]);
        $response->assertSessionHasNoErrors()->assertRedirect();

        return CsvImport::query()->latest('id')->firstOrFail();
    }

    private function preview(CsvImport $batch, array $mapping = ['title' => 0, 'slug' => 1], string $mode = 'skip'): void
    {
        $this->patch(route('csv-imports.preview', ['resource' => 'pages', 'csvImport' => $batch]), ['mapping' => $mapping, 'duplicate_mode' => $mode])->assertSessionHasNoErrors()->assertRedirect();
        $batch->refresh();
    }

    private function confirm(CsvImport $batch): TestResponse
    {
        return $this->post(route('csv-imports.commit', ['resource' => 'pages', 'csvImport' => $batch]), ['preview_token' => $batch->refresh()->preview_token]);
    }
}
