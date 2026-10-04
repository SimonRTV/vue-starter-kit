<script setup lang="ts">
import { setLayoutProps, usePage } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import PageAttachments from '@/components/media/PageAttachments.vue';
import PageForm from '@/components/pages/PageForm.vue';
import type { PageDetail } from '@/types';
import type { MediaItem } from '@/types/media';

const shared = usePage();

const props = defineProps<{
    page: PageDetail;
    attachments: MediaItem[];
}>();

watchEffect(() => {
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
});
</script>

<template>
    <div class="flex min-w-0 flex-1 flex-col">
        <PageForm :page="page">
            <template v-if="shared.props.auth.can.manageMedia" #attachments>
                <PageAttachments
                    :page-id="page.id"
                    :attachments="attachments"
                />
            </template>
        </PageForm>
    </div>
</template>
