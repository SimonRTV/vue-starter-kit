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
    props.page ? 'Save changes' : 'Create page',
);
const formDescription = computed(() =>
    props.page
        ? 'Changes are applied as soon as you save the form.'
        : 'Give the page a clear title, URL slug, and content.',
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
        <FormLayout title="Page details" :description="formDescription">
            <FieldGroup>
                <Field :data-invalid="errors.title ? true : undefined">
                    <FieldLabel for="title">Title</FieldLabel>
                    <Input
                        id="title"
                        v-model="title"
                        name="title"
                        required
                        autofocus
                        autocomplete="off"
                        placeholder="About our company"
                        :aria-invalid="Boolean(errors.title)"
                    />
                    <FieldError v-if="errors.title">
                        {{ errors.title }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.slug ? true : undefined">
                    <FieldLabel for="slug">Slug</FieldLabel>
                    <Input
                        id="slug"
                        v-model="slug"
                        name="slug"
                        required
                        autocomplete="off"
                        placeholder="about-our-company"
                        :aria-invalid="Boolean(errors.slug)"
                        @input="slugWasEdited = true"
                    />
                    <FieldDescription>
                        Used in the public URL for this page.
                    </FieldDescription>
                    <FieldError v-if="errors.slug">
                        {{ errors.slug }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.excerpt ? true : undefined">
                    <FieldLabel for="excerpt">Excerpt</FieldLabel>
                    <Textarea
                        id="excerpt"
                        v-model="excerpt"
                        name="excerpt"
                        rows="3"
                        placeholder="A short summary of this page"
                        :aria-invalid="Boolean(errors.excerpt)"
                    />
                    <FieldDescription>
                        Optional. Keep it concise for listings and previews.
                    </FieldDescription>
                    <FieldError v-if="errors.excerpt">
                        {{ errors.excerpt }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.body ? true : undefined">
                    <FieldLabel for="body">Content</FieldLabel>
                    <Textarea
                        id="body"
                        v-model="body"
                        name="body"
                        rows="14"
                        placeholder="Write the page content…"
                        :aria-invalid="Boolean(errors.body)"
                    />
                    <FieldError v-if="errors.body">
                        {{ errors.body }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.is_published ? true : undefined">
                    <FieldLabel for="publication-status">
                        Publication status
                    </FieldLabel>
                    <Select v-model="publicationStatus">
                        <SelectTrigger
                            id="publication-status"
                            :aria-invalid="Boolean(errors.is_published)"
                        >
                            <SelectValue placeholder="Choose a status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="published">
                                    Published
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
                        Published pages receive a publication timestamp
                        automatically.
                    </FieldDescription>
                    <FieldError v-if="errors.is_published">
                        {{ errors.is_published }}
                    </FieldError>
                </Field>
            </FieldGroup>

            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="cancelTarget">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" data-icon="inline-start" />
                    {{ processing ? 'Saving…' : submitLabel }}
                </Button>
            </template>
        </FormLayout>
    </Form>
</template>
