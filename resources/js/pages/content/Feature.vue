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
        <main
            class="mx-auto grid max-w-7xl gap-12 px-6 py-16 lg:grid-cols-[1fr_1.5fr] lg:gap-20 lg:py-24"
        >
            <header class="space-y-6 lg:sticky lg:top-28 lg:self-start">
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
                <div
                    v-if="page.body_html"
                    class="rich-content"
                    v-html="page.body_html"
                />
            </header>
            <TemplateContent
                :template="template"
                :fields="fields"
                :collections="collections"
                compact
            />
        </main>
        <FrontendFooter />
    </div>
</template>
