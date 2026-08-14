<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ExternalLink,
    FileText,
    LayoutGrid,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import UserController from '@/actions/App/Http/Controllers/UserController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Tableau de bord',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(page.props.auth.can.managePages
        ? [
              {
                  title: 'Pages',
                  href: PageController.index(),
                  icon: FileText,
              },
          ]
        : []),
    ...(page.props.auth.can.manageUsers
        ? [
              {
                  title: 'Utilisateurs',
                  href: UserController.index(),
                  icon: Users,
              },
          ]
        : []),
    ...(page.props.auth.can.manageRoles
        ? [
              {
                  title: 'Rôles',
                  href: RoleController.index(),
                  icon: ShieldCheck,
              },
          ]
        : []),
]);

const footerNavItems = computed<NavItem[]>(() =>
    page.props.navigation.sidebarFooterLinks.map((link) => {
        const isExternal = /^https?:\/\//i.test(link.url);

        return {
            title: link.title,
            href: link.url,
            icon: isExternal ? ExternalLink : undefined,
            isExternal,
        };
    }),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
