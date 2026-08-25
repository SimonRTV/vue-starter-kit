<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoFull from '@/components/AppLogoFull.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;
const hasFullLogo = computed(
    () =>
        page.props.branding.fullLogoUrl !== null ||
        page.props.branding.darkFullLogoUrl !== null,
);

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col bg-muted p-10 text-white lg:flex dark:border-r"
        >
            <div class="absolute inset-0 bg-zinc-900" />
            <Link
                :href="home()"
                class="relative z-20 flex items-center gap-2 text-lg font-medium"
            >
                <AppLogoFull
                    background="dark"
                    class="h-8 w-auto max-w-56 fill-current text-white"
                />
                <span v-if="!hasFullLogo">{{ name }}</span>
            </Link>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
