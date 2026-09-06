<?php

namespace Tests\Feature;

use App\Actions\Notifications\SendNotification;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\RecordUserManagementEvent;
use App\Actions\Users\SendUserPasswordSetupLink;
use App\Models\User;
use App\Notifications\InboxEmail;
use App\Notifications\UserPasswordSetup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
    }

    public function test_inbox_requires_authentication_and_verification(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get(route('notifications.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create())->get(route('notifications.index'))->assertOk();
    }

    public function test_inbox_and_counts_are_private_and_paginated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        for ($i = 0; $i < 22; $i++) {
            $this->send($user);
        }
        $this->send($other, 'Other user secret');
        $this->actingAs($user)->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')->has('items.data', 20)->where('items.total', 22)->where('notifications.unreadCount', 22));
        $this->get(route('notifications.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('items.data', 2));
        Queue::assertNothingPushed();
    }

    public function test_read_unread_and_delete_only_apply_to_the_recipient(): void
    {
        $user = User::factory()->create();
        $own = $this->send($user);
        $foreign = $this->send(User::factory()->create());
        $this->actingAs($user)->patch(route('notifications.update', $foreign->id), ['read' => true])->assertNotFound();
        $this->delete(route('notifications.destroy', $foreign->id))->assertNotFound();
        $this->patch(route('notifications.update', $own->id), ['read' => true])->assertRedirect();
        $this->assertNotNull($own->refresh()->read_at);
        $this->patch(route('notifications.update', $own->id), ['read' => false])->assertRedirect();
        $this->assertNull($own->refresh()->read_at);
        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertNotNull($own->refresh()->read_at);
        $this->assertNull($foreign->refresh()->read_at);
        $this->delete(route('notifications.destroy', $own->id))->assertRedirect();
        $this->assertModelMissing($own);
        $this->assertModelExists($foreign);
    }

    public function test_status_and_category_filters_and_invalid_values(): void
    {
        $user = User::factory()->create();
        $this->send($user)->markAsRead();
        app(SendNotification::class)->handle($user, 'account', 'Account', 'Message');
        $this->actingAs($user)->get(route('notifications.index', ['status' => 'unread', 'category' => 'account']))
            ->assertInertia(fn (Assert $page) => $page->where('items.total', 1)->where('items.data.0.data.category', 'account'));
        $this->get(route('notifications.index', ['status' => 'read']))->assertInertia(fn (Assert $page) => $page->where('items.total', 1));
        $this->get(route('notifications.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
        $this->get(route('notifications.index', ['category' => 'invalid']))->assertSessionHasErrors('category');
        $this->patch(route('notifications.update', $user->notifications()->firstOrFail()->id), ['read' => 'invalid'])->assertSessionHasErrors('read');
    }

    public function test_preferences_only_change_current_user_and_default_to_no_optional_email(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->get(route('notifications.preferences'))->assertInertia(fn (Assert $page) => $page
            ->where('email', ['account' => false, 'updates' => false]));
        $this->put(route('notifications.preferences.update'), ['email' => ['account' => true, 'updates' => false], 'user_id' => $other->id])->assertRedirect();
        $this->assertSame(['account' => true, 'updates' => false], $user->refresh()->notification_preferences);
        $this->assertNull($other->refresh()->notification_preferences);
        $this->assertArrayNotHasKey('notification_preferences', $user->toArray());
        $this->put(route('notifications.preferences.update'), ['email' => ['unknown' => true]])->assertSessionHasErrors('email');
    }

    public function test_email_is_queued_after_commit_and_preferences_are_checked_again_before_delivery(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['notification_preferences' => ['updates' => true]])->save();
        $notification = $this->send($user);
        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof InboxEmail
            && $job->notification->notificationId === $notification->id && $job->notification->afterCommit === true);
        $mail = new InboxEmail($notification->id, 'updates');
        $this->assertTrue($mail->shouldSend($user, 'mail'));
        $this->assertSame(3, $mail->tries);
        $this->assertSame(route('notifications.index'), $mail->toMail($user)->actionUrl);
        $user->forceFill(['notification_preferences' => ['updates' => false]])->save();
        $this->assertFalse($mail->shouldSend($user, 'mail'));
        $user->forceFill(['notification_preferences' => ['updates' => true], 'disabled_at' => now()])->save();
        $this->assertFalse($mail->shouldSend($user, 'mail'));
        $user->forceFill(['disabled_at' => null, 'email_verified_at' => null])->save();
        $this->assertFalse($mail->shouldSend($user, 'mail'));
        $user->forceFill(['email_verified_at' => now()])->save();
        $notification->delete();
        $this->assertFalse($mail->shouldSend($user, 'mail'));
    }

    public function test_rollback_removes_inbox_entry(): void
    {
        $user = User::factory()->create();
        try {
            DB::transaction(function () use ($user): void {
                $this->send($user);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_module_can_be_disabled_without_removing_history_or_account_emails(): void
    {
        $user = User::factory()->create();
        $this->send($user);
        config(['starter.features.notifications' => false]);
        $this->assertNull(app(SendNotification::class)->handle($user, 'updates', 'Hidden', 'Message'));
        $this->actingAs($user)->get(route('notifications.index'))->assertNotFound();
        $this->get(route('notifications.preferences'))->assertNotFound();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('notifications.unreadCount', 0));
        $this->assertDatabaseCount('notifications', 1);
        Notification::fake();
        app(SendUserPasswordSetupLink::class)->handle($user, invitation: true);
        Notification::assertSentTo($user, UserPasswordSetup::class);
    }

    public function test_account_events_create_safe_recipient_notifications_and_keep_history(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        app(RecordUserManagementEvent::class)->handle($actor, $user, 'security_reset', 'secret description', ['token' => 'secret']);
        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(0, $actor->notifications()->count());
        $this->assertStringNotContainsString('secret', $user->notifications()->firstOrFail()->toJson());
        $this->assertDatabaseCount('user_management_events', 1);
        app(DeleteUser::class)->handle($user, $actor);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_actions_reject_external_and_script_urls(): void
    {
        $user = User::factory()->create();
        foreach (['https://example.com', '//example.com', '/\\example.com', 'javascript:alert(1)', "/\nexample.com"] as $path) {
            try {
                app(SendNotification::class)->handle($user, 'updates', 'Title', 'Body', $path);
                $this->fail('An unsafe path was accepted.');
            } catch (InvalidArgumentException) {
            }
        }
        $this->assertDatabaseCount('notifications', 0);
        $item = app(SendNotification::class)->handle($user, 'updates', 'Title', 'Body', '/dashboard');
        $this->assertSame('/dashboard', $item->data['action_path']);
    }

    private function send(User $user, string $title = 'Update'): DatabaseNotification
    {
        $notification = app(SendNotification::class)->handle($user, 'updates', $title, 'Your operation is complete.', '/dashboard');
        $this->assertInstanceOf(DatabaseNotification::class, $notification);

        return $notification;
    }
}
