<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Plus,
    Pencil,
    Trash2,
    LayoutTemplate,
    ListFilter,
    ArrowLeft,
} from '@lucide/vue';
import { index as pagesIndex } from '@/actions/App/Http/Controllers/PageController';
import {
    index,
    destroy as deleteTemplate,
} from '@/actions/App/Http/Controllers/PageTemplateController';
import { destroy as deleteFieldSet } from '@/actions/App/Http/Controllers/PageFieldSetController';
import { PageHeader } from '@/components/application';
import ConfirmationAction from '@/components/application/ConfirmationAction.vue';
import FieldSetForm from '@/components/pages/FieldSetForm.vue';
import TemplateForm from '@/components/pages/TemplateForm.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type { PageFieldSet, PageTemplate } from '@/types/page-templates';

defineProps<{
    templates: PageTemplate[];
    fieldSets: PageFieldSet[];
    renderers: Record<string, { label: string; component: string }>;
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pages', href: pagesIndex() },
            { title: 'Modèles et champs', href: index() },
        ],
    },
});
const tab = ref<'templates' | 'fields'>('templates');
const templateOpen = ref(false);
const fieldSetOpen = ref(false);
const selectedTemplate = ref<PageTemplate>();
const selectedFieldSet = ref<PageFieldSet>();
const removal = ref<{
    kind: 'template' | 'field_set';
    id: number;
    name: string;
}>();
const deleteOpen = ref(false);
const deleteForm = useForm({});
function remove(): void {
    if (!removal.value) {
        return;
    }
    deleteForm.submit(
        removal.value.kind === 'template'
            ? deleteTemplate(removal.value.id)
            : deleteFieldSet(removal.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteOpen.value = false;
            },
            onError: () => {
                deleteOpen.value = false;
            },
        },
    );
}
</script>
<template>
    <main
        class="admin-page mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
    >
        <PageHeader
            title="Modèles et champs"
            description="Composez des pages uniques avec des mises en page et des groupes de champs réutilisables."
        >
            <template #actions
                ><Button variant="outline" as-child
                    ><Link :href="pagesIndex()"
                        ><ArrowLeft class="size-4" />Pages</Link
                    ></Button
                ><Button
                    @click="
                        tab === 'templates'
                            ? ((selectedTemplate = undefined),
                              (templateOpen = true))
                            : ((selectedFieldSet = undefined),
                              (fieldSetOpen = true))
                    "
                    ><Plus class="size-4" />{{
                        tab === 'templates'
                            ? 'Nouveau modèle'
                            : 'Nouveau groupe'
                    }}</Button
                ></template
            >
        </PageHeader>
        <div
            class="flex flex-wrap gap-2"
            role="group"
            aria-label="Gestion des modèles"
        >
            <Button
                :variant="tab === 'templates' ? 'secondary' : 'ghost'"
                :aria-pressed="tab === 'templates'"
                @click="tab = 'templates'"
                ><LayoutTemplate class="size-4" />Modèles
                <Badge variant="outline">{{ templates.length }}</Badge></Button
            ><Button
                :variant="tab === 'fields' ? 'secondary' : 'ghost'"
                :aria-pressed="tab === 'fields'"
                @click="tab = 'fields'"
                ><ListFilter class="size-4" />Groupes de champs
                <Badge variant="outline">{{ fieldSets.length }}</Badge></Button
            >
        </div>
        <p
            v-for="(error, key) in deleteForm.errors"
            :key="key"
            role="alert"
            class="text-destructive"
        >
            {{ error }}
        </p>
        <div v-if="tab === 'templates'" class="grid gap-4 md:grid-cols-2">
            <div
                v-if="!templates.length"
                class="text-muted-foreground rounded-xl border border-dashed p-10 text-center md:col-span-2"
            >
                Créez un groupe de champs, puis associez-le à votre premier
                modèle.
            </div>
            <article
                v-for="template in templates"
                :key="template.id"
                class="bg-card flex flex-col gap-4 rounded-xl border p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ template.name }}</h2>
                        <p class="text-muted-foreground text-sm">
                            {{
                                renderers[template.renderer]?.label ??
                                'Mise en page indisponible'
                            }}
                        </p>
                    </div>
                    <Badge variant="secondary"
                        >{{ template.pages_count }} page(s)</Badge
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="set in template.field_sets"
                        :key="set.id"
                        variant="outline"
                        >{{ set.name }}</Badge
                    ><span
                        v-if="!template.field_sets.length"
                        class="text-muted-foreground text-sm"
                        >Aucun groupe de champs</span
                    >
                </div>
                <div class="mt-auto flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="
                            selectedTemplate = template;
                            templateOpen = true;
                        "
                        ><Pencil class="size-4" />Modifier</Button
                    ><Button
                        variant="ghost"
                        size="sm"
                        :disabled="Boolean(template.pages_count)"
                        @click="
                            removal = {
                                kind: 'template',
                                id: template.id,
                                name: template.name,
                            };
                            deleteOpen = true;
                        "
                        ><Trash2 class="size-4" />Supprimer</Button
                    >
                </div>
                <p
                    v-if="template.pages_count"
                    class="text-muted-foreground text-xs"
                >
                    Pour supprimer ce modèle, changez d’abord le modèle des
                    pages qui l’utilisent.
                </p>
            </article>
        </div>
        <div v-else class="grid gap-4 md:grid-cols-2">
            <div
                v-if="!fieldSets.length"
                class="text-muted-foreground rounded-xl border border-dashed p-10 text-center md:col-span-2"
            >
                Regroupez vos champs : présentation, galerie, collection de
                contenus…
            </div>
            <article
                v-for="set in fieldSets"
                :key="set.id"
                class="bg-card flex flex-col gap-4 rounded-xl border p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ set.name }}</h2>
                        <p class="text-muted-foreground text-sm">
                            {{ set.fields.length }} champ(s) ·
                            {{ set.templates_count }} modèle(s)
                        </p>
                    </div>
                    <Badge variant="outline">{{ set.key }}</Badge>
                </div>
                <p class="text-muted-foreground text-sm">
                    {{ set.fields.map((field) => field.label).join(', ') }}
                </p>
                <div class="mt-auto flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="
                            selectedFieldSet = set;
                            fieldSetOpen = true;
                        "
                        ><Pencil class="size-4" />Modifier</Button
                    ><Button
                        variant="ghost"
                        size="sm"
                        :disabled="Boolean(set.templates_count)"
                        @click="
                            removal = {
                                kind: 'field_set',
                                id: set.id,
                                name: set.name,
                            };
                            deleteOpen = true;
                        "
                        ><Trash2 class="size-4" />Supprimer</Button
                    >
                </div>
                <p
                    v-if="set.templates_count"
                    class="text-muted-foreground text-xs"
                >
                    Retirez ce groupe des modèles avant de le supprimer.
                </p>
            </article>
        </div>
        <Dialog v-model:open="templateOpen"
            ><DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                ><DialogHeader
                    ><DialogTitle>{{
                        selectedTemplate
                            ? 'Modifier le modèle'
                            : 'Nouveau modèle'
                    }}</DialogTitle
                    ><DialogDescription
                        >Choisissez une mise en page et les groupes de champs à
                        utiliser.</DialogDescription
                    ></DialogHeader
                ><TemplateForm
                    v-if="templateOpen"
                    :key="selectedTemplate?.id ?? 'new'"
                    :template="selectedTemplate"
                    :field-sets="fieldSets"
                    :renderers="renderers"
                    @saved="templateOpen = false"
                    @cancel="templateOpen = false" /></DialogContent
        ></Dialog>
        <Dialog v-model:open="fieldSetOpen"
            ><DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl"
                ><DialogHeader
                    ><DialogTitle>{{
                        selectedFieldSet
                            ? 'Modifier le groupe'
                            : 'Nouveau groupe de champs'
                    }}</DialogTitle
                    ><DialogDescription
                        >Définissez les champs que vos pages pourront
                        remplir.</DialogDescription
                    ></DialogHeader
                ><FieldSetForm
                    v-if="fieldSetOpen"
                    :key="selectedFieldSet?.id ?? 'new'"
                    :field-set="selectedFieldSet"
                    @saved="fieldSetOpen = false"
                    @cancel="fieldSetOpen = false" /></DialogContent
        ></Dialog>
        <ConfirmationAction
            v-model:open="deleteOpen"
            title="Supprimer cet élément ?"
            :description="`« ${removal?.name ?? ''} » sera supprimé définitivement.`"
            confirm-label="Supprimer"
            :processing="deleteForm.processing"
            @confirm="remove"
        />
    </main>
</template>
