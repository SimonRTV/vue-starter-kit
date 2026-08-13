<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageController from '@/actions/App/Http/Controllers/PageController';
import PageForm from '@/components/pages/PageForm.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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
            <header class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    New page
                </h1>
                <p class="text-sm text-muted-foreground sm:text-base">
                    Add a page and choose whether to publish it immediately.
                </p>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>Page details</CardTitle>
                    <CardDescription>
                        Give the page a clear title, URL slug, and content.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <PageForm />
                </CardContent>
            </Card>
        </main>
    </div>
</template>
