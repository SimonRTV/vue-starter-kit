<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ArrowUpRight, Globe, Search, Share2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { PageHeader } from '@/components/application';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import { sitemap, robots } from '@/routes';
import { edit, update } from '@/routes/seo';

type Values = Record<string, string>;
type FieldDefinition = {
    key: string;
    label: string;
    hint?: string;
    max?: number;
    kind?: 'textarea' | 'url' | 'json';
    options?: { value: string; label: string }[];
};

const props = defineProps<{
    target: string;
    settings: Values;
    defaults: Values;
    fallback: { title: string | null; description: string | null };
    publicUrl: string;
    isPublished: boolean;
    pages: {
        id: number;
        title: string;
        slug: string;
        is_published: boolean;
        published_at: string | null;
    }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Référencement SEO', href: edit() }] },
});

const form = useForm<Values>({ ...props.settings });
const selection = ref(props.target);
const isGlobal = computed(() => props.target === 'defaults');
const selectedPage = computed(() =>
    props.pages.find((page) => String(page.id) === props.target),
);
const selectionTitle = computed(() =>
    isGlobal.value
        ? 'Paramètres par défaut'
        : props.target === 'home'
          ? 'Page d’accueil'
          : selectedPage.value?.title,
);
const inherit = (key: string) => form[key] || props.defaults[key] || '';
const previewTitle = computed(
    () =>
        (form.meta_title || props.fallback.title || props.defaults.meta_title) +
        ((isGlobal.value ? form.title_suffix : props.defaults.title_suffix)
            ? ` - ${isGlobal.value ? form.title_suffix : props.defaults.title_suffix}`
            : ''),
);
const previewDescription = computed(
    () =>
        form.meta_description ||
        props.fallback.description ||
        (isGlobal.value ? '' : props.defaults.meta_description),
);
const previewImage = computed(
    () => form.og_image || (isGlobal.value ? '' : props.defaults.og_image),
);
const isIndexable = computed(() => inherit('robots_index') === 'index');

function navigate() {
    if (
        form.isDirty &&
        !window.confirm('Quitter sans enregistrer vos modifications SEO ?')
    ) {
        selection.value = props.target;
        return;
    }
    router.get(edit.url({ target: selection.value }));
}

function save() {
    form.submit(update({ target: props.target }), {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
        },
    });
}

const sections = computed<
    { title: string; description: string; fields: FieldDefinition[] }[]
