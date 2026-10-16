<script setup lang="ts">
import { Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ArrowLeft, History, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    edit,
    index as pagesIndex,
    show,
} from '@/actions/App/Http/Controllers/PageController';
import {
    index,
    restore,
} from '@/actions/App/Http/Controllers/PageRevisionController';
import { ConfirmationAction, PageHeader } from '@/components/application';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Pagination } from '@/types/pagination';

type Content = {
    title: string;
    slug: string;
    excerpt: string | null;
    body: string | null;
    body_format: string;
    page_template_id: number | null;
    template_fields: Record<string, unknown> | null;
};
type Revision = {
    id: number;
    author_name: string | null;
    event: string;
    is_published: boolean;
    created_at: string;
};
const props = defineProps<{
    page: { id: number; title: string; is_published: boolean };
    revisions: Pagination<Revision>;
    selected:
        | (Omit<Revision, 'is_published'> & {
              content: Content;
              body_html: string;
              template_name: string;
          })
        | null;
    current: Content & { body_html: string; template_name: string };
    canRestore: boolean;
    timezone: string;
}>();
const confirmationOpen = ref(false);
const form = useForm({ revision: '' });
const fields: { key: keyof Content; label: string }[] = [
    { key: 'title', label: 'Titre' },
    { key: 'slug', label: 'Adresse' },
    { key: 'excerpt', label: 'Résumé' },
    { key: 'body', label: 'Contenu' },
    { key: 'body_format', label: 'Format' },
    { key: 'page_template_id', label: 'Modèle de page' },
    { key: 'template_fields', label: 'Champs du modèle' },
];
function changed(key: keyof Content): boolean {
    return (
        JSON.stringify(props.selected?.content[key] ?? null) !==
        JSON.stringify(props.current[key] ?? null)
    );
}
const changes = computed(() => fields.filter(({ key }) => changed(key)).length);
function date(value: string): string {
    return new Intl.DateTimeFormat('fr-CH', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: props.timezone,
    }).format(new Date(value));
}
function display(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Oui' : 'Non';
    if (typeof value === 'object')
        return Object.entries(value)
            .map(
                ([key, item]) =>
                    `${key.replaceAll('_', ' ')} : ${display(item)}`,
            )
            .join('\n');
    return String(value);
}
function restoreRevision(): void {
    if (!props.selected) return;
    form.post(
        restore.url({ page: props.page.id, revision: props.selected.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                confirmationOpen.value = false;
            },
            onError: () => {
                confirmationOpen.value = false;
            },
        },
    );
}
setLayoutProps({
    breadcrumbs: [
        { title: 'Pages', href: pagesIndex() },
        { title: props.page.title, href: show(props.page.id) },
        { title: 'Historique', href: index(props.page.id) },
    ],
});
</script>

