<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { preferences } from '@/actions/App/Http/Controllers/NotificationController';
import { ArrowUpRight, Settings2 } from '@lucide/vue';
import { PageHeader } from '@/components/application';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editApplicationLogo } from '@/routes/application-logo';
import { edit as editFrontendNavigation } from '@/routes/frontend-navigation';
import { edit as editGeneral } from '@/routes/general-settings';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editSidebarFooterLinks } from '@/routes/sidebar-footer-links';
import type { NavItem } from '@/types';

const page = usePage();
const sidebarNavItems = computed<NavItem[]>(() => [
    ...(page.props.features.notifications
        ? [{ title: 'Notifications', href: preferences() }]
        : []),
    {
        title: 'Profil',
        href: editProfile(),
    },
    {
        title: 'Sécurité',
        href: editSecurity(),
    },
    {
        title: 'Apparence',
        href: editAppearance(),
    },
    ...(page.props.auth.can.manageApplicationSettings
        ? [
              {
                  title: 'Informations générales',
                  href: editGeneral(),
              },
              {
                  title: 'Application',
                  href: editApplicationLogo(),
              },
              {
                  title: 'Menu latéral',
                  href: editSidebarFooterLinks(),
              },
              ...(page.props.features.public_site
                  ? [
                        {
                            title: 'Navigation publique',
                            href: editFrontendNavigation(),
                        },
                    ]
                  : []),
          ]
        : []),
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div
        class="admin-page mx-auto flex w-full max-w-[1400px] flex-col gap-8 p-5 md:p-8 lg:p-10"
    >
        <PageHeader
            title="Paramètres"
            description="Un espace qui s’adapte à vous. Gérez votre compte et personnalisez votre application."
        />
        <div
            class="grid items-start gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-10"
        >
            <aside class="min-w-0">
                <div class="mb-4 hidden items-center gap-2 px-3 lg:flex">
                    <Settings2 class="text-primary size-4" />
                    <p
                        class="text-muted-foreground text-xs font-semibold tracking-[0.18em] uppercase"
                    >
                        À votre mesure
                    </p>
                </div>
                <nav
                    class="grid grid-cols-2 gap-1 sm:grid-cols-3 lg:flex lg:flex-col"
                    aria-label="Paramètres"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        class="admin-settings-link h-auto min-h-11 w-full justify-between px-3 py-3 text-left text-sm whitespace-normal"
                        as-child
                    >
                        <Link
                            :href="item.href"
                            :aria-current="
                                isCurrentOrParentUrl(item.href)
                                    ? 'page'
                                    : undefined
                            "
                        >
                            {{ item.title }}
                            <ArrowUpRight
                                class="admin-settings-indicator size-4 shrink-0 opacity-0"
                            />
                        </Link>
                    </Button>
                </nav>
            </aside>
            <div class="admin-settings-panel">
                <section class="flex min-w-0 flex-col gap-8"><slot /></section>
            </div>
        </div>
    </div>
</template>
