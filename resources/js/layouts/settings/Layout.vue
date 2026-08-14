<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editApplicationLogo } from '@/routes/application-logo';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editSidebarFooterLinks } from '@/routes/sidebar-footer-links';
import type { NavItem } from '@/types';

const page = usePage();
const sidebarNavItems = computed<NavItem[]>(() => [
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
                  title: 'Application',
                  href: editApplicationLogo(),
              },
              {
                  title: 'Menu latéral',
                  href: editSidebarFooterLinks(),
              },
          ]
        : []),
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Paramètres"
            description="Gérez votre profil et les paramètres de votre compte"
        />

        <div class="flex flex-col lg:flex-row lg:gap-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col gap-1" aria-label="Paramètres">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="flex max-w-xl flex-col gap-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
