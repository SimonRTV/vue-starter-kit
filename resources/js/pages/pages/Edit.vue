<script setup lang="ts">
import { setLayoutProps, usePage } from '@inertiajs/vue3';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { PageHeader } from '@/components/application';
import PageAttachments from '@/components/media/PageAttachments.vue';
import PageForm from '@/components/pages/PageForm.vue';
import type { PageDetail } from '@/types';
import type { MediaItem } from '@/types/media';

const shared = usePage();

const props = defineProps<{
    page: PageDetail;
    attachments: MediaItem[];
}>();

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
        {
            title: 'Modifier',
            href: PageController.edit(props.page.id),
        },
    ],
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <main
            class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                :title="'Modifier ' + page.title"
                description="Modifiez le contenu, l’URL ou le statut de publication de la page."
            />

            <PageForm :page="page" />
            <PageAttachments
                v-if="shared.props.auth.can.manageMedia"
                :page-id="page.id"
                :attachments="attachments"
            />
        </main>
    </div>
</template>
