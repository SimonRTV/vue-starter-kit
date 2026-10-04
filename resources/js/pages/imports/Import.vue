<script setup lang="ts">
import { setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import {
    create,
    store,
    preview,
    commit,
    errors,
    destroy,
} from '@/actions/App/Http/Controllers/CsvImportController';
import { PageHeader } from '@/components/application';
import CsvImportWizard from '@/components/application/CsvImportWizard.vue';
import type { ImportBatch, ImportField } from '@/types/imports';

const props = defineProps<{
    resource: string;
    label: string;
    columns: string[];
    mappingHelp: string;
    returnUrl: string;
    fields: ImportField[];
    batch: ImportBatch | null;
    canUpdate: boolean;
}>();
watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            { title: props.label, href: props.returnUrl },
            { title: 'Importer', href: create(props.resource) },
        ],
    }),
);
</script>

<template>
    <div class="flex flex-1 flex-col">
        <main
            class="admin-page mx-auto flex w-full max-w-[1400px] flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                :title="`Importer — ${label}`"
                description="Chargez un CSV, associez ses colonnes et vérifiez les modifications avant de les appliquer."
            />
            <CsvImportWizard
                :key="batch?.id ?? resource"
                :batch="batch"
                :fields="fields"
                :columns="columns"
                :can-update="canUpdate"
                :upload-url="store.url(resource)"
                :preview-url="
                    batch
                        ? preview.url({ resource, csvImport: batch.id })
                        : undefined
                "
                :commit-url="
                    batch
                        ? commit.url({ resource, csvImport: batch.id })
                        : undefined
                "
                :errors-url="
                    batch
                        ? errors.url({ resource, csvImport: batch.id })
                        : undefined
                "
                :destroy-url="
                    batch
                        ? destroy.url({ resource, csvImport: batch.id })
                        : undefined
                "
                :return-url="returnUrl"
                :mapping-help="mappingHelp"
            />
        </main>
    </div>
</template>
