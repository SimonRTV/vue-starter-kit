<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    History,
    ExternalLink,
    FileText,
    LayoutGrid,
    Images,
    Settings2,
    Search,
    ShieldCheck,
    Users,
    X,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import { index as activityIndex } from '@/actions/App/Http/Controllers/ActivityController';
import { index as mediaIndex } from '@/actions/App/Http/Controllers/MediaController';
import PageController from '@/actions/App/Http/Controllers/PageController';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import UserController from '@/actions/App/Http/Controllers/UserController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editSeo } from '@/routes/seo';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

const page = usePage();
const { isMobile, setOpenMobile } = useSidebar();
watch(
    () => page.url,
    () => setOpenMobile(false),
);
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Tableau de bord',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(page.props.auth.can.viewActivity
        ? [
              {
                  title: 'Journal d’activité',
                  href: activityIndex(),
                  icon: History,
              },
          ]
        : []),
    ...(page.props.auth.can.manageMedia
        ? [{ title: 'Médiathèque', href: mediaIndex(), icon: Images }]
        : []),
    ...page.props.navigation.resources.map((resource) => ({
        title: resource.title,
        href: resource.url,
        icon: FileText,
    })),
    ...(page.props.auth.can.managePages
        ? [
              {
                  title: 'Pages',
                  href: PageController.index(),
                  icon: FileText,
              },
          ]
        : []),
    ...(page.props.features.public_site &&
    page.props.auth.can.manageApplicationSettings
        ? [{ title: 'Référencement SEO', href: editSeo(), icon: Search }]
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

const workspaceItems = computed(() =>
    mainNavItems.value.filter(
        (item) => item.icon !== Users && item.icon !== ShieldCheck,
    ),
);
const managementItems = computed(() =>
    mainNavItems.value.filter(
        (item) => item.icon === Users || item.icon === ShieldCheck,
    ),
);
const settingsItems = computed<NavItem[]>(() => [
    {
        title: 'Paramètres',
        href: editProfile(),
        icon: Settings2,
        isActive: page.component.startsWith('settings/'),
    },
]);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="admin-navigation border-0"
    >
        <div class="admin-sidebar-body flex h-full flex-col">
            <SidebarHeader
                class="relative px-3 pt-6 pb-2 group-data-[collapsible=icon]:px-2"
            >
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            class="admin-brand h-14"
                            as-child
                        >
                            <Link :href="dashboard()">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <Button
                    v-if="isMobile"
                    variant="ghost"
                    size="icon"
                    class="text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-foreground absolute top-8 right-4"
                    aria-label="Fermer la navigation"
                    @click="setOpenMobile(false)"
                    ><X class="size-4"
                /></Button>
            </SidebarHeader>

            <SidebarContent class="gap-2">
                <NavMain :items="workspaceItems" label="Espace de travail" />
                <NavMain :items="managementItems" label="Administration" />
                <NavMain :items="settingsItems" label="Préférences" />
            </SidebarContent>

            <SidebarFooter
                class="gap-4 px-3 pb-4 group-data-[collapsible=icon]:px-2"
            >
                <NavFooter :items="footerNavItems" />
                <NavUser />
            </SidebarFooter>
        </div>
    </Sidebar>
</template>
