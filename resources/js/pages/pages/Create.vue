<script setup lang="ts">
import PageController from '@/actions/App/Http/Controllers/PageController';
import PageForm from '@/components/pages/PageForm.vue';
import type { PageTemplate, TemplateSource } from '@/types/page-templates';
defineProps<{ templates: PageTemplate[]; templateSources: TemplateSource[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pages',
                href: PageController.index(),
            },
            {
                title: 'Nouvelle page',
                href: PageController.create(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex min-w-0 flex-1 flex-col">
        <PageForm :templates="templates" :template-sources="templateSources" />
    </div>
</template>
