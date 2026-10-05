<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Circle,
    Eye,
    FileText,
    Globe,
    Maximize2,
    Minimize2,
    PenLine,
    Save,
    Settings2,
} from '@lucide/vue';
import { useEventListener } from '@vueuse/core';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/PageController';
import ConfirmationAction from '@/components/application/ConfirmationAction.vue';
import TemplateFieldsEditor from '@/components/pages/TemplateFieldsEditor.vue';
import type {
    PageTemplate,
    TemplateSource,
    TemplateValues,
} from '@/types/page-templates';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Badge } from '@/components/ui/badge';
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
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { show as publicPage } from '@/routes/content';
import type { PageDetail } from '@/types';

const props = defineProps<{
    page?: PageDetail;
    templates: PageTemplate[];
    templateSources: TemplateSource[];
}>();
function initialFields(
    templateId: number | null,
    saved: TemplateValues = {},
): TemplateValues {
    const values: TemplateValues = {};
    for (const set of props.templates.find(
        (template) => template.id === templateId,
    )?.field_sets ?? []) {
        values[set.key] = {};
        for (const field of set.fields) {
            values[set.key][field.key] =
                saved[set.key]?.[field.key] ??
                (field.type === 'boolean'
                    ? false
                    : field.type === 'collection'
                      ? {
                            source: props.templateSources[0]?.key ?? '',
                            limit: 6,
                            order_by:
                                Object.keys(
                                    props.templateSources[0]?.orders ?? {},
                                )[0] ?? '',
                            direction: 'desc',
                        }
                      : null);
        }
    }
    return values;
}
const shared = usePage();
const form = useForm({
    page_template_id: props.page?.page_template_id ?? (null as number | null),
    template_fields: initialFields(
        props.page?.page_template_id ?? null,
        props.page?.template_fields,
    ),
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    excerpt: props.page?.excerpt ?? '',
    body: props.page?.body_html ?? '',
    body_format: 'html',
    is_published: props.page?.is_published ?? false,
});
const selectedTemplate = computed(() =>
    props.templates.find((template) => template.id === form.page_template_id),
);
const templateSelection = computed({
    get: () =>
        form.page_template_id === null
            ? 'default'
            : String(form.page_template_id),
    set: (value: string) => {
        form.page_template_id = value === 'default' ? null : Number(value);
        form.template_fields = initialFields(form.page_template_id);
        form.clearErrors();
    },
});
const formElement = useTemplateRef<HTMLFormElement>('formElement');
const focusMode = ref(false);
const preview = ref(false);
const leaveDialogOpen = ref(false);
let pendingNavigation: (() => void) | undefined;
let leavingEditor = false;
const slugWasEdited = ref(Boolean(props.page));
const saveStatus = computed(() =>
    form.processing
        ? 'Enregistrement…'
        : form.isDirty
          ? 'Modifications non enregistrées'
          : props.page
            ? 'Toutes les modifications sont enregistrées'
            : 'Nouvelle page',
);
const updatedAt = computed(() =>
    props.page?.updated_at
        ? new Intl.DateTimeFormat('fr-CH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(props.page.updated_at))
        : null,
);

watch(
    () => form.title,
    (value) => {
        if (slugWasEdited.value) {
            return;
        }

        form.slug = value
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    },
);

function submit(event: SubmitEvent): void {
    if (form.processing) {
        return;
    }

    const intent =
        (event.submitter as HTMLButtonElement | null)?.value || 'save';

    form.transform((data) => ({ ...data, intent })).submit(
        props.page ? update(props.page.id) : store(),
        {
            preserveScroll: true,
            onSuccess: () => {
                if (props.page && intent !== 'preview') {
                    form.title = props.page.title;
                    form.slug = props.page.slug;
                    form.excerpt = props.page.excerpt ?? '';
                    form.body = props.page.body_html;
                    form.is_published = props.page.is_published;
                    form.page_template_id = props.page.page_template_id;
                    form.template_fields = initialFields(
                        props.page.page_template_id,
                        props.page.template_fields,
                    );
                    form.defaults();
                }
            },
            onError: () => {
                preview.value = false;
                focusMode.value = false;
            },
        },
    );
}

useEventListener('keydown', (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        formElement.value?.requestSubmit();
    }
});
useEventListener('beforeunload', (event: BeforeUnloadEvent) => {
    if (form.isDirty) {
        event.preventDefault();
        event.returnValue = '';
    }
});

let removeNavigationListener: (() => void) | undefined;

function leaveEditor(): void {
    leaveDialogOpen.value = false;
    leavingEditor = true;
    pendingNavigation?.();
    leavingEditor = false;
}

