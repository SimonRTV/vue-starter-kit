import type { Pagination } from './pagination';

export type RoleAssignmentStatus = 'assigned' | 'unused';

export type RoleAssignmentFilter = RoleAssignmentStatus | null;

export type RoleSort =
    'name' | 'users_count' | 'permissions_count' | 'created_at' | 'updated_at';

export type RoleSortDirection = 'asc' | 'desc';

export type RoleIndexFilters = {
    search: string | null;
    assignment: RoleAssignmentFilter;
    sort: RoleSort;
    direction: RoleSortDirection;
    per_page: 10 | 25 | 50;
};

export type RolePermissionOption = {
    id: number;
    name: string;
    label: string;
    description: string;
    group: string;
    group_label: string;
    is_sensitive: boolean;
    is_orphaned: boolean;
};

export type ManagedRoleAbilities = {
    update: boolean;
    delete: boolean;
};

export type ManagedRoleSummary = {
    id: number;
    name: string;
    users_count: number;
    permissions_count: number;
    is_protected: boolean;
    created_at: string | null;
    updated_at: string | null;
    can: ManagedRoleAbilities;
};

export type RoleAssignedUser = {
    id: number;
    name: string;
    email: string;
};

export type ManagedRoleDetail = ManagedRoleSummary & {
    permissions: string[];
    assigned_users: RoleAssignedUser[];
    can_view_assigned_users: boolean;
    assigned_to_current_user: boolean;
};

export type ManagedRolePagination = Pagination<ManagedRoleSummary>;

export type RoleIndexAbilities = {
    create: boolean;
};
