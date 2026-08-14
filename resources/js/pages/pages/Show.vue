<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Edit3 } from '@lucide/vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { PageHeader } from '@/components/application';
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
        return 'Non publiée';
    }

    return new Intl.DateTimeFormat('fr-CH', {
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
            <PageHeader :title="page.title">
                <template #badge>
                    <Badge
                        :variant="page.is_published ? 'default' : 'secondary'"
                    >
                        {{ page.is_published ? 'Publiée' : 'Brouillon' }}
                    </Badge>
                </template>
                <template #meta>
                    <p class="font-mono text-sm text-muted-foreground">
                        /{{ page.slug }}
                    </p>
                </template>
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="PageController.edit(page.id)">
                            <Edit3 data-icon="inline-start" />
                            Modifier la page
                        </Link>
                    </Button>
                    <DeletePageButton :page="page" />
                </template>
            </PageHeader>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <Card class="min-w-0">
                    <CardHeader>
                        <CardTitle>Contenu</CardTitle>
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
                            Cette page ne contient encore aucun contenu.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Détails</CardTitle>
                        <CardDescription>
                            Informations de publication et de modification.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 text-sm">
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">Statut</span>
                            <span class="font-medium">
                                {{
                                    page.is_published ? 'Publiée' : 'Brouillon'
                                }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground"
                                >Publication</span
                            >
                            <span class="font-medium">
                                {{ formatDate(page.published_at) }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">
                                Dernière modification
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