onMounted(() => {
    removeNavigationListener = router.on('before', (event) => {
        if (
            event.detail.visit.method === 'get' &&
            event.detail.visit.url.pathname !== window.location.pathname &&
            form.isDirty &&
            !leavingEditor
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
    <div
        :class="
            focusMode
                ? 'bg-background fixed inset-0 z-40 overflow-y-auto overscroll-contain'
                : 'min-w-0 flex-1'
        "
    >
        <form
            ref="formElement"
            class="flex flex-col"
            :class="{ 'min-h-full': focusMode }"
            @submit.prevent="submit"
        >
            <header
                class="bg-background/95 sticky top-0 z-20 border-b backdrop-blur"
            >
                <div
                    class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <Button
                            variant="outline"
                            size="icon"
                            as-child
                            class="shrink-0"
                        >
                            <Link :href="index()" aria-label="Retour aux pages"
                                ><ArrowLeft class="size-4"
                            /></Link>
                        </Button>
                        <div class="min-w-0">
                            <h1 class="text-base font-semibold tracking-tight">
                                {{ page ? 'Éditeur de page' : 'Nouvelle page' }}
                            </h1>
                            <p
                                role="status"
                                class="text-muted-foreground mt-1 flex items-center gap-1.5 text-xs"
                            >
                                <Spinner
                                    v-if="form.processing"
                                    class="size-3"
                                />
                                <Circle
                                    v-else-if="form.isDirty"
                                    class="size-2 fill-amber-500 text-amber-500"
                                />
                                <Check
                                    v-else-if="page"
                                    class="size-3 text-emerald-600 dark:text-emerald-400"
                                />
                                {{ saveStatus }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex w-full flex-wrap items-center gap-2 sm:w-auto"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            :aria-pressed="focusMode"
                            :aria-label="
                                focusMode
                                    ? 'Quitter le mode concentration'
                                    : 'Mode concentration'
                            "
                            :title="
                                focusMode
                                    ? 'Quitter le mode concentration'
                                    : 'Mode concentration'
                            "
                            @click="focusMode = !focusMode"
                        >
                            <Minimize2
                                v-if="focusMode"
                                class="size-4"
                            /><Maximize2 v-else class="size-4" />
                        </Button>
                        <Button
                            v-if="
                                page?.is_published &&
                                shared.props.features.public_site
                            "
                            variant="outline"
                            as-child
                            class="hidden sm:inline-flex"
                        >
                            <a
                                :href="publicPage.url(page.public_slug)"
                                target="_blank"
                                rel="noopener noreferrer"
                                ><Globe class="size-4" />Voir en ligne</a
                            >
                        </Button>
                        <Button
                            v-if="
                                shared.props.features.public_site &&
                                shared.props.auth.can.managePages
                            "
                            type="submit"
                            name="intent"
                            value="preview"
                            variant="outline"
                            :disabled="form.processing"
                            title="Enregistrer le brouillon et ouvrir l’aperçu privé"
                        >
                            <Eye class="size-4" />Enregistrer et prévisualiser
                        </Button>
                        <Button
                            type="submit"
                            name="intent"
                            value="save"
                            :disabled="form.processing"
                            class="flex-1 sm:flex-none"
                            title="Enregistrer (⌘/Ctrl+S)"
                        >
                            <Spinner
                                v-if="form.processing"
                                class="size-4"
                            /><Save v-else class="size-4" />
                            {{
                                form.processing
                                    ? 'Enregistrement…'
                                    : page
                                      ? 'Enregistrer'
                                      : 'Enregistrer le brouillon'
                            }}
                        </Button>
                    </div>
                </div>
            </header>

            <div
                class="mx-auto grid w-full max-w-[1600px] flex-1 items-start gap-6 p-4 sm:p-6 lg:p-8"
                :class="
                    focusMode
                        ? 'max-w-5xl!'
                        : 'xl:grid-cols-[minmax(0,1fr)_300px]'
                "
            >
                <div class="min-w-0 space-y-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div
                            class="bg-muted/60 inline-flex items-center rounded-lg border p-1"
                            role="group"
                            aria-label="Mode de l’éditeur"
                        >
                            <Button
                                type="button"
                                :variant="preview ? 'ghost' : 'secondary'"
                                size="sm"
                                :aria-pressed="!preview"
                                @click="preview = false"
                                ><PenLine class="size-3.5" />Rédiger</Button
                            >
                            <Button
                                type="button"
                                :variant="preview ? 'secondary' : 'ghost'"
                                size="sm"
                                :aria-pressed="preview"
                                @click="preview = true"
                                ><Eye class="size-3.5" />Aperçu du texte</Button
                            >
                        </div>
                        <span
                            class="text-muted-foreground flex items-center gap-1.5 text-xs"
                            ><FileText class="size-3.5" />{{
                                preview
                                    ? 'Aperçu de votre page'
                                    : 'Votre espace de rédaction'
                            }}</span
                        >
                    </div>

                    <section
                        class="bg-background overflow-hidden rounded-xl border shadow-xs"
                        aria-label="Document"
                    >
                        <div class="px-6 pt-8 pb-5 sm:px-10 sm:pt-10">
                            <Field
                                :data-invalid="
                                    form.errors.title ? true : undefined
                                "
                            >
                                <FieldLabel
                                    for="title"
                                    class="text-muted-foreground text-xs font-medium tracking-widest uppercase"
                                    >Titre de la page</FieldLabel
                                >
                                <textarea
                                    v-show="!preview"
                                    id="title"
                                    v-model="form.title"
                                    name="title"
                                    rows="1"
                                    required
                                    maxlength="255"
                                    :readonly="preview || form.processing"
                                    placeholder="Donnez un titre à votre page…"
                                    :aria-invalid="Boolean(form.errors.title)"
                                    class="placeholder:text-muted-foreground/50 focus-visible:outline-ring field-sizing-content min-h-12 w-full resize-none bg-transparent py-1 text-3xl leading-tight font-semibold tracking-tight outline-none focus-visible:outline-2 focus-visible:outline-offset-4 sm:text-4xl"
                                />
                                <h2
                                    v-if="preview"
                                    class="text-3xl leading-tight font-semibold tracking-tight break-words sm:text-4xl"
                                >
                                    {{ form.title || 'Sans titre' }}
                                </h2>
                                <FieldError v-if="form.errors.title">{{
                                    form.errors.title
                                }}</FieldError>
                            </Field>
                            <p
                                v-if="preview && form.excerpt"
                                class="text-muted-foreground mt-4 text-lg leading-relaxed"
                            >
                                {{ form.excerpt }}
                            </p>
                        </div>
                        <RichTextEditor
                            id="body"
                            v-model="form.body"
                            document
                            :preview="preview"
                            :disabled="form.processing"
                            :invalid="Boolean(form.errors.body)"
                        />
                        <FieldError v-if="form.errors.body" class="px-6 pb-4">{{
                            form.errors.body
                        }}</FieldError>
                    </section>
                    <TemplateFieldsEditor
                        v-if="selectedTemplate"
                        v-model="form.template_fields"
                        :template="selectedTemplate"
                        :sources="templateSources"
                        :errors="form.errors"
                        :disabled="form.processing"
                    />
                    <FieldError v-if="form.errors.template_fields">{{
                        form.errors.template_fields
                    }}</FieldError>
                    <p class="text-muted-foreground text-xs">
                        {{
                            preview
                                ? 'Aperçu du texte uniquement. Enregistrez puis ouvrez la page publique pour voir la mise en page du modèle.'
                                : 'Astuce : utilisez ⌘/Ctrl + S pour enregistrer sans quitter l’éditeur.'
                        }}
                    </p>
                </div>

                <aside
                    v-show="!focusMode"
                    class="min-w-0 space-y-5"
                    aria-label="Paramètres de la page"
                >
                    <section
                        class="bg-background space-y-4 rounded-xl border p-5"
                    >
                        <Field
                            ><FieldLabel for="page-template"
                                >Modèle de page</FieldLabel
                            >
                            <Select
                                v-model="templateSelection"
                                :disabled="form.processing"
                                ><SelectTrigger
                                    id="page-template"
                                    class="w-full"
                                    ><SelectValue
                                        placeholder="Modèle indisponible" /></SelectTrigger
                                ><SelectContent
                                    ><SelectItem value="default"
                                        >Page standard</SelectItem
                                    ><SelectItem
                                        v-for="template in templates"
                                        :key="template.id"
                                        :value="String(template.id)"
                                        >{{ template.name }}</SelectItem
                                    ></SelectContent
                                ></Select
                            >
                            <FieldDescription
                                >Le modèle détermine la mise en page publique et
                                ses champs. Changer de modèle réinitialise les
                                champs personnalisés.</FieldDescription
                            >
                            <FieldError v-if="form.errors.page_template_id">{{
                                form.errors.page_template_id
                            }}</FieldError>
                        </Field>
                        <p
                            v-if="form.page_template_id && !selectedTemplate"
                            class="text-destructive text-sm"
                        >
                            Ce modèle n’est plus disponible. Choisissez un autre
                            modèle pour enregistrer.
                        </p>
                    </section>
                    <section
                        class="bg-background space-y-5 rounded-xl border p-5"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h2
                                class="flex items-center gap-2 text-sm font-semibold"
                            >
                                <Globe
                                    class="text-muted-foreground size-4"
                                />Publication
                            </h2>
                            <Badge variant="secondary">{{
                                page?.has_draft
                                    ? 'Modifications privées'
                                    : page?.is_published
                                      ? 'En ligne'
                                      : 'Brouillon'
                            }}</Badge>
                        </div>
                        <Field
                            :data-invalid="
                                form.errors.is_published ? true : undefined
                            "
                        >
                            <FieldDescription>{{
                                page?.is_published
                                    ? 'Enregistrer conserve vos modifications en privé. Publiez-les lorsque vous êtes prêt ; la version actuelle reste en ligne.'
                                    : 'Enregistrez et prévisualisez votre brouillon. La page sera visible sur le site uniquement après publication.'
                            }}</FieldDescription>
                            <Button
                                type="submit"
                                name="intent"
                                value="publish"
                                :disabled="form.processing"
                            >
                                <Globe class="size-4" />{{
                                    page?.is_published
                                        ? 'Publier les modifications'
                                        : 'Publier la page'
                                }}
                            </Button>
                            <Button
                                v-if="page?.is_published"
                                type="submit"
                                name="intent"
                                value="unpublish"
                                variant="outline"
                                :disabled="form.processing"
                            >
                                Retirer du site
                            </Button>
                            <FieldError v-if="form.errors.is_published">{{
                                form.errors.is_published
                            }}</FieldError>
                        </Field>
                        <div
                            v-if="updatedAt"
                            class="text-muted-foreground border-t pt-4 text-xs leading-relaxed"
                        >
                            <span class="block">Dernier enregistrement</span
                            ><span class="text-foreground">{{
                                updatedAt
                            }}</span>
                        </div>
                    </section>
                    <section
                        class="bg-background space-y-5 rounded-xl border p-5"
                    >
                        <h2
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <Settings2
                                class="text-muted-foreground size-4"
                            />Paramètres de la page
                        </h2>
                        <FieldGroup>
                            <Field
                                :data-invalid="
                                    form.errors.slug ? true : undefined
                                "
                            >
                                <FieldLabel for="slug"
                                    >Identifiant URL</FieldLabel
                                >
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    name="slug"
                                    :required="!focusMode"
                                    maxlength="255"
                                    autocomplete="off"
                                    :disabled="form.processing"
                                    placeholder="ma-nouvelle-page"
                                    :aria-invalid="Boolean(form.errors.slug)"
                                    @input="slugWasEdited = true"
                                />
                                <FieldDescription class="break-all">{{
                                    publicPage.url(
                                        form.slug || 'ma-nouvelle-page',
                                    )
                                }}</FieldDescription>
                                <FieldError v-if="form.errors.slug">{{
                                    form.errors.slug
                                }}</FieldError>
                            </Field>
                            <Field
                                :data-invalid="
                                    form.errors.excerpt ? true : undefined
                                "
                            >
                                <FieldLabel for="excerpt"
                                    >Résumé
                                    <span
                                        class="text-muted-foreground font-normal"
                                        >(facultatif)</span
                                    ></FieldLabel
                                >
                                <Textarea
                                    id="excerpt"
                                    v-model="form.excerpt"
                                    name="excerpt"
                                    rows="4"
                                    maxlength="500"
                                    :disabled="form.processing"
                                    placeholder="Présentez votre page en quelques mots…"
                                    :aria-invalid="Boolean(form.errors.excerpt)"
                                />
                                <FieldDescription
                                    >Une courte introduction pour les listes et
                                    les aperçus.</FieldDescription
                                >
                                <FieldError v-if="form.errors.excerpt">{{
                                    form.errors.excerpt
                                }}</FieldError>
                            </Field>
                        </FieldGroup>
                    </section>
                </aside>
            </div>
        </form>
        <div
            v-if="$slots.attachments"
            v-show="!focusMode"
            class="mx-auto w-full max-w-[1600px] px-4 pb-6 sm:px-6 lg:px-8"
        >
            <slot name="attachments" />
        </div>
        <ConfirmationAction
            v-model:open="leaveDialogOpen"
            title="Quitter l’éditeur ?"
            description="Vos modifications n’ont pas encore été enregistrées. Vous pouvez continuer la rédaction ou quitter sans les enregistrer."
            confirm-label="Quitter sans enregistrer"
            cancel-label="Continuer la rédaction"
            @confirm="leaveEditor"
        />
    </div>
</template>