>(() => [
    ...(isGlobal.value
        ? [
              {
                  title: 'Identité du site',
                  description:
                      'Les valeurs communes à toutes vos pages publiques.',
                  fields: [
                      { key: 'site_name', label: 'Nom du site', max: 100 },
                      {
                          key: 'title_suffix',
                          label: 'Suffixe des titres',
                          max: 100,
                          hint: 'Ajouté après « - ». Laissez vide pour ne pas ajouter de suffixe.',
                      },
                      {
                          key: 'locale',
                          label: 'Langue et région',
                          hint: 'Format : fr_CH, fr_FR ou en_US.',
                      },
                  ],
              },
          ]
        : []),
    {
        title: 'Apparence dans les recherches',
        description: isGlobal.value
            ? 'Le titre d’accueil et la description de secours des pages sans résumé.'
            : 'Laissez un champ vide pour utiliser le titre, le résumé de la page ou les valeurs par défaut.',
        fields: [
            {
                key: 'meta_title',
                label: isGlobal.value
                    ? 'Titre d’accueil par défaut'
                    : 'Titre SEO',
                max: 255,
                hint: 'Visez environ 50 à 60 caractères, suffixe compris.',
            },
            {
                key: 'meta_description',
                label: 'Méta-description',
                kind: 'textarea',
                max: 1000,
                hint: 'Visez environ 150 à 160 caractères. Les moteurs peuvent afficher un autre extrait.',
            },
            ...(!isGlobal.value
                ? [
                      {
                          key: 'canonical_url',
                          label: 'URL canonique',
                          kind: 'url' as const,
                          max: 2048,
                          hint: 'Laissez vide pour utiliser l’URL de cette page. Une autre URL exclut cette page du sitemap.',
                      },
                  ]
                : []),
        ],
    },
    {
        title: 'Indexation et sitemap',
        description:
            'Contrôlez la visibilité dans les moteurs. Les brouillons et les publications futures restent privés.',
        fields: [
            {
                key: 'robots_index',
                label: 'Indexation',
                options: [
                    { value: 'index', label: 'Autoriser l’indexation' },
                    { value: 'noindex', label: 'Ne pas indexer (noindex)' },
                ],
            },
            {
                key: 'robots_follow',
                label: 'Suivi des liens',
                options: [
                    { value: 'follow', label: 'Suivre les liens' },
                    {
                        value: 'nofollow',
                        label: 'Ne pas suivre les liens (nofollow)',
                    },
                ],
            },
            {
                key: 'include_in_sitemap',
                label: 'Présence dans le sitemap',
                options: [
                    {
                        value: 'yes',
                        label: 'Inclure si la page est publiée et indexable',
                    },
                    { value: 'no', label: 'Exclure du sitemap' },
                ],
            },
        ],
    },
    {
        title: 'Partage social · Open Graph',
        description:
            'Personnalisez les aperçus de vos liens sur les réseaux sociaux.',
        fields: [
            ...(!isGlobal.value
                ? [
                      {
                          key: 'og_title',
                          label: 'Titre du partage',
                          max: 255,
                          hint: 'Par défaut : le titre SEO complet.',
                      },
                      {
                          key: 'og_description',
                          label: 'Description du partage',
                          kind: 'textarea' as const,
                          max: 1000,
                      },
                  ]
                : []),
            {
                key: 'og_image',
                label: 'URL de l’image de partage',
                kind: 'url',
                max: 2048,
                hint: 'URL publique HTTP(S). Une image de 1200 × 630 px convient aux aperçus larges.',
            },
            { key: 'og_image_alt', label: 'Description de l’image', max: 420 },
            {
                key: 'og_type',
                label: 'Type de contenu',
                options: [
                    { value: 'website', label: 'Site web' },
                    { value: 'article', label: 'Article' },
                ],
            },
        ],
    },
    {
        title: 'Cartes X / Twitter',
        description:
            'Le titre, la description et l’image Open Graph sont utilisés en l’absence de valeur spécifique.',
        fields: [
            {
                key: 'twitter_card',
                label: 'Format de la carte',
                options: [
                    { value: 'summary', label: 'Résumé' },
                    { value: 'summary_large_image', label: 'Grande image' },
                ],
            },
            ...(isGlobal.value
                ? [
                      {
                          key: 'twitter_site',
                          label: 'Compte du site',
                          hint: 'Exemple : @votrecompte',
                      },
                  ]
                : [
                      {
                          key: 'twitter_title',
                          label: 'Titre X / Twitter',
                          max: 255,
                      },
                      {
                          key: 'twitter_description',
                          label: 'Description X / Twitter',
                          kind: 'textarea' as const,
                          max: 1000,
                      },
                  ]),
            {
                key: 'twitter_image',
                label: 'URL d’image X / Twitter',
                kind: 'url',
                max: 2048,
            },
        ],
    },
    ...(isGlobal.value
        ? [
              {
                  title: 'Vérification des moteurs',
                  description:
                      'Collez uniquement le code de vérification, sans la balise HTML.',
                  fields: [
                      {
                          key: 'google_verification',
                          label: 'Google Search Console',
                          max: 255,
                      },
                      {
                          key: 'bing_verification',
                          label: 'Bing Webmaster Tools',
                          max: 255,
                      },
                  ],
              },
          ]
        : []),
    {
        title: 'Données structurées',
        description: isGlobal.value
            ? 'Objet JSON-LD ajouté à chaque page publique, par exemple votre Organization.'
            : 'Objet JSON-LD propre à cette page, ajouté aux données structurées globales.',
        fields: [
            {
                key: 'structured_data',
                label: 'JSON-LD',
                kind: 'json',
                max: 20000,
                hint: 'Un objet avec @context: "https://schema.org" et @type, sans valeur vide ou nulle. Le JSON est validé et publié sans exécuter de code.',
            },
        ],
    },
]);
</script>

