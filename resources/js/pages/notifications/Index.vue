<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroy,
    index,
    preferences,
    readAll,
    update,
} from '@/actions/App/Http/Controllers/NotificationController';
import { ConfirmationAction, PageHeader } from '@/components/application';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import type { Pagination } from '@/types/pagination';

type NotificationItem = {
    id: string;
    data: {
        category: string;
        title: string;
        body: string;
        action_path: string | null;
    };
    read_at: string | null;
    created_at: string;
};
const props = defineProps<{
    items: Pagination<NotificationItem>;
    filters: { status: string; category: string | null };
    categories: Record<string, { label: string; description: string }>;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifications', href: index() }] },
});
const page = usePage();
const status = ref(props.filters.status);
const category = ref(props.filters.category ?? '');
const readForm = useForm({ read: true });
const deleteForm = useForm({});
const confirmOpen = ref(false);
const deleting = ref<NotificationItem | null>(null);
const busy = computed(() => readForm.processing || deleteForm.processing);
function filter(): void {
    router.get(
        index.url(),
        { status: status.value, category: category.value },
        { preserveState: true },
    );
}
function mark(item: NotificationItem): void {
    readForm.read = item.read_at === null;
    readForm.patch(update.url(item.id), { preserveScroll: true });
}
function remove(): void {
    if (!deleting.value) {
        return;
    }

    deleteForm.delete(destroy.url(deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmOpen.value = false;
        },
    });
}
function date(value: string): string {
    return new Intl.DateTimeFormat('fr-CH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
        <PageHeader
            title="Notifications"
            description="Retrouvez les informations et les mises à jour qui vous concernent."
        />
        <div class="flex flex-wrap gap-2">
            <Button variant="outline" as-child
                ><Link :href="preferences()">Préférences</Link></Button
            >
            <Button
                variant="outline"
                :disabled="busy || page.props.notifications.unreadCount === 0"
                @click="readForm.patch(readAll.url(), { preserveScroll: true })"
                >Tout marquer comme lu</Button
            >
            <Button
                variant="ghost"
                @click="router.reload({ only: ['items', 'notifications'] })"
                >Actualiser</Button
            >
        </div>
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="filter">
            <Field class="min-w-40 flex-1">
                <FieldLabel for="notification-status">État</FieldLabel>
                <select
                    id="notification-status"
                    v-model="status"
                    class="bg-background h-9 rounded-md border px-3 text-sm"
                >
                    <option value="all">Toutes</option>
                    <option value="unread">Non lues</option>
                    <option value="read">Lues</option>
                </select>
            </Field>
            <Field class="min-w-40 flex-1">
                <FieldLabel for="notification-category">Catégorie</FieldLabel>
                <select
                    id="notification-category"
                    v-model="category"
                    class="bg-background h-9 rounded-md border px-3 text-sm"
                >
                    <option value="">Toutes les catégories</option>
                    <option
                        v-for="(definition, key) in categories"
                        :key="key"
                        :value="key"
                    >
                        {{ definition.label }}
                    </option>
                </select>
            </Field>
            <Button type="submit" variant="outline">Filtrer</Button>
        </form>
        <p role="status" class="text-muted-foreground text-sm">
            {{ items.total }} notification(s) ·
            {{ page.props.notifications.unreadCount }} non lue(s)
        </p>
        <div
            v-if="!items.data.length"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Bell class="size-8" aria-hidden="true" />
            <p>Aucune notification à afficher.</p>
        </div>
        <ol v-else class="space-y-3">
            <li
                v-for="item in items.data"
                :key="item.id"
                class="space-y-3 rounded-xl border p-4 sm:p-5"
                :class="
                    item.read_at === null
                        ? 'border-primary/30 bg-primary/5'
                        : ''
                "
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge v-if="item.read_at === null">Non lue</Badge>
                        <span class="text-muted-foreground text-xs">{{
                            categories[item.data.category]?.label ??
                            item.data.category
                        }}</span>
                    </div>
                    <time
                        :datetime="item.created_at"
                        class="text-muted-foreground text-xs"
                        >{{ date(item.created_at) }}</time
                    >
                </div>
                <h2 class="font-semibold break-words">{{ item.data.title }}</h2>
                <p
                    class="text-muted-foreground text-sm break-words whitespace-pre-wrap"
                >
                    {{ item.data.body }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="item.data.action_path"
                        variant="outline"
                        size="sm"
                        as-child
                        ><Link :href="item.data.action_path"
                            >Consulter</Link
                        ></Button
                    >
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="busy"
                        @click="mark(item)"
                        >{{
                            item.read_at === null
                                ? 'Marquer comme lue'
                                : 'Marquer comme non lue'
                        }}</Button
                    >
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="busy"
                        @click="
                            deleting = item;
                            confirmOpen = true;
                        "
                        >Supprimer</Button
                    >
                </div>
            </li>
        </ol>
        <nav
            class="flex items-center justify-between gap-3"
            aria-label="Pagination des notifications"
        >
            <Button v-if="items.current_page > 1" variant="outline" as-child
                ><Link
                    :href="
                        index({
                            query: { ...filters, page: items.current_page - 1 },
                        })
                    "
                    >Précédent</Link
                ></Button
            >
            <span class="text-muted-foreground text-sm"
                >Page {{ items.current_page }} sur {{ items.last_page }}</span
            >
            <Button
                v-if="items.current_page < items.last_page"
                variant="outline"
                as-child
                ><Link
                    :href="
                        index({
                            query: { ...filters, page: items.current_page + 1 },
                        })
                    "
                    >Suivant</Link
                ></Button
            >
        </nav>
        <ConfirmationAction
            v-model:open="confirmOpen"
            title="Supprimer cette notification ?"
            description="La notification sera retirée définitivement de votre boîte de réception."
            confirm-label="Supprimer"
            :processing="deleteForm.processing"
            @confirm="remove"
        />
    </div>
</template>
