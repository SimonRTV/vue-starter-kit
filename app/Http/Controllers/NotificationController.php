<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\NotificationPreferences;
use App\Http\Requests\IndexNotificationsRequest;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;

class NotificationController extends Controller
{
    public function index(IndexNotificationsRequest $request): Response
    {
        $query = $request->user()->notifications()->where('type', 'workspace');
        $status = $request->validated('status') ?? 'all';
        $category = $request->validated('category');
        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }
        if ($category !== null) {
            $query->where('data->category', $category);
        }
        Head::title('Notifications');

        return Inertia::render('notifications/Index', [
            'items' => $query->reorder()->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => ['status' => $status, 'category' => $category],
            'categories' => config('notifications.categories'),
        ]);
    }

    public function update(UpdateNotificationRequest $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->where('type', 'workspace')->whereKey($notification)->firstOrFail();
        if ($request->boolean('read')) {
            $item->markAsRead();
        } else {
            $item->markAsUnread();
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->where('type', 'workspace')->update(['read_at' => now()]);

        return back();
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->where('type', 'workspace')->whereKey($notification)->firstOrFail()->delete();

        return back();
    }

    public function preferences(Request $request, NotificationPreferences $preferences): Response
    {
        /** @var User $user */
        $user = $request->user();
        Head::title('Préférences de notification');

        return Inertia::render('settings/Notifications', [
            'email' => $preferences->get($user),
            'categories' => config('notifications.categories'),
        ]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        /** @var array<string, bool|int|string> $email */
        $email = $request->validated('email');
        $request->user()->forceFill(['notification_preferences' => array_map(fn (mixed $value): bool => (bool) $value, $email)])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vos préférences ont été enregistrées.']);

        return back();
    }
}
