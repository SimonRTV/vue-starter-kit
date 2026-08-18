<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FrontendNavigationController from '@/actions/App/Http/Controllers/Settings/FrontendNavigationController';
import FormLayout from '@/components/application/FormLayout.vue';
import FrontendNavigationBuilder from '@/components/navigation/FrontendNavigationBuilder.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/frontend-navigation';
import type { FrontendNavigationItem } from '@/types';

const props = defineProps<{
    items: FrontendNavigationItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Navigation publique',
                href: edit(),
            },
        ],
    },
});

function copyItems(items: FrontendNavigationItem[]): FrontendNavigationItem[] {
    return items.map((item) => ({
        ...item,
        children: item.children.map((child) => ({ ...child })),
    }));
}

const form = useForm<{ items: FrontendNavigationItem[] }>({
    items: copyItems(props.items),
});
const builderKey = ref(0);

function submit(): void {
    form.submit(FrontendNavigationController.update(), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

function reset(): void {
    form.reset();
    form.clearErrors();
    builderKey.value += 1;
}
</script>

<template>
    <h1 class="sr-only">Navigation publique</h1>

    <form @submit.prevent="submit">
        <FormLayout
            title="Menu du site public"
            description="Organisez les liens et menus déroulants affichés dans l’en-tête des pages publiques."
        >
            <FrontendNavigationBuilder
                :key="builderKey"
                v-model="form.items"
                :errors="form.errors"
                @change="form.clearErrors()"
            />

            <template #actions>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing || !form.isDirty"
                    @click="reset"
                >
                    Annuler les modifications
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                >
                    <Spinner v-if="form.processing" data-icon="inline-start" />
                    Enregistrer
                </Button>
            </template>
        </FormLayout>
    </form>
</template>
