import type { Pagination } from './pagination';

export type UserVerificationStatus = 'verified' | 'unverified';

export type UserVerificationFilter = UserVerificationStatus | null;

export type UserAccountStatus = 'active' | 'disabled';

export type UserAccountStatusFilter = UserAccountStatus | null;

export type UserSort =
    | 'name'
    | 'email'
    | 'email_verified_at'
    | 'disabled_at'
    | 'last_login_at'
    | 'created_at'
    | 'updated_at';

export type UserSortDirection = 'asc' | 'desc';

export type UserIndexFilters = {
    search: string | null;
    role: string | null;
    verification: UserVerificationFilter;
    status: UserAccountStatusFilter;
    sort: UserSort;
    direction: UserSortDirection;
    per_page: 10 | 25 | 50;
};

export type UserRoleOption = {
    id: number;
    name: string;
    can_assign: boolean;
};

export type ManagedUserAbilities = {
    update: boolean;
    delete: boolean;
    disable: boolean;
    enable: boolean;
    reset_password: boolean;
    reset_security: boolean;
};

export type ManagedUserSummary = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    disabled_at: string | null;
    invitation_sent_at: string | null;
    last_login_at: string | null;
    roles: string[];
    created_at: string | null;
    updated_at: string | null;
    can: ManagedUserAbilities;
};

export type UserManagementActivity = {
    id: number;
    action: string;
    description: string;
    actor_name: string | null;
    created_at: string | null;
};

export type ManagedUserDetail = ManagedUserSummary & {
    permissions: string[];
    can_manage_roles: boolean;
    activity: UserManagementActivity[];
};

export type ManagedUserPagination = Pagination<ManagedUserSummary>;

export type UserIndexAbilities = {
    create: boolean;
};