<template>
    <main
        class="admin-page mx-auto flex w-full max-w-[1600px] min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
    >
        <PageHeader title="Historique des versions" :description="page.title">
            <template #actions>
                <Button variant="outline" as-child
                    ><Link :href="show(page.id)"
                        ><ArrowLeft class="size-4" />Retour à la page</Link
                    ></Button
                >
                <Button v-if="canRestore" variant="outline" as-child
                    ><Link :href="edit(page.id)">Ouvrir l’éditeur</Link></Button
                >
            </template>
        </PageHeader>
        <p class="text-muted-foreground max-w-3xl text-sm leading-relaxed">
            Comparez une version enregistrée avec le contenu actuel, y compris
            le brouillon en cours. La restauration crée un brouillon à vérifier
            avant publication. Les pièces jointes et les paramètres SEO restent
            inchangés.
        </p>
        <p
            v-if="form.errors.revision"
            role="alert"
            class="text-destructive border-destructive/30 rounded-xl border p-4 text-sm"
        >
            {{ form.errors.revision }}
        </p>
        <div
            v-if="!selected"
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed p-10 text-center"
        >
            <History class="text-muted-foreground size-8" />
            <h2 class="font-medium">Pas encore de version enregistrée</h2>
            <p class="text-muted-foreground max-w-lg text-sm">
                Au prochain enregistrement, le contenu actuel sera conservé dans
                l’historique. Les modifications antérieures à l’activation de
                l’historique ne sont pas disponibles.
            </p>
        </div>
        <div
            v-else
            class="grid min-w-0 gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]"
        >
            <aside class="space-y-4" aria-label="Versions enregistrées">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-medium">Versions enregistrées</h2>
                    <Badge variant="secondary">{{ revisions.total }}</Badge>
                </div>
                <p class="text-muted-foreground text-xs">
                    Dates : {{ timezone }}
                </p>
                <ol
                    class="admin-panel divide-y overflow-hidden rounded-xl border"
                >
                    <li
                        v-for="(revision, position) in revisions.data"
                        :key="revision.id"
                    >
                        <Link
                            :href="
                                index(page.id, {
                                    query: {
                                        revision: revision.id,
                                        history_page: revisions.current_page,
                                    },
                                })
                            "
                            :aria-current="
                                selected.id === revision.id ? 'true' : undefined
                            "
                            class="hover:bg-muted/50 focus-visible:ring-ring flex flex-col gap-2 p-4 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            :class="
                                selected.id === revision.id
                                    ? 'bg-primary/5 border-primary border-l-2'
                                    : ''
                            "
                            preserve-scroll
                        >
                            <span class="text-muted-foreground text-xs"
                                >Version
                                {{
                                    revisions.total -
                                    (revisions.current_page - 1) *
                                        revisions.per_page -
                                    position
                                }}</span
                            >
                            <time
                                :datetime="revision.created_at"
                                class="font-medium"
                                >{{ date(revision.created_at) }}</time
                            >
                            <span class="text-muted-foreground break-words">{{
                                revision.event === 'baseline'
                                    ? 'Version de départ · auteur inconnu'
                                    : (revision.author_name ?? 'Système')
                            }}</span>
                            <span
                                ><Badge
                                    :variant="
                                        revision.is_published
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    >{{
                                        revision.is_published
                                            ? 'Publiée'
                                            : 'Brouillon'
                                    }}</Badge
                                ></span
                            >
                        </Link>
                    </li>
                </ol>
                <nav
                    aria-label="Pagination des versions"
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <Button
                        v-if="revisions.current_page > 1"
                        variant="outline"
                        size="sm"
                        as-child
                        ><Link
                            :href="
                                index(page.id, {
                                    query: {
                                        history_page:
                                            revisions.current_page - 1,
                                        revision: selected.id,
                                    },
                                })
                            "
                            preserve-scroll
                            >Précédent</Link
                        ></Button
                    >
                    <span class="text-muted-foreground text-xs"
                        >{{ revisions.current_page }} /
                        {{ revisions.last_page }}</span
                    >
                    <Button
                        v-if="revisions.current_page < revisions.last_page"
                        variant="outline"
                        size="sm"
                        as-child
                        ><Link
                            :href="
                                index(page.id, {
                                    query: {
                                        history_page:
                                            revisions.current_page + 1,
                                        revision: selected.id,
                                    },
                                })
                            "
                            preserve-scroll
                            >Suivant</Link
                        ></Button
                    >
                </nav>
            </aside>
            <section
                class="min-w-0 space-y-4"
                aria-label="Comparaison des versions"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-medium">
                            Comparaison avec le contenu actuel
                        </h2>
                        <p
                            class="text-muted-foreground mt-1 text-sm"
                            role="status"
                        >
                            {{
                                changes
                                    ? `${changes} champ(s) différent(s)`
                                    : 'Cette version est identique au contenu actuel.'
                            }}
                        </p>
                    </div>
                    <ConfirmationAction
                        v-if="canRestore"
                        v-model:open="confirmationOpen"
                        title="Restaurer cette version ?"
                        description="Le contenu actuel est conservé dans l’historique. Cette version remplacera le brouillon en cours. La page en ligne reste inchangée jusqu’à publication."
                        confirm-label="Restaurer en brouillon"
                        pending-label="Restauration…"
                        confirm-variant="default"
                        :processing="form.processing"
                        @confirm="restoreRevision"
                    >
                        <template #trigger
                            ><Button :disabled="!changes || form.processing"
                                ><RotateCcw class="size-4" />Restaurer cette
                                version</Button
                            ></template
                        >
                    </ConfirmationAction>
                </div>
                <div
                    class="admin-panel min-w-0 overflow-hidden rounded-xl border"
                >
                    <div
                        class="bg-muted/40 hidden grid-cols-2 gap-6 border-b p-4 text-sm font-medium md:grid"
                    >
                        <span>Version du {{ date(selected.created_at) }}</span
                        ><span>Contenu actuel</span>
                    </div>
                    <div
                        v-for="field in fields"
                        :key="field.key"
                        class="space-y-3 border-b p-4 last:border-0 sm:p-5"
                    >
                        <h3 class="flex items-center gap-2 text-sm font-medium">
                            {{ field.label
                            }}<Badge v-if="changed(field.key)" variant="outline"
                                >Différent</Badge
                            >
                        </h3>
                        <div class="grid min-w-0 gap-5 md:grid-cols-2">
                            <div
                                v-for="side in ['previous', 'current'] as const"
                                :key="side"
                                class="min-w-0 rounded-lg p-3"
                                :class="changed(field.key) ? 'bg-muted/50' : ''"
                            >
                                <p
                                    class="text-muted-foreground mb-2 text-xs font-medium md:hidden"
                                >
                                    {{
                                        side === 'previous'
                                            ? 'Version sélectionnée'
                                            : 'Contenu actuel'
                                    }}
                                </p>
                                <div
                                    v-if="
                                        field.key === 'body' &&
                                        (side === 'previous'
                                            ? selected.body_html
                                            : current.body_html)
                                    "
                                    class="rich-content text-sm break-words"
                                    v-html="
                                        side === 'previous'
                                            ? selected.body_html
                                            : current.body_html
                                    "
                                />
                                <p
                                    v-else-if="field.key === 'page_template_id'"
                                    class="text-sm break-words"
                                >
                                    {{
                                        side === 'previous'
                                            ? selected.template_name
                                            : current.template_name
                                    }}
                                </p>
                                <p
                                    v-else-if="field.key === 'body_format'"
                                    class="text-sm"
                                >
                                    {{
                                        (side === 'previous'
                                            ? selected.content.body_format
                                            : current.body_format) === 'html'
                                            ? 'Texte enrichi'
                                            : 'Texte simple'
                                    }}
                                </p>
                                <p
                                    v-else
                                    class="text-sm break-words whitespace-pre-wrap"
                                >
                                    {{
                                        display(
                                            side === 'previous'
                                                ? selected.content[field.key]
                                                : current[field.key],
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
