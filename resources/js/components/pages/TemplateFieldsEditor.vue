<script setup lang="ts">
import {
    Field,
    FieldLabel,
    FieldError,
    FieldDescription,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import type {
    PageTemplate,
    TemplateValues,
    TemplateValue,
    TemplateQuery,
    TemplateSource,
} from '@/types/page-templates';

const props = defineProps<{
    template: PageTemplate;
    sources: TemplateSource[];
    errors: Record<string, string>;
    disabled: boolean;
}>();
const values = defineModel<TemplateValues>({ required: true });
function value(set: string, key: string): TemplateValue {
    return values.value[set]?.[key] ?? null;
}
function scalar(set: string, key: string): string | number {
    const current = value(set, key);
    return typeof current === 'string' || typeof current === 'number'
        ? current
        : '';
}
function setValue(set: string, key: string, next: TemplateValue): void {
    values.value = {
        ...values.value,
        [set]: { ...values.value[set], [key]: next },
    };
}
function query(set: string, key: string): TemplateQuery {
    const current = value(set, key);
    return current && typeof current === 'object'
        ? current
        : {
              source: props.sources[0]?.key ?? '',
              limit: 6,
              order_by: Object.keys(props.sources[0]?.orders ?? {})[0] ?? '',
              direction: 'desc',
          };
}
function setQuery(
    set: string,
    key: string,
    part: Partial<TemplateQuery>,
): void {
    const next = { ...query(set, key), ...part };
    if (part.source) {
        next.order_by =
            Object.keys(
                props.sources.find((source) => source.key === part.source)
                    ?.orders ?? {},
            )[0] ?? '';
    }
    setValue(set, key, next);
}
function errorsFor(set: string, key: string): string[] {
    const path = `template_fields.${set}.${key}`;
    return Object.entries(props.errors)
        .filter(([name]) => name === path || name.startsWith(`${path}.`))
        .map(([, error]) => error);
}
</script>

<template>
    <div class="space-y-5">
        <section
            v-for="set in template.field_sets"
            :key="set.id"
            class="bg-background space-y-5 rounded-xl border p-6"
        >
            <div>
                <h2 class="font-semibold">{{ set.name }}</h2>
                <p class="text-muted-foreground text-sm">
                    Champs du modèle « {{ template.name }} »
                </p>
            </div>
            <FieldError v-if="errors[`template_fields.${set.key}`]">{{
                errors[`template_fields.${set.key}`]
            }}</FieldError>
            <Field v-for="field in set.fields" :key="field.key">
                <FieldLabel :for="`field-${set.key}-${field.key}`"
                    >{{ field.label
                    }}<span v-if="field.required" aria-label="obligatoire">
                        *</span
                    ></FieldLabel
                >
                <Textarea
                    v-if="field.type === 'textarea'"
                    :id="`field-${set.key}-${field.key}`"
                    :model-value="String(scalar(set.key, field.key))"
                    :disabled="disabled"
                    :required="field.required"
                    @update:model-value="
                        setValue(set.key, field.key, String($event))
                    "
                />
                <Switch
                    v-else-if="field.type === 'boolean'"
                    :id="`field-${set.key}-${field.key}`"
                    :model-value="Boolean(value(set.key, field.key))"
                    :disabled="disabled"
                    @update:model-value="setValue(set.key, field.key, $event)"
                />
                <Select
                    v-else-if="field.type === 'select'"
                    :model-value="String(scalar(set.key, field.key))"
                    :disabled="disabled"
                    @update:model-value="
                        setValue(set.key, field.key, String($event))
                    "
                >
                    <SelectTrigger
                        :id="`field-${set.key}-${field.key}`"
                        class="w-full"
                        ><SelectValue placeholder="Choisir…" /></SelectTrigger
                    ><SelectContent
                        ><SelectItem
                            v-for="option in field.options"
                            :key="option"
                            :value="option"
                            >{{ option }}</SelectItem
                        ></SelectContent
                    >
                </Select>
                <div
                    v-else-if="field.type === 'collection'"
                    class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                >
                    <Field
                        ><FieldLabel :for="`field-${set.key}-${field.key}`"
                            >Source</FieldLabel
                        >
                        <Select
                            :model-value="query(set.key, field.key).source"
                            :disabled="disabled"
                            @update:model-value="
                                setQuery(set.key, field.key, {
                                    source: String($event),
                                })
                            "
                        >
                            <SelectTrigger
                                :id="`field-${set.key}-${field.key}`"
                                class="w-full"
                                ><SelectValue
                                    placeholder="Choisir une source" /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="source in sources"
                                    :key="source.key"
                                    :value="source.key"
                                    >{{ source.label }}</SelectItem
                                ></SelectContent
                            >
                        </Select>
                    </Field>
                    <Field
                        ><FieldLabel :for="`limit-${set.key}-${field.key}`"
                            >Nombre d’éléments</FieldLabel
                        ><Input
                            :id="`limit-${set.key}-${field.key}`"
                            type="number"
                            min="1"
                            max="100"
                            :disabled="disabled"
                            :model-value="query(set.key, field.key).limit"
                            @update:model-value="
                                setQuery(set.key, field.key, {
                                    limit: Number($event),
                                })
                            "
                    /></Field>
                    <Field
                        ><FieldLabel :for="`order-${set.key}-${field.key}`"
                            >Trier par</FieldLabel
                        >
                        <Select
                            :model-value="query(set.key, field.key).order_by"
                            :disabled="disabled"
                            @update:model-value="
                                setQuery(set.key, field.key, {
                                    order_by: String($event),
                                })
                            "
                        >
                            <SelectTrigger
                                :id="`order-${set.key}-${field.key}`"
                                class="w-full"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="(label, key) in sources.find(
                                        (source) =>
                                            source.key ===
                                            query(set.key, field.key).source,
                                    )?.orders"
                                    :key="key"
                                    :value="key"
                                    >{{ label }}</SelectItem
                                ></SelectContent
                            >
                        </Select>
                    </Field>
                    <Field
                        ><FieldLabel :for="`direction-${set.key}-${field.key}`"
                            >Ordre</FieldLabel
                        >
                        <Select
                            :model-value="query(set.key, field.key).direction"
                            :disabled="disabled"
                            @update:model-value="
                                setQuery(set.key, field.key, {
                                    direction: String($event),
                                })
                            "
                        >
                            <SelectTrigger
                                :id="`direction-${set.key}-${field.key}`"
                                class="w-full"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="desc"
                                    >Décroissant</SelectItem
                                ><SelectItem value="asc"
                                    >Croissant</SelectItem
                                ></SelectContent
                            >
                        </Select>
                    </Field>
                    <FieldDescription class="sm:col-span-2"
                        >Seuls les contenus publics sont affichés, dans la
                        limite de 100 éléments.</FieldDescription
                    >
                </div>
                <Input
                    v-else
                    :id="`field-${set.key}-${field.key}`"
                    :type="
                        field.type === 'number'
                            ? 'number'
                            : field.type === 'image'
                              ? 'url'
                              : 'text'
                    "
                    :step="field.type === 'number' ? 'any' : undefined"
                    :model-value="scalar(set.key, field.key)"
                    :required="field.required"
                    :disabled="disabled"
                    :placeholder="
                        field.type === 'image' ? 'https://…' : undefined
                    "
                    @update:model-value="
                        setValue(
                            set.key,
                            field.key,
                            $event === ''
                                ? null
                                : field.type === 'number'
                                  ? Number($event)
                                  : String($event),
                        )
                    "
                />
                <FieldDescription v-if="field.type === 'image'"
                    >Adresse HTTPS ou HTTP de l’image.</FieldDescription
                >
                <FieldError
                    v-for="error in errorsFor(set.key, field.key)"
                    :key="error"
                    >{{ error }}</FieldError
                >
            </Field>
        </section>
    </div>
</template>
