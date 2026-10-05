<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowUp, ArrowDown, Plus, Trash2 } from '@lucide/vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/PageFieldSetController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Field,
    FieldLabel,
    FieldError,
    FieldDescription,
} from '@/components/ui/field';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import type { PageFieldSet, TemplateField } from '@/types/page-templates';

const props = defineProps<{ fieldSet?: PageFieldSet }>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const types: Record<TemplateField['type'], string> = {
    text: 'Texte',
    textarea: 'Texte long',
    number: 'Nombre',
    boolean: 'Interrupteur',
    select: 'Liste de choix',
    image: 'Image (URL)',
    collection: 'Collection de modèles',
};
const blank = (): TemplateField => ({
    key: '',
    label: '',
    type: 'text',
    required: false,
    options: [],
});
const form = useForm({
    name: props.fieldSet?.name ?? '',
    key: props.fieldSet?.key ?? '',
    fields: props.fieldSet
        ? props.fieldSet.fields.map((field) => ({
              ...field,
              options: [...(field.options ?? [])],
          }))
        : [blank()],
});
function move(index: number, direction: number): void {
    [form.fields[index], form.fields[index + direction]] = [
        form.fields[index + direction],
        form.fields[index],
    ];
}
function error(path: string): string | undefined {
    return (form.errors as Record<string, string>)[path];
}
function submit(): void {
    form.submit(props.fieldSet ? update(props.fieldSet.id) : store(), {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>
<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <Field
                ><FieldLabel for="set-name">Nom du groupe</FieldLabel
                ><Input
                    id="set-name"
                    v-model="form.name"
                    required
                    maxlength="255"
                /><FieldError>{{ form.errors.name }}</FieldError></Field
            ><Field
                ><FieldLabel for="set-key">Identifiant du groupe</FieldLabel
                ><Input
                    id="set-key"
                    v-model="form.key"
                    required
                    pattern="[a-z][a-z0-9_]*"
                    maxlength="64"
                    :readonly="Boolean(fieldSet?.templates_count)"
                    placeholder="presentation"
                /><FieldDescription
                    >Identifiant stable : lettres minuscules, chiffres et
                    _.</FieldDescription
                ><FieldError>{{ form.errors.key }}</FieldError></Field
            >
        </div>
        <p
            v-if="fieldSet?.templates_count"
            class="text-muted-foreground rounded-lg border p-3 text-sm"
        >
            Les modifications s’appliquent à tous les modèles utilisant ce
            groupe. Renommer ou retirer un champ retire sa valeur de l’affichage
            public.
        </p>
        <FieldError>{{ form.errors.fields }}</FieldError>
        <section
            v-for="(field, index) in form.fields"
            :key="index"
            class="space-y-4 rounded-xl border p-4"
        >
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">Champ {{ index + 1 }}</h3>
                <div class="flex gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="index === 0"
                        aria-label="Monter le champ"
                        @click="move(index, -1)"
                        ><ArrowUp class="size-4" /></Button
                    ><Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="index === form.fields.length - 1"
                        aria-label="Descendre le champ"
                        @click="move(index, 1)"
                        ><ArrowDown class="size-4" /></Button
                    ><Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="form.fields.length === 1"
                        aria-label="Retirer le champ"
                        @click="form.fields.splice(index, 1)"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field
                    ><FieldLabel :for="`label-${index}`">Libellé</FieldLabel
                    ><Input
                        :id="`label-${index}`"
                        v-model="field.label"
                        required
                        maxlength="255"
                    /><FieldError>{{
                        error(`fields.${index}.label`)
                    }}</FieldError></Field
                ><Field
                    ><FieldLabel :for="`key-${index}`">Identifiant</FieldLabel
                    ><Input
                        :id="`key-${index}`"
                        v-model="field.key"
                        required
                        pattern="[a-z][a-z0-9_]*"
                        maxlength="64"
                        placeholder="titre"
                    /><FieldError>{{
                        error(`fields.${index}.key`)
                    }}</FieldError></Field
                >
            </div>
            <Field
                ><FieldLabel :for="`type-${index}`">Type de champ</FieldLabel
                ><Select v-model="field.type"
                    ><SelectTrigger :id="`type-${index}`" class="w-full"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent
                        ><SelectItem
                            v-for="(label, key) in types"
                            :key="key"
                            :value="key"
                            >{{ label }}</SelectItem
                        ></SelectContent
                    ></Select
                ><FieldError>{{
                    error(`fields.${index}.type`)
                }}</FieldError></Field
            >
            <Field v-if="field.type === 'select'"
                ><FieldLabel :for="`options-${index}`"
                    >Choix (un par ligne)</FieldLabel
                ><Textarea
                    :id="`options-${index}`"
                    :model-value="(field.options ?? []).join('\n')"
                    @update:model-value="
                        field.options = String($event).split('\n')
                    "
                /><FieldError
                    v-for="(message, path) in form.errors"
                    :key="path"
                    v-show="String(path).startsWith(`fields.${index}.options`)"
                    >{{ message }}</FieldError
                ></Field
            >
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="field.required" type="checkbox" />Champ
                obligatoire</label
            >
        </section>
        <Button
            type="button"
            variant="outline"
            :disabled="form.fields.length >= 20"
            @click="form.fields.push(blank())"
            ><Plus class="size-4" />Ajouter un champ</Button
        >
        <div class="flex justify-end gap-3">
            <Button
                type="button"
                variant="outline"
                :disabled="form.processing"
                @click="emit('cancel')"
                >Annuler</Button
            ><Button :disabled="form.processing">{{
                form.processing ? 'Enregistrement…' : 'Enregistrer le groupe'
            }}</Button>
        </div>
    </form>
</template>
