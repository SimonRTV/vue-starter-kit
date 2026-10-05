<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type {
    PageTemplate,
    TemplateValues,
    TemplateCollections,
} from '@/types/page-templates';
defineProps<{
    template: PageTemplate;
    fields: TemplateValues;
    collections: TemplateCollections;
    compact?: boolean;
}>();
</script>
<template>
    <div class="space-y-12">
        <section
            v-for="set in template.field_sets"
            :key="set.id"
            class="space-y-8"
            :aria-label="set.name"
        >
            <div v-for="field in set.fields" :key="field.key">
                <template v-if="field.type === 'collection'">
                    <h2 class="mb-6 text-2xl font-semibold tracking-tight">
                        {{ field.label }}
                    </h2>
                    <div
                        v-if="collections[set.key]?.[field.key]?.length"
                        class="grid gap-6"
                        :class="
                            compact
                                ? 'sm:grid-cols-2'
                                : 'sm:grid-cols-2 lg:grid-cols-3'
                        "
                    >
                        <Link
                            v-for="item in collections[set.key][field.key]"
                            :key="item.url"
                            :href="item.url"
                            class="bg-card hover:border-primary/40 group overflow-hidden rounded-2xl border transition-colors"
                        >
                            <img
                                v-if="item.image"
                                :src="item.image"
                                alt=""
                                loading="lazy"
                                class="aspect-[4/3] w-full object-cover"
                            />
                            <div class="space-y-3 p-6">
                                <h3
                                    class="text-xl font-semibold tracking-tight group-hover:underline"
                                >
                                    {{ item.title }}
                                </h3>
                                <p
                                    v-if="item.excerpt"
                                    class="text-muted-foreground text-sm leading-6"
                                >
                                    {{ item.excerpt }}
                                </p>
                            </div>
                        </Link>
                    </div>
                    <p
                        v-else
                        class="text-muted-foreground rounded-xl border border-dashed p-8"
                    >
                        Aucun contenu disponible pour le moment.
                    </p>
                </template>
                <img
                    v-else-if="
                        field.type === 'image' && fields[set.key]?.[field.key]
                    "
                    :src="String(fields[set.key][field.key])"
                    :alt="field.label"
                    loading="lazy"
                    class="max-h-[36rem] w-full rounded-2xl object-cover"
                />
                <div
                    v-else-if="
                        fields[set.key]?.[field.key] !== null &&
                        fields[set.key]?.[field.key] !== undefined &&
                        fields[set.key]?.[field.key] !== ''
                    "
                    class="space-y-2"
                >
                    <h2 class="text-xl font-semibold">{{ field.label }}</h2>
                    <p
                        class="text-muted-foreground leading-8 whitespace-pre-line"
                    >
                        {{
                            field.type === 'boolean'
                                ? fields[set.key][field.key]
                                    ? 'Oui'
                                    : 'Non'
                                : fields[set.key][field.key]
                        }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
