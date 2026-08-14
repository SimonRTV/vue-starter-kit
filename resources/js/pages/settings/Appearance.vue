<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminThemeSelector from '@/components/AdminThemeSelector.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Paramètres d’apparence',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Paramètres d’apparence" />

    <h1 class="sr-only">Paramètres d’apparence</h1>

    <div class="flex flex-col gap-8">
        <Heading
            variant="small"
            title="Paramètres d’apparence"
            description="Personnalisez l’apparence de votre compte"
        />
        <AppearanceTabs />
        <AdminThemeSelector />
    </div>
</template>
