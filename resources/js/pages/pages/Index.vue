<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import { create as importPages } from '@/actions/App/Http/Controllers/CsvImportController';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { index as templatesIndex } from '@/actions/App/Http/Controllers/PageTemplateController';
import { PageHeader } from '@/components/application';
import PageDataTable from '@/components/pages/PageDataTable.vue';
import { Button } from '@/components/ui/button';
import type { PageIndexFilters, PagePagination } from '@/types';

defineProps<{
    pages: PagePagination;
    filters: PageIndexFilters;
    canBulkUpdate: boolean;
    canImport: boolean;
    canManageTemplates: boolean;
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
            class="admin-page mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                title="Pages"
                description="Créez, publiez et maintenez le contenu de votre site."
            >
                <template #actions>
                    <Button v-if="canManageTemplates" variant="outline" as-child
                        ><Link :href="templatesIndex()"
                            >Modèles et champs</Link
                        ></Button
                    >
                    <Button v-if="canImport" variant="outline" as-child>
                        <Link :href="importPages('pages')"
                            ><Upload data-icon="inline-start" />Importer un
                            CSV</Link
                        >
                    </Button>
                    <Button as-child>
                        <Link :href="PageController.create()">
                            <Plus data-icon="inline-start" />
                            Nouvelle page
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <PageDataTable
                :pages="pages"
                :filters="filters"
                :can-bulk-update="canBulkUpdate"
            />
        </main>
    </div>
</template>
