<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bookmark, Download } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { parseTableViews, saveTableView } from '@/lib/tableViews';
import type { SavedTableView, TableFilters } from '@/lib/tableViews';

const props = defineProps<{
    tableKey: string;
    filters: TableFilters;
    url: (filters: TableFilters) => string;
    exportUrl: string;
    total: number;
    disabled?: boolean;
}>();
const page = usePage();
const storageKey = `table-views:v1:${page.props.auth.user.id}:${props.tableKey}`;
const views = ref<SavedTableView[]>([]);
const selected = ref('');
const name = ref('');
const error = ref('');
const open = ref(false);
onMounted(() => {
    try {
        views.value = parseTableViews(
            localStorage.getItem(storageKey),
            Object.keys(props.filters),
        );
    } catch {
        error.value =
            'Le stockage des vues est indisponible dans ce navigateur.';
    }
});
function persist(next: SavedTableView[]): boolean {
    try {
        localStorage.setItem(storageKey, JSON.stringify(next));
        views.value = next;
        error.value = '';

        return true;
    } catch {
        error.value = 'Impossible d’enregistrer les vues dans ce navigateur.';

        return false;
    }
}
function save(): void {
    try {
        if (persist(saveTableView(views.value, name.value, props.filters))) {
            open.value = false;
            name.value = '';
        }
    } catch (exception) {
        error.value =
            exception instanceof Error
                ? exception.message
                : 'Impossible d’enregistrer la vue.';
    }
}
function apply(): void {
    const view = views.value.find((item) => item.name === selected.value);

    if (view) {
        router.get(props.url(view.filters), {}, { preserveScroll: true });
    }
}
function remove(): void {
    if (persist(views.value.filter((view) => view.name !== selected.value))) {
        selected.value = '';
    }
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <select
                v-if="views.length"
                v-model="selected"
                aria-label="Vues enregistrées"
                class="bg-background h-9 max-w-56 rounded-md border px-3 text-sm"
                :disabled="disabled"
            >
                <option value="">Vues enregistrées</option>
                <option
                    v-for="view in views"
                    :key="view.name"
                    :value="view.name"
                >
                    {{ view.name }}
                </option>
            </select>
            <Button
                v-if="selected"
                type="button"
                variant="outline"
                :disabled="disabled"
                @click="apply"
                >Appliquer</Button
            >
            <Button
                v-if="selected"
                type="button"
                variant="ghost"
                :disabled="disabled"
                @click="remove"
                >Oublier cette vue</Button
            >
            <Button
                type="button"
                variant="outline"
                :disabled="disabled"
                @click="
                    open = true;
                    error = '';
                "
                ><Bookmark class="size-4" />Enregistrer la vue</Button
            >
            <Button
                v-if="total <= 10000 && !disabled"
                variant="outline"
                as-child
                ><a :href="exportUrl"
                    ><Download class="size-4" />Exporter en CSV</a
                ></Button
            >
            <Button v-else variant="outline" disabled
                ><Download class="size-4" />Exporter en CSV</Button
            >
        </div>
        <p class="text-muted-foreground text-xs">
            Vues privées enregistrées dans ce navigateur. L’export inclut tous
            les résultats filtrés, jusqu’à 10 000 lignes.
        </p>
        <p v-if="error && !open" role="alert" class="text-destructive text-sm">
            {{ error }}
        </p>
        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader
                    ><DialogTitle>Enregistrer cette vue</DialogTitle
                    ><DialogDescription
                        >Conservez les filtres, le tri et le nombre de lignes
                        actuellement appliqués.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="save">
                    <label for="table-view-name" class="text-sm font-medium"
                        >Nom de la vue</label
                    >
                    <Input
                        id="table-view-name"
                        v-model="name"
                        maxlength="60"
                        required
                        placeholder="Mes brouillons récents"
                    />
                    <p
                        v-if="error"
                        role="alert"
                        class="text-destructive text-sm"
                    >
                        {{ error }}
                    </p>
                    <Button type="submit">Enregistrer</Button>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
