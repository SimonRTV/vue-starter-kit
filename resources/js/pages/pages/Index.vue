<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { PageHeader } from '@/components/application';
import PageDataTable from '@/components/pages/PageDataTable.vue';
import { Button } from '@/components/ui/button';
import type { PageIndexFilters, PagePagination } from '@/types';

defineProps<{
    pages: PagePagination;
    filters: PageIndexFilters;
}>();

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
        <main
            class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                title="Pages"
                description="Créez, publiez et maintenez le contenu de votre site."
            >
                <template #actions>
                    <Button as-child>
                        <Link :href="PageController.create()">
                            <Plus data-icon="inline-start" />
                            Nouvelle page
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <PageDataTable :pages="pages" :filters="filters" />
        </main>
    </div>
</template>
