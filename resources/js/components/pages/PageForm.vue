<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { FormLayout } from '@/components/application';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { PageDetail } from '@/types';

const props = defineProps<{
    page?: PageDetail;
}>();

const title = ref(props.page?.title ?? '');
const slug = ref(props.page?.slug ?? '');
const excerpt = ref(props.page?.excerpt ?? '');
const body = ref(props.page?.body ?? '');
const publicationStatus = ref(props.page?.is_published ? 'published' : 'draft');
const slugWasEdited = ref(Boolean(props.page));

const formAttributes = computed(() =>
    props.page
        ? PageController.update.form(props.page.id)
        : PageController.store.form(),
);

const cancelTarget = computed(() =>
    props.page ? PageController.show(props.page.id) : PageController.index(),
);

const submitLabel = computed(() =>
    props.page ? 'Enregistrer les modifications' : 'Créer la page',
);
const formDescription = computed(() =>
    props.page
        ? 'Les modifications sont appliquées dès l’enregistrement du formulaire.'
        : 'Donnez à la page un titre clair, un identifiant URL et du contenu.',
);

watch(title, (value) => {
    if (slugWasEdited.value) {
        return;
    }

    slug.value = value
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
});
</script>

<template>
    <Form v-bind="formAttributes" v-slot="{ errors, processing }">
        <FormLayout title="Détails de la page" :description="formDescription">
            <FieldGroup>
                <Field :data-invalid="errors.title ? true : undefined">
                    <FieldLabel for="title">Titre</FieldLabel>
                    <Input
                        id="title"
                        v-model="title"
                        name="title"
                        required
                        autofocus
                        autocomplete="off"
                        placeholder="À propos de notre entreprise"
                        :aria-invalid="Boolean(errors.title)"
                    />
                    <FieldError v-if="errors.title">
                        {{ errors.title }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.slug ? true : undefined">
                    <FieldLabel for="slug">Identifiant URL</FieldLabel>
                    <Input
                        id="slug"
                        v-model="slug"
                        name="slug"
                        required
                        autocomplete="off"
                        placeholder="a-propos-de-notre-entreprise"
                        :aria-invalid="Boolean(errors.slug)"
                        @input="slugWasEdited = true"
                    />
                    <FieldDescription>
                        Utilisé dans l’URL publique de cette page.
                    </FieldDescription>
                    <FieldError v-if="errors.slug">
                        {{ errors.slug }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.excerpt ? true : undefined">
                    <FieldLabel for="excerpt">Résumé</FieldLabel>
                    <Textarea
                        id="excerpt"
                        v-model="excerpt"
                        name="excerpt"
                        rows="3"
                        placeholder="Un court résumé de cette page"
                        :aria-invalid="Boolean(errors.excerpt)"
                    />
                    <FieldDescription>
                        Facultatif. Restez concis pour les listes et les
                        aperçus.
                    </FieldDescription>
                    <FieldError v-if="errors.excerpt">
                        {{ errors.excerpt }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.body ? true : undefined">
                    <FieldLabel for="body">Contenu</FieldLabel>
                    <Textarea
                        id="body"
                        v-model="body"
                        name="body"
                        rows="14"
                        placeholder="Rédigez le contenu de la page…"
                        :aria-invalid="Boolean(errors.body)"
                    />
                    <FieldError v-if="errors.body">
                        {{ errors.body }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.is_published ? true : undefined">
                    <FieldLabel for="publication-status">
                        Statut de publication
                    </FieldLabel>
                    <Select v-model="publicationStatus">
                        <SelectTrigger
                            id="publication-status"
                            :aria-invalid="Boolean(errors.is_published)"
                        >
                            <SelectValue placeholder="Choisir un statut" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="draft">Brouillon</SelectItem>
                                <SelectItem value="published">
                                    Publiée
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="is_published"
                        :value="publicationStatus === 'published' ? '1' : '0'"
                    />
                    <FieldDescription>
                        Les pages publiées reçoivent automatiquement une date de
                        publication.
                    </FieldDescription>
                    <FieldError v-if="errors.is_published">
                        {{ errors.is_published }}
                    </FieldError>
                </Field>
            </FieldGroup>

            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="cancelTarget">Annuler</Link>
                </Button>
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" data-icon="inline-start" />
                    {{ processing ? 'Enregistrement…' : submitLabel }}
                </Button>
            </template>
        </FormLayout>
    </Form>
</template>
