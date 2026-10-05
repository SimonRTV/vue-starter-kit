<script setup lang="ts">
import FrontendHeader from '@/components/frontend/FrontendHeader.vue';
import FrontendFooter from '@/components/frontend/FrontendFooter.vue';
import PagePreviewBanner from '@/components/pages/PagePreviewBanner.vue';
import TemplateContent from '@/components/pages/TemplateContent.vue';
import type { PublicPage } from '@/types';
import type {
    PageTemplate,
    TemplateValues,
    TemplateCollections,
} from '@/types/page-templates';
defineProps<{
    page: PublicPage;
    template: PageTemplate;
    fields: TemplateValues;
    collections: TemplateCollections;
}>();
</script>
<template>
    <div class="bg-background text-foreground min-h-screen">
        <PagePreviewBanner />
        <FrontendHeader />
        <main>
            <header class="bg-muted/40 border-b px-6 py-20 sm:py-28">
                <div class="mx-auto max-w-4xl space-y-6 text-center">
                    <p
                        class="text-muted-foreground text-sm tracking-widest uppercase"
                    >
                        {{ template.name }}
                    </p>
                    <h1
                        class="text-5xl leading-tight font-semibold tracking-tight sm:text-6xl"
                    >
                        {{ page.title }}
                    </h1>
                    <p
                        v-if="page.excerpt"
                        class="text-muted-foreground text-xl leading-8"
                    >
                        {{ page.excerpt }}
                    </p>
                </div>
            </header>
            <div class="mx-auto max-w-7xl space-y-12 px-6 py-16">
                <div
                    v-if="page.body_html"
                    class="rich-content mx-auto max-w-3xl"
                    v-html="page.body_html"
                />
                <TemplateContent
                    :template="template"
                    :fields="fields"
                    :collections="collections"
                />
            </div>
        </main>
        <FrontendFooter />
    </div>
</template>
