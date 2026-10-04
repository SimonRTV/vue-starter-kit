<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/ActivityController';
import { PageHeader } from '@/components/application';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { Pagination } from '@/types/pagination';

type Activity = {
    id: number;
    actor_name: string | null;
    subject_type: string;
    subject_id: string;
    event: string;
    changes: Record<string, { before: unknown; after: unknown }>;
    created_at: string;
};
type Filters = {
    type: string;
    event: string;
    subject_id: string;
    actor: string;
    from: string;
    to: string;
};
const props = defineProps<{
    activities: Pagination<Activity>;
    filters: Partial<Filters>;
    types: string[];
    events: string[];
    retentionDays: number;
    timezone: string;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Journal d’activité', href: index() }] },
});
const form = useForm<Filters>({
    type: '',
    event: '',
    subject_id: '',
    actor: '',
    from: '',
    to: '',
    ...props.filters,
});
function filter(): void {
    form.get(index.url(), { preserveScroll: true });
}
function display(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (value === '[redacted]') {
        return 'Valeur masquée';
    }

    return typeof value === 'string' ? value : JSON.stringify(value);
}
function date(value: string): string {
    return new Intl.DateTimeFormat('fr-CH', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: props.timezone,
    }).format(new Date(value));
}
const typeLabels: Record<string, string> = {
    pages: 'Pages',
    media: 'Médiathèque',
    settings: 'Paramètres',
    users: 'Utilisateurs',
    roles: 'Rôles',
};
const eventLabels: Record<string, string> = {
    created: 'Création',
    updated: 'Modification',
    deleted: 'Suppression',
};
</script>

