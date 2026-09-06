<script setup lang="ts">
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';

const page = usePage();
usePoll(30000, { only: ['notifications'] });
</script>

<template>
    <Button variant="ghost" size="icon" class="relative" as-child>
        <Link
            :href="index()"
            :aria-label="`Notifications : ${page.props.notifications.unreadCount} non lues`"
        >
            <Bell class="size-5" />
            <span
                v-if="page.props.notifications.unreadCount > 0"
                class="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-5 font-semibold text-primary-foreground"
                aria-hidden="true"
            >
                {{
                    page.props.notifications.unreadCount > 99
                        ? '99+'
                        : page.props.notifications.unreadCount
                }}
            </span>
        </Link>
    </Button>
</template>
