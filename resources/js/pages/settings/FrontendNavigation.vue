<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { RotateCcw } from '@lucide/vue';
import { useEventListener } from '@vueuse/core';
import { onMounted, onUnmounted, ref } from 'vue';
import FrontendNavigationController from '@/actions/App/Http/Controllers/Settings/FrontendNavigationController';
import ConfirmationAction from '@/components/application/ConfirmationAction.vue';
import FormLayout from '@/components/application/FormLayout.vue';
import FrontendNavigationBuilder from '@/components/navigation/FrontendNavigationBuilder.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/frontend-navigation';
import type {
    FrontendNavigationDestinations,
    FrontendNavigationItem,
} from '@/types';

const props = defineProps<{
    items: FrontendNavigationItem[];
    defaultItems: FrontendNavigationItem[];
    destinations: FrontendNavigationDestinations;
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
const formElement = ref<HTMLFormElement>();
const builderKey = ref(0);
const restoreDialogOpen = ref(false);
const leaveDialogOpen = ref(false);
let pendingNavigation: (() => void) | undefined;
let leavingPage = false;
let removeNavigationListener: (() => void) | undefined;

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

function restoreDefaults(): void {
    form.items = copyItems(props.defaultItems);
    form.clearErrors();
    builderKey.value += 1;
    restoreDialogOpen.value = false;
}

function leavePage(): void {
    leaveDialogOpen.value = false;
    leavingPage = true;
    pendingNavigation?.();
    leavingPage = false;
}

useEventListener('keydown', (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();

        if (form.isDirty && !form.processing) {
            formElement.value?.requestSubmit();
        }
    }
});
useEventListener('beforeunload', (event: BeforeUnloadEvent) => {
    if (form.isDirty) {
        event.preventDefault();
        event.returnValue = '';
    }
});

onMounted(() => {
    removeNavigationListener = router.on('before', (event) => {
        if (
            event.detail.visit.method === 'get' &&
            event.detail.visit.url.pathname !== window.location.pathname &&
            form.isDirty &&
            !leavingPage
        ) {
            event.preventDefault();
            pendingNavigation = () =>
                router.visit(event.detail.visit.url, event.detail.visit);
            leaveDialogOpen.value = true;
        }
    });
});

onUnmounted(() => removeNavigationListener?.());
</script>

<template>
    <div>
        <h1 class="sr-only">Navigation publique</h1>

        <form ref="formElement" @submit.prevent="submit">
            <FormLayout
                title="Menu du site public"
                description="Organisez les liens et menus déroulants affichés dans l’en-tête des pages publiques."
            >
                <FrontendNavigationBuilder
                    :key="builderKey"
                    v-model="form.items"
                    :errors="form.errors"
                    :destinations="destinations"
                    @change="form.clearErrors()"
                />

                <template #actions>
                    <Button
                        type="button"
                        variant="ghost"
                        class="sm:mr-auto"
                        :disabled="form.processing"
                        data-test="navigation-restore-defaults"
                        @click="restoreDialogOpen = true"
                    >
                        <RotateCcw data-icon="inline-start" />
                        Restaurer le menu par défaut
                    </Button>
                    <p
                        v-if="form.isDirty"
                        class="text-muted-foreground text-center text-xs sm:text-right"
                        role="status"
                    >
                        Modifications non enregistrées
                    </p>
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
                        title="Enregistrer (⌘/Ctrl + S)"
                    >
                        <Spinner
                            v-if="form.processing"
                            data-icon="inline-start"
                        />
                        Enregistrer
                    </Button>
                </template>
            </FormLayout>
        </form>

        <ConfirmationAction
            v-model:open="restoreDialogOpen"
            title="Restaurer le menu par défaut ?"
            description="Les éléments actuels seront remplacés par le menu d’origine. Rien n’est enregistré tant que vous ne cliquez pas sur « Enregistrer »."
            confirm-label="Restaurer"
            confirm-variant="default"
            @confirm="restoreDefaults"
        />

        <ConfirmationAction
            v-model:open="leaveDialogOpen"
            title="Quitter sans enregistrer ?"
            description="Vos modifications de la navigation publique n’ont pas encore été enregistrées."
            confirm-label="Quitter sans enregistrer"
            cancel-label="Continuer l’édition"
            @confirm="leavePage"
        />
    </div>
</template>
