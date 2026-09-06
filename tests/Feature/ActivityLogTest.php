<?php

namespace Tests\Feature;

use App\Actions\Activity\RecordActivity;
use App\Actions\Media\UploadMedia;
use App\Actions\Permissions\SyncPolicyPermissions;
use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\UpdateRole;
use App\Actions\Users\RecordUserManagementEvent;
use App\Models\Activity;
use App\Models\ApplicationSetting;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(SyncPolicyPermissions::class)->handle();
    }

    public function test_viewer_requires_a_verified_user_with_explicit_permission(): void
    {
        $this->get(route('activity.index'))->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('activity.index'))->assertForbidden();
        $user->givePermissionTo(ActivityPolicy::VIEW);
        $this->get(route('activity.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('activity/Index')->where('auth.can.viewActivity', true));
        $user->forceFill(['email_verified_at' => null])->save();
        $this->get(route('activity.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_page_lifecycle_records_only_allowed_values_and_skips_no_ops(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $page = Page::factory()->create(['title' => 'Original', 'body' => 'secret body']);
        $created = Activity::query()->sole();
        $this->assertSame($actor->id, $created->actor_id);
        $this->assertSame('created', $created->event);
        $this->assertSame('[redacted]', $created->changes['body']['after']);
        $page->update(['title' => 'Revised']);
        $updated = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame(['title' => ['before' => 'Original', 'after' => 'Revised']], $updated->changes);
        $page->save();
        $this->assertDatabaseCount('activities', 2);
        $page->update(['body' => 'another secret']);
        $this->assertSame(['body' => ['before' => '[redacted]', 'after' => '[redacted]']], Activity::query()->latest('id')->firstOrFail()->changes);
        $page->delete();
        $deleted = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame('deleted', $deleted->event);
        $this->assertSame('Revised', $deleted->changes['title']['before']);
        $this->assertStringNotContainsString('secret', Activity::all()->toJson());
    }

    public function test_settings_and_media_never_log_values_or_storage_paths(): void
    {
        $setting = ApplicationSetting::query()->create(['key' => 'example', 'value' => json_encode(['token' => 'secret-token'])]);
        $setting->update(['value' => json_encode(['password' => 'secret-password'])]);
        $media = Media::factory()->create(['path' => 'secret-path', 'alt_text' => 'secret-alt']);
        $media->update(['visibility' => 'public']);
        $activity = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame((string) $media->id, $activity->subject_id);
        $this->assertSame(['visibility' => ['before' => 'private', 'after' => 'public']], $activity->changes);
        $this->assertStringNotContainsString('secret', Activity::all()->toJson());
    }

    public function test_transaction_rollback_also_rolls_back_activity(): void
    {
        try {
            DB::transaction(function (): void {
                Page::factory()->create();
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_role_permission_only_changes_are_recorded_once_and_no_ops_are_ignored(): void
    {
        $role = app(CreateRole::class)->handle('Reviewer', [PagePolicy::VIEW]);
        app(UpdateRole::class)->handle($role, 'Reviewer', [PagePolicy::VIEW, PagePolicy::CREATE]);
        $updated = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame('updated', $updated->event);
        $this->assertSame([PagePolicy::VIEW], $updated->changes['permissions']['before']);
        $this->assertSame([PagePolicy::CREATE, PagePolicy::VIEW], $updated->changes['permissions']['after']);
        app(UpdateRole::class)->handle($role, 'Reviewer', [PagePolicy::CREATE, PagePolicy::VIEW]);
        $this->assertDatabaseCount('activities', 2);
        app(DeleteRole::class)->handle($role);
        $this->assertSame('deleted', Activity::query()->latest('id')->firstOrFail()->event);
    }

    public function test_user_history_is_preserved_and_actor_snapshots_survive_deletion(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $legacy = app(RecordUserManagementEvent::class)->handle($actor, $user, 'security_reset', 'Sensitive description', ['token' => 'secret']);
        $activity = Activity::query()->sole();
        $this->assertSame($actor->id, $activity->actor_id);
        $this->assertSame((string) $user->id, $activity->subject_id);
        $this->assertSame([], $activity->changes);
        $this->assertModelExists($legacy);
        $actor->delete();
        $this->assertNull($activity->refresh()->actor_id);
        $this->assertSame($actor->name, $activity->actor_name);
        $this->assertStringNotContainsString('secret', $activity->toJson());
    }

    public function test_disabled_module_preserves_existing_history_and_stops_new_records(): void
    {
        Page::factory()->create();
        config(['starter.features.activity' => false]);
        Page::factory()->create();
        $this->assertDatabaseCount('activities', 1);
        $user = User::factory()->create();
        $user->givePermissionTo(ActivityPolicy::VIEW);
        $this->actingAs($user)->get(route('activity.index'))->assertNotFound();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.can.viewActivity', false));
    }

    public function test_filters_pagination_and_date_validation(): void
    {
        $actor = User::factory()->create(['name' => 'Reviewer']);
        $actor->givePermissionTo(ActivityPolicy::VIEW);
        $this->actingAs($actor);
        Page::factory()->count(27)->create();
        $target = Activity::query()->firstOrFail();
        $this->get(route('activity.index'))->assertInertia(fn (Assert $page) => $page->has('activities.data', 25)->where('activities.total', 27));
        $this->get(route('activity.index', ['type' => 'pages', 'event' => 'created', 'actor' => 'Reviewer', 'subject_id' => $target->subject_id, 'from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('activities.data', 1)->where('activities.data.0.id', $target->id));
        $this->get(route('activity.index', ['to' => now()->toDateString()]))->assertOk();
        $this->get(route('activity.index', ['from' => 'invalid']))->assertSessionHasErrors('from');
        $this->get(route('activity.index', ['from' => '2026-09-06', 'to' => '2026-09-01']))->assertSessionHasErrors('to');
        $this->get(route('activity.index', ['type' => 'unknown']))->assertInertia(fn (Assert $page) => $page->has('activities.data', 0));
        $this->post(route('activity.index'))->assertStatus(405);
    }

    public function test_upload_failure_to_record_activity_rolls_back_the_media_and_cleans_up_files(): void
    {
        Storage::fake('media');
        $this->mock(RecordActivity::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Activity storage unavailable'));
        });
        try {
            app(UploadMedia::class)->handle(User::factory()->create(), UploadedFile::fake()->image('photo.png'));
            $this->fail('Expected the upload to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Activity storage unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('activities', 0);
        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_retention_is_opt_in_and_only_removes_expired_activity(): void
    {
        $activity = app(RecordActivity::class)->handle('pages', '1', 'created', [], null);
        $activity->forceFill(['created_at' => now()->subDays(40)])->save();
        app(RecordActivity::class)->handle('pages', '2', 'created', [], null);
        $this->assertSame(0, (new Activity)->prunable()->count());
        config(['activity.retention_days' => 30]);
        $this->artisan('model:prune', ['--model' => [Activity::class], '--pretend' => true])->assertSuccessful();
        $this->assertDatabaseCount('activities', 2);
        $this->artisan('model:prune', ['--model' => [Activity::class]])->assertSuccessful();
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }
}
