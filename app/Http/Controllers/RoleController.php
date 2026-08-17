<?php

namespace App\Http\Controllers;

use App\Actions\Permissions\DiscoverPolicyPermissions;
use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\ListRoles;
use App\Actions\Roles\UpdateRole;
use App\Http\Requests\IndexRoleRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private DiscoverPolicyPermissions $discoverPolicyPermissions,
        private ListRoles $listRoles,
        private CreateRole $createRole,
        private UpdateRole $updateRole,
        private DeleteRole $deleteRole,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexRoleRequest $request): Response|RedirectResponse
    {
        $filters = $request->filters();

        /** @var User $actor */
        $actor = $request->user();
        $roles = $this->listRoles
            ->handle($filters)
            ->through(fn (Role $role): array => $this->summary($role, $actor));

        if ($roles->currentPage() > $roles->lastPage()) {
            return to_route('roles.index', $request->canonicalQuery($roles->lastPage()));
        }

        Head::title('Rôles');

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'filters' => $filters,
            'abilities' => [
                'create' => Gate::allows('create', Role::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        Gate::authorize('create', Role::class);
        Head::title('Nouveau rôle');

        return Inertia::render('roles/Create', [
            'permissions' => $this->permissionOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = $this->createRole->handle(
            $request->roleName(),
            $request->permissionNames(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.show', $role);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Role $role): Response
    {
        Gate::authorize('view', $role);

        /** @var User $actor */
        $actor = $request->user();
        $role->loadMissing('permissions:id,name,guard_name')
            ->loadCount(['permissions', 'users']);
        Head::title($role->name);

        return Inertia::render('roles/Show', [
            'role' => $this->detail($role, $actor),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize('update', $role);

        /** @var User $actor */
        $actor = $request->user();
        $role->loadMissing('permissions:id,name,guard_name')
            ->loadCount(['permissions', 'users']);
        Head::title('Modifier '.$role->name);

        return Inertia::render('roles/Edit', [
            'role' => $this->detail($role, $actor),
            'permissions' => $this->permissionOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->updateRole->handle(
            $role,
            $request->roleName(),
            $request->permissionNames(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.show', $role);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->deleteRole->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    /**
     * @return array{id: int, name: string, users_count: int, permissions_count: int, is_protected: bool, created_at: string|null, updated_at: string|null, can: array{update: bool, delete: bool}}
     */
    private function summary(Role $role, User $actor): array
    {
        return [
            'id' => (int) $role->id,
            'name' => $role->name,
            'users_count' => (int) $role->getAttribute('users_count'),
            'permissions_count' => (int) $role->getAttribute('permissions_count'),
            'is_protected' => RolePolicy::isProtected($role),
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
            'can' => [
                'update' => $actor->can('update', $role),
                'delete' => $actor->can('delete', $role),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, users_count: int, permissions_count: int, is_protected: bool, created_at: string|null, updated_at: string|null, can: array{update: bool, delete: bool}, permissions: list<string>, assigned_users: list<array{id: int, name: string, email: string}>, can_view_assigned_users: bool, assigned_to_current_user: bool}
     */
    private function detail(Role $role, User $actor): array
    {
        $permissionNames = [];

        foreach ($role->permissions as $permission) {
            $permissionNames[] = $permission->name;
        }

        $canViewAssignedUsers = $actor->can(UserPolicy::VIEW);
        $assignedUsers = $canViewAssignedUsers
            ? User::query()
                ->select(['id', 'name', 'email'])
                ->role($role)
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->map(static fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all()
            : [];

        return [
            ...$this->summary($role, $actor),
            'permissions' => array_values(collect($permissionNames)->sort()->all()),
            'assigned_users' => array_values($assignedUsers),
            'can_view_assigned_users' => $canViewAssignedUsers,
            'assigned_to_current_user' => $actor->hasRole($role),
        ];
    }

    /**
     * @return list<array{id: int, name: string, label: string, description: string, group: string, group_label: string, is_sensitive: bool, is_orphaned: bool}>
     */
    private function permissionOptions(): array
    {
        $catalog = $this->discoverPolicyPermissions->handle();

        return array_values(Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Permission $permission) use ($catalog): array {
                $definition = $catalog->find($permission->name);
                $group = Str::before($permission->name, '.');
                $group = $group === $permission->name ? 'other' : $group;

                return [
                    'id' => (int) $permission->id,
                    'name' => $permission->name,
                    'label' => $definition['label']
                        ?? __(Str::of($permission->name)
                            ->afterLast('.')
                            ->replace('_', ' ')
                            ->headline()
                            ->toString()),
                    'description' => $definition['description']
                        ?? __('This permission is not declared by any application policy.'),
                    'group' => $definition['group'] ?? $group,
                    'group_label' => $definition['group_label']
                        ?? __(Str::of($group)->replace('_', ' ')->headline()->toString()),
                    'is_sensitive' => $definition['sensitive'] ?? false,
                    'is_orphaned' => $definition === null,
                ];
            })
            ->all());
    }
}
