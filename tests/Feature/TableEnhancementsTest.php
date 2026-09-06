<?php

namespace Tests\Feature;

use App\Actions\Exports\ExportCsv;
use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\Activity;
use App\Models\Page;
use App\Models\User;
use App\Policies\PagePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TableEnhancementsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_exports_require_resource_access_and_respect_feature_flags(): void
    {
        $this->get(route('table-exports.pages'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        foreach (['pages', 'users', 'roles'] as $resource) {
            $this->get(route('table-exports.'.$resource))->assertForbidden();
        }
        $this->operator();
        config(['starter.features.pages' => false]);
        $this->get(route('table-exports.pages'))->assertNotFound();
        $this->patch(route('pages.bulk'), ['ids' => [1], 'action' => 'publish'])->assertNotFound();
    }

    public function test_page_export_includes_all_filtered_rows_in_requested_order_without_bodies(): void
    {
        $this->operator();
        for ($i = 0; $i < 15; $i++) {
            Page::factory()->draft()->create(['title' => sprintf('Selected %02d', $i), 'body' => 'private-body']);
        }
        Page::factory()->published()->create(['title' => 'Excluded']);
        $response = $this->get(route('table-exports.pages', ['status' => 'draft', 'sort' => 'title', 'direction' => 'asc', 'per_page' => 10]));
        $response->assertDownload('pages.csv')->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertSame(15, substr_count($csv, 'Selected '));
        $this->assertStringNotContainsString('Excluded', $csv);
        $this->assertStringNotContainsString('private-body', $csv);
        $this->assertLessThan(strpos($csv, 'Selected 14'), strpos($csv, 'Selected 00'));
    }

    public function test_user_and_role_exports_apply_their_existing_filters(): void
    {
        $this->operator();
        $role = Role::query()->create(['name' => 'Reviewers', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Selected user']);
        $user->assignRole($role);
        User::factory()->create(['name' => 'Excluded user']);
        $users = $this->get(route('table-exports.users', ['role' => 'Reviewers']))->assertDownload('utilisateurs.csv')->streamedContent();
        $this->assertStringContainsString('Selected user', $users);
        $this->assertStringNotContainsString('Excluded user', $users);
        $this->assertStringNotContainsString('password', $users);
        $roles = $this->get(route('table-exports.roles', ['assignment' => 'assigned']))->assertDownload('roles.csv')->streamedContent();
        $this->assertStringContainsString('Reviewers', $roles);
        $this->assertStringNotContainsString('Administrator', $roles);
    }

    public function test_csv_cells_are_escaped_against_spreadsheet_formulas(): void
    {
        $csv = app(ExportCsv::class);
        foreach (['=1+1', '+cmd', '-1+2', '@SUM(A1)', "\t=1+1", '  =1+1', "\rtext"] as $input) {
            $this->assertSame("'".$input, $csv->safeCell($input));
        }
        $this->assertSame('Normal, "quoted" text', $csv->safeCell('Normal, "quoted" text'));
        $this->operator();
        Page::factory()->create(['title' => '=1+1']);
        $this->assertStringContainsString("'=1+1", $this->get(route('table-exports.pages'))->streamedContent());
    }

    public function test_exports_refuse_to_silently_truncate_large_results(): void
    {
        $this->expectException(HttpException::class);
        app(ExportCsv::class)->download(array_fill(0, ExportCsv::MAX_ROWS + 1, ['Title']), 'pages.csv', ['Title']);
    }

    public function test_bulk_publication_and_unpublication_keep_audit_history_and_dates(): void
    {
        $this->operator();
        $pages = Page::factory()->count(2)->draft()->create();
        $ids = $pages->modelKeys();
        $before = Activity::query()->count();
        $this->patch(route('pages.bulk'), ['ids' => $ids, 'action' => 'publish'])->assertRedirect();
        foreach ($pages as $page) {
            $this->assertTrue($page->refresh()->is_published);
            $this->assertNotNull($page->published_at);
        }
        $this->assertSame($before + 2, Activity::query()->count());
        $publishedAt = $pages->first()->published_at;
        $this->patch(route('pages.bulk'), ['ids' => $ids, 'action' => 'publish'])->assertRedirect();
        $this->assertEquals($publishedAt, $pages->first()->refresh()->published_at);
        $this->patch(route('pages.bulk'), ['ids' => $ids, 'action' => 'unpublish'])->assertRedirect();
        foreach ($pages as $page) {
            $this->assertFalse($page->refresh()->is_published);
            $this->assertNull($page->published_at);
        }
    }

    public function test_bulk_actions_authorize_every_row_and_never_partially_apply(): void
    {
        $this->operator();
        $pages = Page::factory()->count(2)->draft()->create();
        $deniedId = $pages->last()->id;
        Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $ability === 'update' && ($arguments[0] ?? null) instanceof Page && $arguments[0]->id === $deniedId ? false : null);
        $before = Activity::query()->count();
        $this->patch(route('pages.bulk'), ['ids' => $pages->modelKeys(), 'action' => 'publish'])->assertForbidden();
        $this->assertSame(0, Page::query()->where('is_published', true)->count());
        $this->assertSame($before, Activity::query()->count());
        $this->patch(route('pages.bulk'), ['ids' => [$pages->first()->id, 999999], 'action' => 'publish'])->assertSessionHasErrors('ids');
        $this->assertSame(0, Page::query()->where('is_published', true)->count());
    }

    public function test_bulk_validation_rejects_empty_duplicate_oversized_and_invalid_actions(): void
    {
        $this->operator();
        foreach ([[], [1, 1], range(1, 51)] as $ids) {
            $this->patch(route('pages.bulk'), ['ids' => $ids, 'action' => 'publish'])->assertSessionHasErrors();
        }
        $this->patch(route('pages.bulk'), ['ids' => [1], 'action' => 'delete'])->assertSessionHasErrors('action');
    }

    public function test_saved_views_validate_storage_and_preserve_filter_values_without_page_number(): void
    {
        $script = <<<'JS'
const assert = require('node:assert/strict');
const ts = require('typescript');
const fs = require('node:fs');
const exports = {};
new Function('exports', ts.transpileModule(fs.readFileSync('resources/js/lib/tableViews.ts', 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText)(exports);
const { parseTableViews, saveTableView } = exports;
assert.deepEqual(parseTableViews('broken json', ['search']), []);
assert.deepEqual(parseTableViews('{}', ['search']), []);
const filters = { search: 'hello', status: 'draft', sort: 'title', direction: 'asc', per_page: 25, page: 3 };
const saved = saveTableView([], '  Drafts  ', filters);
assert.equal(saved[0].name, 'Drafts');
assert.equal(saved[0].filters.page, undefined);
assert.equal(saved[0].filters.status, 'draft');
assert.equal(filters.page, 3);
assert.throws(() => saveTableView(saved, 'drafts', filters));
assert.throws(() => saveTableView(saved, ' ', filters));
assert.throws(() => saveTableView(Array.from({length:10}, (_,i) => ({name: String(i), filters:{}})), 'Eleven', filters));
const parsed = parseTableViews(JSON.stringify([{name: 'Safe', filters: {search:'hello', page: 999, secret: 'ignored', sort: ['invalid']}}]), ['search','sort','page']);
assert.deepEqual(parsed, [{name:'Safe', filters:{search:'hello'}}]);
console.log('Saved views passed');
JS;
        $process = new Process(['node', '-e', $script], base_path());
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }

    private function operator(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([...PagePolicy::PERMISSIONS, ...UserPolicy::PERMISSIONS, ...RolePolicy::PERMISSIONS]);
        $this->actingAs($user);

        return $user;
    }
}
