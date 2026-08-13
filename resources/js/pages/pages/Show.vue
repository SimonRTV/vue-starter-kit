<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Edit3 } from '@lucide/vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import DeletePageButton from '@/components/pages/DeletePageButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { PageDetail } from '@/types';

const props = defineProps<{
    page: PageDetail;
}>();

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not published';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(value));
}

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
    ],
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head :title="page.title" />

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex min-w-0 flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="truncate text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            {{ page.title }}
                        </h1>
                        <Badge
                            :variant="
                                page.is_published ? 'default' : 'secondary'
                            "
                        >
                            {{ page.is_published ? 'Published' : 'Draft' }}
                        </Badge>
                    </div>
                    <p class="font-mono text-sm text-muted-foreground">
                        /{{ page.slug }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="PageController.edit(page.id)">
                            <Edit3 data-icon="inline-start" />
                            Edit page
                        </Link>
                    </Button>
                    <DeletePageButton :page="page" />
                </div>
            </header>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <Card class="min-w-0">
                    <CardHeader>
                        <CardTitle>Content</CardTitle>
                        <CardDescription v-if="page.excerpt">
                            {{ page.excerpt }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="page.body"
                            class="text-sm leading-7 whitespace-pre-wrap"
                        >
                            {{ page.body }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            This page does not have any content yet.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                        <CardDescription>
                            Publication and editing information.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 text-sm">
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">Status</span>
                            <span class="font-medium">
                                {{ page.is_published ? 'Published' : 'Draft' }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">Published</span>
                            <span class="font-medium">
                                {{ formatDate(page.published_at) }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">
                                Last updated
                            </span>
                            <span class="font-medium">
                                {{ formatDate(page.updated_at) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </div>
</template>
