<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { PageHeader } from '@/components/application';
import PageForm from '@/components/pages/PageForm.vue';
import type { PageDetail } from '@/types';

const props = defineProps<{
    page: PageDetail;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Pages',
            href: PageController.index(),
        },
        {
            title: props.page.title,
            href: PageController.show(props.page.id),
        },
        {
            title: 'Edit',
            href: PageController.edit(props.page.id),
        },
    ],
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head :title="'Edit ' + page.title" />

        <main
            class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                :title="'Edit ' + page.title"
                description="Update the page content, URL, or publication status."
            />

            <PageForm :page="page" />
        </main>
    </div>
</template>
