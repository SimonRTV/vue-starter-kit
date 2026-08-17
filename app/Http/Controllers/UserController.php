<?php

namespace App\Http\Controllers;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\DisableUser;
use App\Actions\Users\EnableUser;
use App\Actions\Users\ListUsers;
use App\Actions\Users\ResetUserSecurity;
use App\Actions\Users\SendUserPasswordSetupLink;
use App\Actions\Users\UpdateUser;
use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\UserManagementEvent;
use App\Policies\UserPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private ListUsers $listUsers,
        private CreateUser $createUser,
        private UpdateUser $updateUser,
        private DeleteUser $deleteUser,
        private DisableUser $disableUser,
        private EnableUser $enableUser,
        private SendUserPasswordSetupLink $sendPasswordSetupLink,
        private ResetUserSecurity $resetUserSecurity,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexUserRequest $request): Response|RedirectResponse
    {
        $filters = $request->filters();

        /** @var User $actor */
        $actor = $request->user();
        $users = $this->listUsers
            ->handle($filters)
            ->through(fn (User $user): array => $this->summary($user, $actor));

        if ($users->currentPage() > $users->lastPage()) {
            return to_route('users.index', $request->canonicalQuery($users->lastPage()));
        }

        Head::title('Utilisateurs');

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->roleOptions($actor),
            'abilities' => [
                'create' => Gate::allows('create', User::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        /** @var User $actor */
        $actor = $request->user();
        Head::title('Nouvel utilisateur');

        return Inertia::render('users/Create', [
            'roles' => $this->roleOptions($actor),
            'canManageVerification' => $actor->can(UserPolicy::VERIFY_EMAIL),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->createUser->handle(
            $request->userAttributes(),
            $request->roleNames(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created and invitation sent.')]);

        return to_route('users.show', $user);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);

        /** @var User $actor */
        $actor = $request->user();
        $user->loadMissing(['permissions:id,name,guard_name', 'roles.permissions:id,name,guard_name']);
        Head::title($user->name);

        return Inertia::render('users/Show', [
            'user' => $this->detail($user, $actor),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();
        $user->loadMissing(['permissions:id,name,guard_name', 'roles.permissions:id,name,guard_name']);
        Head::title('Modifier '.$user->name);

        return Inertia::render('users/Edit', [
            'user' => $this->detail($user, $actor),
            'roles' => $this->roleOptions($actor),
            'canManageVerification' => $actor->can(UserPolicy::VERIFY_EMAIL),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->updateUser->handle(
            $user,
            $request->userAttributes(),
            $request->roleNames(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.show', $user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->deleteUser->handle($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User permanently deleted.')]);

        return to_route('users.index');
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('disable', $user);

        $this->disableUser->handle($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User disabled and sessions revoked.')]);

        return back();
    }

    public function enable(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('enable', $user);

        $this->enableUser->handle($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User re-enabled.')]);

        return back();
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $this->sendPasswordSetupLink->handle($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password reset link sent.')]);

        return back();
    }

    public function resetSecurity(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('resetSecurity', $user);

        $this->resetUserSecurity->handle($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Security reset and password reset link sent.')]);

        return back();
    }

    /**
     * @return array{id: int, name: string, email: string, email_verified_at: string|null, disabled_at: string|null, invitation_sent_at: string|null, last_login_at: string|null, roles: list<string>, created_at: string|null, updated_at: string|null, can: array{update: bool, delete: bool, disable: bool, enable: bool, reset_password: bool, reset_security: bool}}
     */
    private function summary(User $user, User $actor): array
    {
        $roleNames = [];

        foreach ($user->roles as $role) {
            if ($role instanceof Role) {
                $roleNames[] = $role->name;
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'disabled_at' => $user->disabled_at?->toISOString(),
            'invitation_sent_at' => $user->invitation_sent_at?->toISOString(),
            'last_login_at' => $user->last_login_at?->toISOString(),
            'roles' => array_values(collect($roleNames)->sort()->all()),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'can' => [
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
                'disable' => $actor->can('disable', $user),
                'enable' => $actor->can('enable', $user),
                'reset_password' => $actor->can('resetPassword', $user),
                'reset_security' => $actor->can('resetSecurity', $user),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, email_verified_at: string|null, disabled_at: string|null, invitation_sent_at: string|null, last_login_at: string|null, roles: list<string>, permissions: list<string>, created_at: string|null, updated_at: string|null, can_manage_roles: bool, activity: list<array{id: int, action: string, description: string, actor_name: string|null, created_at: string|null}>, can: array{update: bool, delete: bool, disable: bool, enable: bool, reset_password: bool, reset_security: bool}}
     */
    private function detail(User $user, User $actor): array
    {
        $permissionNames = [];

        foreach ($user->getAllPermissions() as $permission) {
            if ($permission instanceof Permission) {
                $permissionNames[] = $permission->name;
            }
        }

        $activity = UserManagementEvent::query()
            ->where('user_id', $user->getKey())
            ->latest()
            ->limit(20)
            ->get(['id', 'action', 'description', 'actor_name', 'created_at'])
            ->map(static fn (UserManagementEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'description' => $event->description,
                'actor_name' => $event->actor_name,
                'created_at' => $event->created_at?->toISOString(),
            ])
            ->all();

        return [
            ...$this->summary($user, $actor),
            'permissions' => array_values(collect($permissionNames)->sort()->all()),
            'can_manage_roles' => ! $actor->is($user)
                && $actor->can(UserPolicy::ASSIGN_ROLES),
            'activity' => array_values($activity),
        ];
    }

    /**
     * @return list<array{id: int, name: string, can_assign: bool}>
     */
    private function roleOptions(User $actor): array
    {
        $actor->loadMissing([
            'permissions:id,name,guard_name',
            'roles.permissions:id,name,guard_name',
        ]);

        return array_values(Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->with('permissions:id,name,guard_name')
            ->get(['id', 'name', 'guard_name'])
            ->map(static fn (Role $role): array => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'can_assign' => $actor->can('assign', $role),
            ])
            ->all());
    }
}
