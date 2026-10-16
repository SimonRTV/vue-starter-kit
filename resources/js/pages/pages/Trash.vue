<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, RotateCcw, Search, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { index as pagesIndex } from '@/actions/App/Http/Controllers/PageController';
import {
    destroy,
    index,
    restore,
} from '@/actions/App/Http/Controllers/PageTrashController';
import { ConfirmationAction, PageHeader } from '@/components/application';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Pagination } from '@/types/pagination';

type TrashedPage = {
    id: number;
    title: string;
    slug: string;
    deleted_at: string;
};
const props = defineProps<{
    pages: Pagination<TrashedPage>;
    search: string | null;
    timezone: string;
}>();
const filter = useForm({ search: props.search ?? '' });
const action = useForm({});
const selected = ref<TrashedPage | null>(null);
const operation = ref<'restore' | 'delete'>('restore');
const confirmationOpen = ref(false);
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pages', href: pagesIndex() },
            { title: 'Corbeille', href: index() },
        ],
    },
});
function confirm(page: TrashedPage, type: 'restore' | 'delete'): void {
    selected.value = page;
    operation.value = type;
    confirmationOpen.value = true;
}
function submit(): void {
    if (!selected.value) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            confirmationOpen.value = false;
        },
    };
    if (operation.value === 'restore')
        action.post(restore.url(selected.value.id), options);
    else action.delete(destroy.url(selected.value.id), options);
}
function date(value: string): string {
    return new Intl.DateTimeFormat('fr-CH', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: props.timezone,
    }).format(new Date(value));
}
</script>

<template>
    <main
        class="admin-page mx-auto flex w-full max-w-[1600px] min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
    >
        <PageHeader
            title="Corbeille des pages"
            description="Retrouvez les pages supprimées et restaurez-les lorsque vous en avez besoin."
        >
            <template #actions
                ><Button variant="outline" as-child
                    ><Link :href="pagesIndex()"
                        ><ArrowLeft class="size-4" />Retour aux pages</Link
                    ></Button
                ></template
            >
        </PageHeader>
        <p class="text-muted-foreground max-w-3xl text-sm leading-relaxed">
            Les pages restent dans la corbeille jusqu’à leur suppression
            définitive. Leur contenu, leurs versions et leurs pièces jointes
            sont conservés. Une page restaurée revient en brouillon, sans être
            remise en ligne.
        </p>
        <form
            class="flex max-w-lg flex-wrap items-end gap-3"
            @submit.prevent="filter.get(index.url(), { preserveScroll: true })"
        >
            <div class="min-w-0 flex-1 space-y-2">
                <label for="trash-search" class="text-sm font-medium"
                    >Rechercher une page</label
                ><Input
                    id="trash-search"
                    v-model="filter.search"
                    placeholder="Titre ou adresse…"
                />
                <p
                    v-if="filter.errors.search"
                    role="alert"
                    class="text-destructive text-sm"
                >
                    {{ filter.errors.search }}
                </p>
            </div>
            <Button
                type="submit"
                variant="outline"
                :disabled="filter.processing"
                ><Search class="size-4" />Rechercher</Button
            >
            <Button v-if="search" variant="ghost" as-child
                ><Link :href="index()">Effacer</Link></Button
            >
        </form>
        <p role="status" class="text-muted-foreground text-sm">
            {{ pages.total }} page(s) dans la corbeille{{
                search ? ' pour cette recherche' : ''
            }}
        </p>
        <div
            v-if="!pages.data.length"
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed p-10 text-center"
        >
            <Trash2 class="text-muted-foreground size-8" />
            <h2 class="font-medium">
                {{ search ? 'Aucune page trouvée' : 'La corbeille est vide' }}
            </h2>
            <p class="text-muted-foreground text-sm">
                {{
                    search
                        ? 'Essayez un autre titre ou une autre adresse.'
                        : 'Les pages que vous supprimez apparaîtront ici.'
                }}
            </p>
        </div>
        <ul
            v-else
            class="admin-panel divide-y overflow-hidden rounded-2xl border"
        >
            <li
                v-for="page in pages.data"
                :key="page.id"
                class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0 space-y-1">
                    <h2 class="font-medium break-words">{{ page.title }}</h2>
                    <p class="text-muted-foreground text-sm break-all">
                        {{ page.slug }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                        Mise à la corbeille le
                        <time :datetime="page.deleted_at">{{
                            date(page.deleted_at)
                        }}</time>
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <Button
                        variant="outline"
                        :disabled="action.processing"
                        @click="confirm(page, 'restore')"
                        ><RotateCcw class="size-4" />Restaurer</Button
                    ><Button
                        variant="ghost"
                        class="text-destructive hover:text-destructive"
                        :disabled="action.processing"
                        @click="confirm(page, 'delete')"
                        ><Trash2 class="size-4" />Supprimer
                        définitivement</Button
                    >
                </div>
            </li>
        </ul>
        <nav
            v-if="pages.last_page > 1"
            aria-label="Pagination de la corbeille"
            class="flex items-center justify-between gap-3"
        >
            <Button v-if="pages.current_page > 1" variant="outline" as-child
                ><Link
                    :href="
                        index({
                            query: { search, page: pages.current_page - 1 },
                        })
                    "
                    >Précédent</Link
                ></Button
            >
            <span class="text-muted-foreground text-sm"
                >Page {{ pages.current_page }} sur {{ pages.last_page }}</span
            >
            <Button
                v-if="pages.current_page < pages.last_page"
                variant="outline"
                as-child
                ><Link
                    :href="
                        index({
                            query: { search, page: pages.current_page + 1 },
                        })
                    "
                    >Suivant</Link
                ></Button
            >
        </nav>
        <ConfirmationAction
            v-model:open="confirmationOpen"
            :title="`${operation === 'restore' ? 'Restaurer' : 'Supprimer définitivement'} « ${selected?.title ?? ''} » ?`"
            :description="
                operation === 'restore'
                    ? 'La page sera restaurée en brouillon avec son historique et ses pièces jointes. Vous pourrez la vérifier avant de la publier.'
                    : 'La page et toutes ses versions seront supprimées sans possibilité de récupération. Les fichiers restent disponibles dans la médiathèque.'
            "
            :confirm-label="
                operation === 'restore'
                    ? 'Restaurer en brouillon'
                    : 'Supprimer définitivement'
            "
            :confirm-variant="
                operation === 'restore' ? 'default' : 'destructive'
            "
            :processing="action.processing"
            @confirm="submit"
        />
    </main>
</template>
