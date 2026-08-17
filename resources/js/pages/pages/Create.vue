<script setup lang="ts">
import PageController from '@/actions/App/Http/Controllers/PageController';
import { PageHeader } from '@/components/application';
import PageForm from '@/components/pages/PageForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pages',
                href: PageController.index(),
            },
            {
                title: 'Nouvelle page',
                href: PageController.create(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <main
            class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                title="Nouvelle page"
                description="Ajoutez une page et choisissez de la publier immédiatement ou non."
            />

            <PageForm />
        </main>
    </div>
</template>
