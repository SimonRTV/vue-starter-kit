<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
                title: 'New page',
                href: PageController.create(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head title="New page" />

        <main
            class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                title="New page"
                description="Add a page and choose whether to publish it immediately."
            />

            <PageForm />
        </main>
    </div>
</template>