<template>
    <div
        class="admin-page mx-auto flex w-full max-w-[1500px] flex-col gap-8 p-5 md:p-8 lg:p-10"
    >
        <PageHeader
            title="Référencement SEO"
            description="Gérez la visibilité de vos pages, leurs métadonnées et leurs aperçus de partage depuis un seul endroit."
        >
            <template #actions>
                <Button variant="outline" as-child
                    ><a
                        :href="sitemap.url()"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Sitemap XML <ArrowUpRight class="size-4" /></a
                ></Button>
                <Button
                    type="submit"
                    form="seo-form"
                    :disabled="form.processing"
                    ><Spinner v-if="form.processing" />Enregistrer</Button
                >
            </template>
        </PageHeader>

        <div
            class="bg-card flex flex-col gap-3 rounded-xl border p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <Globe class="text-primary size-5" />
                <div>
                    <p class="font-medium">{{ selectionTitle }}</p>
                    <p class="text-muted-foreground text-sm">
                        {{
                            isGlobal
                                ? 'Appliqués aux pages sans personnalisation'
                                : 'Personnalisation de cette page'
                        }}
                    </p>
                </div>
            </div>
            <div class="w-full sm:max-w-sm">
                <label for="seo-target" class="sr-only"
                    >Page à configurer</label
                >
                <select
                    id="seo-target"
                    v-model="selection"
                    class="border-input bg-background focus-visible:ring-ring h-11 w-full rounded-md border px-3 text-sm focus-visible:ring-2"
                    @change="navigate"
                >
                    <option value="defaults">
                        Paramètres par défaut du site
                    </option>
                    <option value="home">Page d’accueil</option>
                    <optgroup v-if="pages.length" label="Pages de contenu">
                        <option
                            v-for="page in pages"
                            :key="page.id"
                            :value="String(page.id)"
                        >
                            {{ page.title }}
                        </option>
                    </optgroup>
                </select>
            </div>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <form
                id="seo-form"
                class="flex min-w-0 flex-col gap-6"
                @submit.prevent="save"
            >
                <p
                    v-if="form.hasErrors"
                    role="alert"
                    class="text-destructive border-destructive/30 rounded-lg border p-4 text-sm"
                >
                    Certains champs sont invalides. Corrigez les erreurs
                    indiquées ci-dessous.
                </p>
                <Card v-for="section in sections" :key="section.title">
                    <CardHeader
                        ><CardTitle>{{ section.title }}</CardTitle
                        ><CardDescription>{{
                            section.description
                        }}</CardDescription></CardHeader
                    >
                    <CardContent class="grid gap-6 sm:grid-cols-2">
                        <Field
                            v-for="field in section.fields"
                            :key="field.key"
                            :class="{
                                'sm:col-span-2':
                                    field.kind === 'textarea' ||
                                    field.kind === 'json' ||
                                    field.kind === 'url',
                            }"
                        >
                            <FieldLabel :for="field.key">{{
                                field.label
                            }}</FieldLabel>
                            <select
                                v-if="field.options"
                                :id="field.key"
                                v-model="form[field.key]"
                                :aria-invalid="Boolean(form.errors[field.key])"
                                :aria-describedby="`${field.key}-help ${field.key}-error`"
                                class="border-input bg-background focus-visible:ring-ring h-10 w-full rounded-md border px-3 text-sm focus-visible:ring-2"
                            >
                                <option v-if="!isGlobal" value="">
                                    Par défaut ({{
                                        field.options.find(
                                            (option) =>
                                                option.value ===
                                                defaults[field.key],
                                        )?.label
                                    }})
                                </option>
                                <option
                                    v-for="option in field.options"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <Textarea
                                v-else-if="
                                    field.kind === 'textarea' ||
                                    field.kind === 'json'
                                "
                                :id="field.key"
                                v-model="form[field.key]"
                                :rows="field.kind === 'json' ? 7 : 3"
                                :class="{
                                    'font-mono text-xs': field.kind === 'json',
                                }"
                                :maxlength="field.max"
                                :aria-invalid="Boolean(form.errors[field.key])"
                                :aria-describedby="`${field.key}-help ${field.key}-error`"
                                :spellcheck="field.kind !== 'json'"
                            />
                            <Input
                                v-else
                                :id="field.key"
                                v-model="form[field.key]"
                                :type="field.kind === 'url' ? 'url' : 'text'"
                                :maxlength="field.max"
                                :placeholder="
                                    field.key === 'canonical_url'
                                        ? publicUrl
                                        : undefined
                                "
                                :aria-invalid="Boolean(form.errors[field.key])"
                                :aria-describedby="`${field.key}-help ${field.key}-error`"
                            />
                            <FieldDescription :id="`${field.key}-help`"
                                >{{ field.hint
                                }}<span
                                    v-if="
                                        [
                                            'meta_title',
                                            'meta_description',
                                        ].includes(field.key)
                                    "
                                    class="mt-1 block tabular-nums"
                                    >{{
                                        String(form[field.key] || '').length
                                    }}
                                    caractères</span
                                ></FieldDescription
                            >
                            <FieldError
                                v-if="form.errors[field.key]"
                                :id="`${field.key}-error`"
                                >{{ form.errors[field.key] }}</FieldError
                            >
                        </Field>
                    </CardContent>
                </Card>
                <div
                    class="bg-card flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5"
                >
                    <p
                        role="status"
                        aria-live="polite"
                        class="text-muted-foreground text-sm"
                    >
                        {{
                            form.recentlySuccessful
                                ? 'Paramètres enregistrés.'
                                : form.isDirty
                                  ? 'Modifications non enregistrées'
                                  : 'Tous les paramètres sont à jour'
                        }}
                    </p>
                    <Button type="submit" :disabled="form.processing"
                        ><Spinner v-if="form.processing" />Enregistrer les
                        paramètres</Button
                    >
                </div>
            </form>

            <aside
                class="flex min-w-0 flex-col gap-6 xl:sticky xl:top-6"
                aria-label="Aperçus SEO"
            >
                <Card>
                    <CardHeader
                        ><CardTitle class="flex items-center gap-2"
                            ><Search class="size-4" />Aperçu de
                            recherche</CardTitle
                        ><CardDescription>{{
                            isGlobal
                                ? 'Exemple pour la page d’accueil'
                                : 'Simulation de l’extrait de cette page'
                        }}</CardDescription></CardHeader
                    >
                    <CardContent class="flex flex-col gap-2">
                        <p class="text-muted-foreground truncate text-xs">
                            {{ form.canonical_url || publicUrl }}
                        </p>
                        <p
                            class="line-clamp-2 text-xl leading-snug text-blue-700 dark:text-blue-400"
                        >
                            {{ previewTitle }}
                        </p>
                        <p
                            class="text-muted-foreground line-clamp-3 text-sm leading-relaxed"
                        >
                            {{
                                previewDescription ||
                                'Ajoutez une description pour présenter cette page.'
                            }}
                        </p>
                        <p class="text-muted-foreground mt-3 text-xs">
                            {{ previewTitle.length }} caractères dans le titre
                            affiché · aperçu indicatif
                        </p>
                    </CardContent>
                </Card>
                <Card class="overflow-hidden">
                    <CardHeader
                        ><CardTitle class="flex items-center gap-2"
                            ><Share2 class="size-4" />Aperçu social</CardTitle
                        ></CardHeader
                    >
                    <CardContent>
                        <div class="overflow-hidden rounded-lg border">
                            <img
                                v-if="previewImage"
                                :src="previewImage"
                                :alt="
                                    form.og_image_alt ||
                                    defaults.og_image_alt ||
                                    'Aperçu du partage'
                                "
                                referrerpolicy="no-referrer"
                                class="aspect-[1200/630] w-full object-cover"
                            />
                            <div
                                v-else
                                class="bg-muted text-muted-foreground flex aspect-[1200/630] items-center justify-center"
                            >
                                <Globe class="size-10 opacity-30" />
                            </div>
                            <div class="bg-muted/30 flex flex-col gap-2 p-4">
                                <p
                                    class="text-muted-foreground text-xs uppercase"
                                >
                                    {{
                                        isGlobal
                                            ? form.site_name
                                            : defaults.site_name
                                    }}
                                </p>
                                <p class="line-clamp-2 font-medium">
                                    {{ form.og_title || previewTitle }}
                                </p>
                                <p
                                    class="text-muted-foreground line-clamp-2 text-sm"
                                >
                                    {{
                                        form.og_description ||
                                        previewDescription
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <div
                    class="text-muted-foreground flex flex-col gap-3 rounded-xl border p-5 text-sm"
                >
                    <p
                        :class="{
                            'text-amber-700 dark:text-amber-400':
                                !isIndexable || !isPublished,
                        }"
                    >
                        {{
                            !isPublished
                                ? 'Page non publiée : elle reste inaccessible au public et absente du sitemap.'
                                : isIndexable
                                  ? 'Indexation autorisée pour cette configuration.'
                                  : 'Indexation désactivée pour cette configuration.'
                        }}
                    </p>
                    <p v-if="!isGlobal">
                        Un champ vide reprend sa valeur de secours. Les
                        paramètres globaux ne modifient pas le contenu de vos
                        pages.
                    </p>
                    <a
                        :href="robots.url()"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary inline-flex items-center gap-1"
                        >Voir robots.txt <ArrowUpRight class="size-3"
                    /></a>
                </div>
            </aside>
        </div>
    </div>
</template>
