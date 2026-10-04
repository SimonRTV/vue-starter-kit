<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup
        v-if="items.length"
        class="px-3 py-2 group-data-[collapsible=icon]:px-2"
    >
        <SidebarGroupLabel
            class="mb-3 text-[10px] font-semibold tracking-[0.18em] uppercase"
            >{{ label ?? 'Espace de travail' }}</SidebarGroupLabel
        >
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="
                        item.isActive ?? isCurrentOrParentUrl(item.href)
                    "
                    :tooltip="item.title"
                    class="admin-nav-link h-9 rounded-lg px-3 py-1.5"
                >
                    <Link
                        :href="item.href"
                        :aria-current="
                            (item.isActive ?? isCurrentOrParentUrl(item.href))
                                ? 'page'
                                : undefined
                        "
                    >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
