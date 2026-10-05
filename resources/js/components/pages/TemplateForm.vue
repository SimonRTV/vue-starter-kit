<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowUp, ArrowDown } from '@lucide/vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/PageTemplateController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Field,
    FieldLabel,
    FieldDescription,
    FieldError,
} from '@/components/ui/field';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import type { PageTemplate, PageFieldSet } from '@/types/page-templates';

const props = defineProps<{
    template?: PageTemplate;
    fieldSets: PageFieldSet[];
    renderers: Record<string, { label: string; component: string }>;
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const form = useForm({
    name: props.template?.name ?? '',
    renderer: props.template?.renderer ?? Object.keys(props.renderers)[0] ?? '',
    field_set_ids:
        props.template?.field_sets.map((set) => set.id) ?? ([] as number[]),
});
function toggleSet(id: number): void {
    form.field_set_ids = form.field_set_ids.includes(id)
        ? form.field_set_ids.filter((value) => value !== id)
        : [...form.field_set_ids, id];
}
function move(index: number, direction: number): void {
    const ids = [...form.field_set_ids];
    [ids[index], ids[index + direction]] = [ids[index + direction], ids[index]];
    form.field_set_ids = ids;
}
function submit(): void {
    form.submit(props.template ? update(props.template.id) : store(), {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>
<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Field
            ><FieldLabel for="template-name">Nom du modèle</FieldLabel
            ><Input
                id="template-name"
                v-model="form.name"
                required
                maxlength="255"
            /><FieldError>{{ form.errors.name }}</FieldError></Field
        >
        <Field
            ><FieldLabel for="template-renderer">Mise en page</FieldLabel
            ><Select v-model="form.renderer"
                ><SelectTrigger id="template-renderer" class="w-full"
                    ><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem
                        v-for="(renderer, key) in renderers"
                        :key="key"
                        :value="key"
                        >{{ renderer.label }}</SelectItem
                    ></SelectContent
                ></Select
            ><FieldError>{{ form.errors.renderer }}</FieldError></Field
        >
        <Field
            ><FieldLabel>Groupes de champs</FieldLabel
            ><FieldDescription
                >Sélectionnez les groupes à remplir dans l’éditeur de
                page.</FieldDescription
            >
            <p v-if="!fieldSets.length" class="text-muted-foreground text-sm">
                Créez d’abord un groupe dans l’onglet « Groupes de champs ».
            </p>
            <label
                v-for="set in fieldSets"
                :key="set.id"
                class="flex items-center gap-3 rounded-lg border p-3"
                ><input
                    type="checkbox"
                    :checked="form.field_set_ids.includes(set.id)"
                    @change="toggleSet(set.id)"
                /><span>{{ set.name }}</span></label
            >
            <FieldError>{{ form.errors.field_set_ids }}</FieldError>
        </Field>
        <div v-if="form.field_set_ids.length" class="space-y-2">
            <p class="text-sm font-medium">Ordre des groupes</p>
            <div
                v-for="(id, index) in form.field_set_ids"
                :key="id"
                class="flex items-center justify-between gap-3 rounded-lg border p-2"
            >
                <span class="text-sm">{{
                    fieldSets.find((set) => set.id === id)?.name
                }}</span>
                <div class="flex gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="index === 0"
                        aria-label="Monter le groupe"
                        @click="move(index, -1)"
                        ><ArrowUp class="size-4" /></Button
                    ><Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="index === form.field_set_ids.length - 1"
                        aria-label="Descendre le groupe"
                        @click="move(index, 1)"
                        ><ArrowDown class="size-4"
                    /></Button>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3">
            <Button
                type="button"
                variant="outline"
                :disabled="form.processing"
                @click="emit('cancel')"
                >Annuler</Button
            ><Button :disabled="form.processing">{{
                form.processing ? 'Enregistrement…' : 'Enregistrer le modèle'
            }}</Button>
        </div>
    </form>
</template>
