import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isExternal?: boolean;
    isActive?: boolean;
};

export type SidebarFooterLink = {
    title: string;
    url: string;
};

export type FrontendNavigationChild = {
    label: string;
    url: string;
    description: string;
};

export type FrontendNavigationItem = {
    type: 'link' | 'group';
    label: string;
    url: string | null;
    children: FrontendNavigationChild[];
};
