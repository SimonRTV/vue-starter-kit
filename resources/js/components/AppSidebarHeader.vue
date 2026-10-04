<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, SlidersHorizontal } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { home } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const page = usePage();
withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <header
        class="admin-topbar flex h-20 shrink-0 items-center justify-between gap-4 border-b px-5 md:px-8 lg:px-10"
    >
        <div class="flex min-w-0 items-center gap-4">
            <SidebarTrigger class="shrink-0" />
            <span
                class="bg-border hidden h-5 w-px sm:block"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <p
                    class="text-muted-foreground mb-1 hidden text-[9px] font-semibold tracking-[0.2em] uppercase sm:block"
                >
                    Administration
                </p>
                <Breadcrumbs
                    v-if="breadcrumbs.length"
                    :breadcrumbs="breadcrumbs"
                />
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <Button
                v-if="page.props.features.public_site"
                variant="ghost"
                size="sm"
                class="mr-2 hidden sm:inline-flex"
                as-child
            >
                <Link :href="home()"
                    >Voir le site <ArrowUpRight class="size-3.5"
                /></Link>
            </Button>
            <Button variant="outline" size="icon" class="rounded-full" as-child>
                <Link
                    :href="editAppearance()"
                    aria-label="Personnaliser l’apparence"
                    ><SlidersHorizontal class="size-4"
                /></Link>
            </Button>
            <NotificationBell
                v-if="
                    page.props.features.notifications &&
                    page.props.auth.user?.email_verified_at
                "
            />
        </div>
    </header>
</template>
