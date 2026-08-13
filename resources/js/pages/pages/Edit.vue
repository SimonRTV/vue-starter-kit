<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageController from '@/actions/App/Http/Controllers/PageController';
import PageForm from '@/components/pages/PageForm.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
            <header class="flex min-w-0 flex-col gap-1">
                <h1
                    class="truncate text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Edit {{ page.title }}
                </h1>
                <p class="text-sm text-muted-foreground sm:text-base">
                    Update the page content, URL, or publication status.
                </p>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>Page details</CardTitle>
                    <CardDescription>
                        Changes are applied as soon as you save the form.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <PageForm :page="page" />
                </CardContent>
            </Card>
        </main>
    </div>
</template>
