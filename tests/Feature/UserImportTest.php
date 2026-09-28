<?php

namespace Tests\Feature;

use App\Actions\Permissions\SyncPolicyPermissions;
use App\Actions\Users\ImportUsers;
use App\Models\CsvImport;
use App\Models\User;
use App\Notifications\UserPasswordSetup;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_users_share_the_import_pipeline_and_create_invited_accounts_once(): void
    {
        $this->operator();
        $this->get($this->url('create'))->assertInertia(fn (Assert $page) => $page->component('imports/Import')->where('resource', 'users')->where('label', 'Utilisateurs'));
        $batch = $this->upload("Nom;E-mail\nAlice;  ALICE@example.com  \nBob;bob@example.com\n");
        $this->assertSame(['name' => 0, 'email' => 1, 'active' => null, 'email_verified' => null, 'roles' => null], $batch->mapping);
        $this->preview($batch);
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
        $this->confirm($batch)->assertRedirect();
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $this->assertSame('Alice', $alice->name);
        $this->assertNull($alice->email_verified_at);
        $this->assertNull($alice->disabled_at);
        $this->assertNotNull($alice->invitation_sent_at);
        $this->assertCount(0, $alice->roles);
        Notification::assertSentTo($alice, UserPasswordSetup::class, fn (UserPasswordSetup $notification): bool => $notification->invitation && $notification->afterCommit === true);
        $this->confirm($batch)->assertRedirect();
        Notification::assertSentToTimes($alice, UserPasswordSetup::class, 1);
    }

    public function test_exported_user_columns_can_be_imported_without_changes(): void
    {
        $this->operator();
        $role = Role::query()->create(['name' => 'Reader', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Round trip', 'email' => 'roundtrip@example.com']);
        $user->assignRole($role);
        $csv = $this->get(route('table-exports.users', ['search' => 'roundtrip@example.com']))->assertDownload('utilisateurs.csv')->streamedContent();
        $batch = $this->upload($csv, ',');
        $this->preview($batch, $batch->mapping, 'update');
        $this->assertSame(0, $batch->refresh()->preview['counts']['error']);
        $password = $user->password;
        $verified = $user->email_verified_at;
        $created = $user->created_at;
        $this->confirm($batch)->assertRedirect();
        $this->assertSame($password, $user->refresh()->password);
        $this->assertEquals($verified, $user->email_verified_at);
        $this->assertEquals($created, $user->created_at);
        $this->assertTrue($user->hasRole('Reader'));
        Notification::assertNotSentTo($user, UserPasswordSetup::class);
    }

    public function test_updates_preserve_unmapped_access_and_support_skip_mode(): void
    {
        $this->operator();
        $role = Role::query()->create(['name' => 'Reader', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Before', 'email' => 'existing@example.com', 'disabled_at' => now()]);
        $user->assignRole($role);
        $batch = $this->upload("name;email\nAfter;existing@example.com\n");
        $this->preview($batch);
        $this->confirm($batch)->assertRedirect();
        $this->assertSame('Before', $user->refresh()->name);
        $batch = $this->upload("name;email\nAfter;existing@example.com\n");
        $this->preview($batch, mode: 'update');
        $this->confirm($batch)->assertRedirect();
        $this->assertSame('After', $user->refresh()->name);
        $this->assertNotNull($user->disabled_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('Reader'));
        Notification::assertNotSentTo($user, UserPasswordSetup::class);
    }

    public function test_import_can_assign_authorized_roles_verify_and_disable_accounts(): void
    {
        $this->operator();
        Role::query()->create(['name' => 'Reader', 'guard_name' => 'web']);
        $batch = $this->upload("Nom;E-mail;Statut;Vérifié;Rôles\nAlice;alice@example.com;Désactivé;Oui;Reader\n");
        $this->preview($batch, $batch->mapping);
        $this->confirm($batch)->assertRedirect();
        $user = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Reader'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->disabled_at);
    }

    public function test_missing_permissions_cannot_grant_roles_verify_or_suspend(): void
    {
        $actor = $this->operator();
        $actor->revokePermissionTo([UserPolicy::ASSIGN_ROLES, UserPolicy::VERIFY_EMAIL, UserPolicy::SUSPEND]);
        Role::query()->create(['name' => 'Reader', 'guard_name' => 'web']);
        $batch = $this->upload("Nom;E-mail;Statut;Vérifié;Rôles\nAlice;alice@example.com;Désactivé;Oui;Reader\n");
        $this->preview($batch, $batch->mapping);
        $this->assertSame(1, $batch->refresh()->preview['counts']['error']);
        $this->assertGreaterThanOrEqual(3, count($batch->preview['rows'][0]['errors']));
        $this->confirm($batch)->assertSessionHasErrors('preview_token');
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
        Notification::assertNothingSent();
    }

    public function test_role_assignment_cannot_escalate_permissions_or_grant_administrator(): void
    {
        $actor = $this->operator();
        $actor->revokePermissionTo(UserPolicy::DELETE);
        $powerful = Role::query()->create(['name' => 'Powerful', 'guard_name' => 'web']);
        $powerful->givePermissionTo(UserPolicy::DELETE);
        $batch = $this->upload("name;email;roles\nAlice;alice@example.com;Powerful\nBob;bob@example.com;Administrator\n");
        $this->preview($batch, ['name' => 0, 'email' => 1, 'roles' => 2]);
        $this->assertSame(2, $batch->refresh()->preview['counts']['error']);
        $this->confirm($batch)->assertSessionHasErrors();
    }

    public function test_role_removal_and_self_changes_obey_existing_safeguards(): void
    {
        $actor = $this->operator();
        $role = Role::query()->create(['name' => 'Reader', 'guard_name' => 'web']);
        $target = User::factory()->create(['email' => 'target@example.com']);
        $target->assignRole($role);
        $actor->assignRole($role);
        $actor->revokePermissionTo(UserPolicy::ASSIGN_ROLES);
        $batch = $this->upload("name;email;roles\nTarget;target@example.com;\nSelf;{$actor->email};\n");
        $this->preview($batch, ['name' => 0, 'email' => 1, 'roles' => 2], 'update');
        $this->assertSame(2, $batch->refresh()->preview['counts']['error']);
        $this->confirm($batch)->assertSessionHasErrors();
        $this->assertTrue($target->refresh()->hasRole('Reader'));
    }

    public function test_final_administrator_verification_is_protected(): void
    {
        $actor = $this->operator();
        $actor->assignRole(RolePolicy::ADMINISTRATOR_ROLE);
        $batch = $this->upload("name;email;email_verified\nSelf;{$actor->email};Non\n");
        $this->preview($batch, ['name' => 0, 'email' => 1, 'email_verified' => 2], 'update');
        $this->assertSame(1, $batch->refresh()->preview['counts']['error']);
        $this->confirm($batch)->assertSessionHasErrors();
        $this->assertNotNull($actor->refresh()->email_verified_at);
    }

    public function test_batch_cannot_unverify_every_administrator_even_if_individual_previews_allow_it(): void
    {
        $actor = $this->operator();
        $actor->assignRole(RolePolicy::ADMINISTRATOR_ROLE);
        $other = User::factory()->create(['email' => 'other-admin@example.com']);
        $other->assignRole(RolePolicy::ADMINISTRATOR_ROLE);
        $batch = $this->upload("name;email;email_verified\nSelf;{$actor->email};Non\nOther;{$other->email};Non\n");
        $this->preview($batch, ['name' => 0, 'email' => 1, 'email_verified' => 2], 'update');
        $this->confirm($batch)->assertSessionHasErrors();
        $this->assertNotNull($actor->refresh()->email_verified_at);
        $this->assertNotNull($other->refresh()->email_verified_at);
        $this->assertNull($batch->refresh()->completed_at);
    }

    public function test_duplicate_emails_invalid_fields_and_unknown_roles_block_every_row(): void
    {
        $this->operator();
        $batch = $this->upload("name;email;roles\nAlice;ALICE@example.com;\nAgain;alice@example.com;\nInvalid;not-email;Missing\n");
        $this->preview($batch, ['name' => 0, 'email' => 1, 'roles' => 2]);
        $this->assertSame(2, $batch->refresh()->preview['counts']['error']);
        $this->confirm($batch)->assertSessionHasErrors();
        $report = $this->get($this->url('errors', $batch))->assertDownload('erreurs-import-users.csv')->streamedContent();
        $this->assertStringContainsString('not-email', $report);
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
    }

    public function test_changed_roles_or_revoked_permissions_invalidate_confirmation(): void
    {
        $actor = $this->operator();
        $role = Role::query()->create(['name' => 'Reader', 'guard_name' => 'web']);
        $target = User::factory()->create(['email' => 'target@example.com']);
        $batch = $this->upload("name;email\nTarget;target@example.com\n");
        $this->preview($batch, mode: 'update');
        $target->assignRole($role);
        $this->confirm($batch)->assertSessionHasErrors('preview_token');
        $this->preview($batch, mode: 'update');
        $actor->revokePermissionTo(UserPolicy::UPDATE);
        $this->confirm($batch)->assertSessionHasErrors('preview_token');
    }

    public function test_imports_are_resource_scoped_owned_and_allowlisted(): void
    {
        $this->actingAs(User::factory()->create())->get($this->url('create'))->assertForbidden();
        $owner = $this->operator();
        $batch = $this->upload("name;email\nAlice;alice@example.com\n");
        $this->preview($batch);
        $owner->givePermissionTo(['pages.view', 'pages.create']);
        $this->get(route('csv-imports.show', ['resource' => 'pages', 'csvImport' => $batch]))->assertNotFound();
        $this->operator();
        $this->get($this->url('show', $batch))->assertNotFound();
        $this->get($this->url('errors', $batch))->assertNotFound();
        $this->confirm($batch)->assertNotFound();
        $this->get(route('csv-imports.create', ['resource' => 'unregistered']))->assertNotFound();
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_passwords_and_arbitrary_fields_cannot_be_mapped(): void
    {
        $this->operator();
        $batch = $this->upload("name;email;password\nAlice;alice@example.com;secret\n");
        $this->patch($this->url('preview', $batch), ['mapping' => ['name' => 0, 'email' => 1, 'password' => 2], 'duplicate_mode' => 'skip'])->assertSessionHasErrors('mapping');
        $this->assertNull($batch->refresh()->preview);
    }

    public function test_export_round_trip_preserves_roles_containing_commas(): void
    {
        $this->operator();
        $first = Role::query()->create(['name' => 'Sales, Europe', 'guard_name' => 'web']);
        $second = Role::query()->create(['name' => 'Reader', 'guard_name' => 'web']);
        $user = User::factory()->create(['email' => 'comma@example.com']);
        $user->assignRole([$first, $second]);
        $csv = $this->get(route('table-exports.users', ['search' => 'comma@example.com']))->streamedContent();
        $batch = $this->upload($csv, ',');
        $this->preview($batch, $batch->mapping, 'update');
        $this->assertSame(0, $batch->refresh()->preview['counts']['error']);
        $this->confirm($batch)->assertRedirect();
        $this->assertSame(['Reader', 'Sales, Europe'], $user->refresh()->getRoleNames()->sort()->values()->all());
    }

    public function test_imported_suspension_revokes_sessions_and_can_be_reversed(): void
    {
        $this->operator();
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['email' => 'target@example.com']);
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $batch = $this->upload("name;email;active\nTarget;target@example.com;Désactivé\n");
        $this->preview($batch, ['name' => 0, 'email' => 1, 'active' => 2], 'update');
        $this->confirm($batch)->assertRedirect();
        $this->assertNotNull($user->refresh()->disabled_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $batch = $this->upload("name;email;active\nTarget;target@example.com;Actif\n");
        $this->preview($batch, ['name' => 0, 'email' => 1, 'active' => 2], 'update');
        $this->confirm($batch)->assertRedirect();
        $this->assertNull($user->refresh()->disabled_at);
    }

    public function test_a_registered_adapter_reuses_routes_controller_and_screen(): void
    {
        $this->operator();
        config(['imports.resources.directory' => DirectoryImportForTest::class]);
        $this->get(route('csv-imports.create', ['resource' => 'directory']))->assertInertia(fn (Assert $page) => $page
            ->component('imports/Import')->where('resource', 'directory')->where('label', 'Annuaire'));
        $this->post(route('csv-imports.store', ['resource' => 'directory']), [
            'file' => UploadedFile::fake()->createWithContent('directory.csv', "name;email\nDirectory;directory@example.com\n"), 'delimiter' => ';',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $batch = CsvImport::query()->latest('id')->firstOrFail();
        $params = ['resource' => 'directory', 'csvImport' => $batch];
        $this->patch(route('csv-imports.preview', $params), ['mapping' => ['name' => 0, 'email' => 1], 'duplicate_mode' => 'skip'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('csv-imports.commit', $params), ['preview_token' => $batch->refresh()->preview_token])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'directory@example.com']);
    }

    private function operator(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(UserPolicy::PERMISSIONS);
        $this->actingAs($user);

        return $user;
    }

    private function url(string $action, ?CsvImport $batch = null): string
    {
        return route('csv-imports.'.$action, ['resource' => 'users', ...($batch === null ? [] : ['csvImport' => $batch])]);
    }

    private function upload(string $csv, string $delimiter = ';'): CsvImport
    {
        $this->post($this->url('store'), ['file' => UploadedFile::fake()->createWithContent('users.csv', $csv), 'delimiter' => $delimiter])->assertSessionHasNoErrors()->assertRedirect();

        return CsvImport::query()->latest('id')->firstOrFail();
    }

    private function preview(CsvImport $batch, array $mapping = ['name' => 0, 'email' => 1], string $mode = 'skip'): void
    {
        $this->patch($this->url('preview', $batch), ['mapping' => $mapping, 'duplicate_mode' => $mode])->assertSessionHasNoErrors()->assertRedirect();
        $batch->refresh();
    }

    private function confirm(CsvImport $batch): TestResponse
    {
        return $this->post($this->url('commit', $batch), ['preview_token' => $batch->refresh()->preview_token]);
    }
}

class DirectoryImportForTest extends ImportUsers
{
    public function definition(): array
    {
        return [...parent::definition(), 'key' => 'directory', 'label' => 'Annuaire'];
    }
}