<template>
    <div
        class="admin-page mx-auto flex w-full max-w-[1600px] flex-col gap-8 p-5 md:p-8 lg:p-10"
    >
        <PageHeader
            title="Journal d’activité"
            description="Consultez les changements effectués dans l’application."
        />
        <p class="text-muted-foreground text-sm">
            Les valeurs sensibles sont masquées. Les dates utilisent le fuseau
            {{ timezone }}.
            {{
                retentionDays > 0
                    ? `Conservation : ${retentionDays} jours.`
                    : 'Conservation sans limite de durée.'
            }}
        </p>
        <form
            class="admin-panel grid gap-5 rounded-2xl border p-6 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="filter"
        >
            <Field>
                <FieldLabel for="activity-type">Ressource</FieldLabel>
                <select
                    id="activity-type"
                    v-model="form.type"
                    class="bg-background h-9 rounded-md border px-3 text-sm"
                >
                    <option value="">Toutes les ressources</option>
                    <option v-for="type in types" :key="type" :value="type">
                        {{ typeLabels[type] ?? type }}
                    </option>
                </select>
                <FieldError
                    :errors="
                        form.errors.type ? [{ message: form.errors.type }] : []
                    "
                />
            </Field>
            <Field>
                <FieldLabel for="activity-event">Action</FieldLabel>
                <select
                    id="activity-event"
                    v-model="form.event"
                    class="bg-background h-9 rounded-md border px-3 text-sm"
                >
                    <option value="">Toutes les actions</option>
                    <option v-for="event in events" :key="event" :value="event">
                        {{ eventLabels[event] ?? event }}
                    </option>
                </select>
                <FieldError
                    :errors="
                        form.errors.event
                            ? [{ message: form.errors.event }]
                            : []
                    "
                />
            </Field>
            <Field>
                <FieldLabel for="activity-subject"
                    >Identifiant de la ressource</FieldLabel
                >
                <Input
                    id="activity-subject"
                    v-model="form.subject_id"
                    maxlength="255"
                />
                <FieldError
                    :errors="
                        form.errors.subject_id
                            ? [{ message: form.errors.subject_id }]
                            : []
                    "
                />
            </Field>
            <Field>
                <FieldLabel for="activity-actor">Auteur (nom exact)</FieldLabel>
                <Input
                    id="activity-actor"
                    v-model="form.actor"
                    maxlength="255"
                />
                <FieldError
                    :errors="
                        form.errors.actor
                            ? [{ message: form.errors.actor }]
                            : []
                    "
                />
            </Field>
            <Field>
                <FieldLabel for="activity-from">Du</FieldLabel>
                <Input id="activity-from" v-model="form.from" type="date" />
                <FieldError
                    :errors="
                        form.errors.from ? [{ message: form.errors.from }] : []
                    "
                />
            </Field>
            <Field>
                <FieldLabel for="activity-to">Au</FieldLabel>
                <Input id="activity-to" v-model="form.to" type="date" />
                <FieldError
                    :errors="
                        form.errors.to ? [{ message: form.errors.to }] : []
                    "
                />
            </Field>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                <Button type="submit" :disabled="form.processing"
                    >Filtrer</Button
                >
                <Button variant="outline" as-child
                    ><Link :href="index()">Réinitialiser</Link></Button
                >
            </div>
        </form>
        <p role="status" class="text-muted-foreground text-sm">
            {{ activities.total }} événement(s)
        </p>
        <div
            v-if="activities.data.length === 0"
            class="text-muted-foreground rounded-xl border border-dashed p-10 text-center"
        >
            Aucun événement ne correspond à ces critères.
        </div>
        <ol
            v-else
            class="admin-panel divide-y overflow-hidden rounded-2xl border"
        >
            <li
                v-for="activity in activities.data"
                :key="activity.id"
                class="space-y-4 p-6"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-2 text-sm"
                >
                    <div class="min-w-0 space-y-1">
                        <p class="font-medium">
                            {{ eventLabels[activity.event] ?? activity.event }}
                            ·
                            {{
                                typeLabels[activity.subject_type] ??
                                activity.subject_type
                            }}
                        </p>
                        <p class="text-muted-foreground break-all">
                            #{{ activity.subject_id }} ·
                            {{ activity.actor_name ?? 'Système' }}
                        </p>
                    </div>
                    <time
                        :datetime="activity.created_at"
                        class="text-muted-foreground"
                        >{{ date(activity.created_at) }}</time
                    >
                </div>
                <details
                    v-if="Object.keys(activity.changes).length"
                    class="text-sm"
                >
                    <summary class="cursor-pointer font-medium">
                        Voir les changements
                    </summary>
                    <dl class="mt-3 space-y-3">
                        <div
                            v-for="(change, field) in activity.changes"
                            :key="field"
                            class="bg-muted/50 grid gap-2 rounded-md p-3 sm:grid-cols-3"
                        >
                            <dt class="font-medium">{{ field }}</dt>
                            <dd class="min-w-0 break-words whitespace-pre-wrap">
                                <span class="text-muted-foreground"
                                    >Avant : </span
                                >{{ display(change.before) }}
                            </dd>
                            <dd class="min-w-0 break-words whitespace-pre-wrap">
                                <span class="text-muted-foreground"
                                    >Après : </span
                                >{{ display(change.after) }}
                            </dd>
                        </div>
                    </dl>
                </details>
            </li>
        </ol>
        <nav
            aria-label="Pagination du journal"
            class="flex items-center justify-between gap-4"
        >
            <Button
                v-if="activities.current_page > 1"
                variant="outline"
                as-child
            >
                <Link
                    :href="
                        index({
                            query: {
                                ...filters,
                                page: activities.current_page - 1,
                            },
                        })
                    "
                    >Précédent</Link
                >
            </Button>
            <span class="text-muted-foreground text-sm"
                >Page {{ activities.current_page }} sur
                {{ activities.last_page }}</span
            >
            <Button
                v-if="activities.current_page < activities.last_page"
                variant="outline"
                as-child
            >
                <Link
                    :href="
                        index({
                            query: {
                                ...filters,
                                page: activities.current_page + 1,
                            },
                        })
                    "
                    >Suivant</Link
                >
            </Button>
        </nav>
    </div>
</template>
