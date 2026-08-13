<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import { computed } from 'vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import PageDataTable from '@/components/pages/PageDataTable.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import type { PageIndexFilters, PagePagination } from '@/types';

const props = defineProps<{
    pages: PagePagination;
    filters: PageIndexFilters;
}>();

const shouldShowTable = computed(
    () =>
        props.pages.total > 0 ||
        props.filters.search !== null ||
        props.filters.status !== null,
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pages',
                href: PageController.index(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head title="Pages" />

        <main
            class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex min-w-0 flex-col gap-1">
                    <h1
                        class="text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        Pages
                    </h1>
                    <p class="text-sm text-muted-foreground sm:text-base">
                        Create, publish, and maintain your site content.
                    </p>
                </div>
                <Button as-child>
                    <Link :href="PageController.create()">
                        <Plus data-icon="inline-start" />
                        New page
                    </Link>
                </Button>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>All pages</CardTitle>
                    <CardDescription>
                        Search, filter, sort, and manage every page.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <PageDataTable
                        v-if="shouldShowTable"
                        :pages="pages"
                        :filters="filters"
                    />
                    <Empty v-else>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FileText />
                            </EmptyMedia>
                            <EmptyTitle>No pages yet</EmptyTitle>
                            <EmptyDescription>
                                Create your first page to start building your
                                content library.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button as-child>
                                <Link :href="PageController.create()">
                                    <Plus data-icon="inline-start" />
                                    Create page
                                </Link>
                            </Button>
                        </EmptyContent>
                    </Empty>
                </CardContent>
            </Card>
        </main>
    </div>
</template>
