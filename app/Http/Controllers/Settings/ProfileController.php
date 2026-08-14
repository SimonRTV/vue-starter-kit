<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private UpdateUser $updateUser,
        private DeleteUser $deleteUser,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $email = Arr::string($validated, 'email');
        $roleNames = $user->getRoleNames()
            ->map(static fn (mixed $roleName): string => (string) $roleName)
            ->values()
            ->all();

        $this->updateUser->handle(
            $user,
            [
                'name' => Arr::string($validated, 'name'),
                'email' => $email,
                'email_verified_at' => $email === $user->email
                    ? $user->email_verified_at
                    : null,
            ],
            array_values($roleNames),
            $user,
            verificationErrorKey: 'email',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->deleteUser->handle($user, $user);
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
