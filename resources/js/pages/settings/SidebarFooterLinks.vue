<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Link2, Plus, Trash2 } from '@lucide/vue';
import SidebarFooterLinkController from '@/actions/App/Http/Controllers/Settings/SidebarFooterLinkController';
import EmptyState from '@/components/application/EmptyState.vue';
import FormLayout from '@/components/application/FormLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/sidebar-footer-links';
import type { SidebarFooterLink } from '@/types';

const props = defineProps<{
    links: SidebarFooterLink[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Menu latéral',
                href: edit(),
            },
        ],
    },
});

const form = useForm<{ links: SidebarFooterLink[] }>({
    links: props.links.map((link) => ({ ...link })),
});

function addLink(): void {
    form.links.push({
        title: '',
        url: '',
    });
    form.clearErrors();
}

function removeLink(index: number): void {
    form.links.splice(index, 1);
    form.clearErrors();
}

function moveLink(index: number, offset: -1 | 1): void {
    const destination = index + offset;

    if (destination < 0 || destination >= form.links.length) {
        return;
    }

    const [link] = form.links.splice(index, 1);

    if (link) {
        form.links.splice(destination, 0, link);
    }

    form.clearErrors();
}

function linkError(index: number, field: 'title' | 'url'): string | undefined {
    const errors = form.errors as Record<string, string>;

    return errors[`links.${index}.${field}`];
}

function submit(): void {
    form.submit(SidebarFooterLinkController.update(), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

function reset(): void {
    form.reset();
    form.clearErrors();
}
</script>

<template>
    <h1 class="sr-only">Menu latéral</h1>

    <form @submit.prevent="submit">
        <FormLayout
            title="Liens du bas de la barre latérale"
            description="Ajoutez, ordonnez ou retirez les liens internes et externes affichés pour tous les utilisateurs."
        >
            <template #headerAction>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="form.links.length >= 10"
                    @click="addLink"
                >
                    <Plus data-icon="inline-start" />
                    Ajouter
                </Button>
            </template>

            <FieldError :errors="[form.errors.links]" />

            <EmptyState
                v-if="form.links.length === 0"
                title="Aucun lien"
                description="La barre latérale ne présentera aucun raccourci sous la navigation principale."
                :icon="Link2"
            >
                <template #actions>
                    <Button type="button" variant="outline" @click="addLink">
                        <Plus data-icon="inline-start" />
                        Ajouter un lien
                    </Button>
                </template>
            </EmptyState>

            <div v-else class="flex flex-col gap-4">
                <Card v-for="(link, index) in form.links" :key="index">
                    <CardHeader>
                        <CardTitle>Lien {{ index + 1 }}</CardTitle>
                        <CardDescription>
                            {{ link.title || 'Nouveau raccourci' }}
                        </CardDescription>
                        <CardAction class="flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="index === 0"
                                :aria-label="`Monter le lien ${index + 1}`"
                                :title="`Monter le lien ${index + 1}`"
                                @click="moveLink(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="index === form.links.length - 1"
                                :aria-label="`Descendre le lien ${index + 1}`"
                                :title="`Descendre le lien ${index + 1}`"
                                @click="moveLink(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Retirer le lien ${index + 1}`"
                                :title="`Retirer le lien ${index + 1}`"
                                @click="removeLink(index)"
                            >
                                <Trash2 />
                            </Button>
                        </CardAction>
                    </CardHeader>

                    <CardContent>
                        <FieldGroup>
                            <Field
                                :data-invalid="
                                    Boolean(linkError(index, 'title'))
                                "
                            >
                                <FieldLabel :for="`link-${index}-title`">
                                    Libellé
                                </FieldLabel>
                                <Input
                                    :id="`link-${index}-title`"
                                    v-model="link.title"
                                    maxlength="80"
                                    autocomplete="off"
                                    :aria-invalid="
                                        Boolean(linkError(index, 'title'))
                                    "
                                    placeholder="Ex. Centre d’aide"
                                />
                                <FieldError
                                    :errors="[linkError(index, 'title')]"
                                />
                            </Field>

                            <Field
                                :data-invalid="Boolean(linkError(index, 'url'))"
                            >
                                <FieldLabel :for="`link-${index}-url`">
                                    Destination
                                </FieldLabel>
                                <Input
                                    :id="`link-${index}-url`"
                                    v-model="link.url"
                                    type="text"
                                    inputmode="url"
                                    maxlength="2048"
                                    autocomplete="url"
                                    autocapitalize="none"
                                    spellcheck="false"
                                    :aria-invalid="
                                        Boolean(linkError(index, 'url'))
                                    "
                                    placeholder="Ex. /pages ou https://exemple.com"
                                />
                                <FieldError
                                    :errors="[linkError(index, 'url')]"
                                />
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </div>

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
