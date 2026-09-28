<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Download, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ImportBatch, ImportField } from '@/types/imports';

const props = defineProps<{
    fields: ImportField[];
    columns: string[];
    batch: ImportBatch | null;
    canUpdate: boolean;
    uploadUrl: string;
    previewUrl?: string;
    commitUrl?: string;
    errorsUrl?: string;
    destroyUrl?: string;
    returnUrl: string;
    mappingHelp: string;
}>();
const upload = useForm<{ file: File | null; delimiter: string }>({
    file: null,
    delimiter: ';',
});
const mapping = useForm({
    mapping: {} as Record<string, string>,
    duplicate_mode: 'skip',
});
const confirmation = useForm({ preview_token: '' });
const cancellation = useForm({});
const pageNumber = ref(1);
const actionLabels: Record<string, string> = {
    create: 'Créer',
    update: 'Mettre à jour',
    skip: 'Ignorer',
    error: 'Erreur',
};
watch(
    () => props.batch,
    (batch) => {
        mapping.mapping = Object.fromEntries(
            props.fields.map((field) => [
                field.key,
                String(batch?.mapping[field.key] ?? 'none'),
            ]),
        );
        mapping.duplicate_mode = batch?.duplicateMode ?? 'skip';
        confirmation.preview_token = batch?.previewToken ?? '';
        pageNumber.value = 1;
    },
    { immediate: true },
);
const normalizedMapping = computed(() =>
    Object.fromEntries(
        Object.entries(mapping.mapping).map(([key, value]) => [
            key,
            value === 'none' ? null : Number(value),
        ]),
    ),
);
const changed = computed(
    () =>
        props.batch !== null &&
        (mapping.duplicate_mode !== props.batch.duplicateMode ||
            props.fields.some(
                (field) =>
                    normalizedMapping.value[field.key] !==
                    (props.batch?.mapping[field.key] ?? null),
            )),
);
const busy = computed(
    () =>
        upload.processing ||
        mapping.processing ||
        confirmation.processing ||
        cancellation.processing,
);
const errors = computed(() => [
    ...Object.values(upload.errors),
    ...Object.values(mapping.errors),
    ...Object.values(confirmation.errors),
    ...Object.values(cancellation.errors),
]);
const rows = computed(
    () =>
        props.batch?.preview?.rows.slice(
            (pageNumber.value - 1) * 20,
            pageNumber.value * 20,
        ) ?? [],
);
const totalPages = computed(() =>
    Math.max(1, Math.ceil((props.batch?.preview?.rows.length ?? 0) / 20)),
);
function preview(): void {
    if (!props.previewUrl) return;
    confirmation.clearErrors();
    mapping
        .transform(() => ({
            mapping: normalizedMapping.value,
            duplicate_mode: mapping.duplicate_mode,
        }))
        .patch(props.previewUrl, { preserveScroll: true });
}
function confirm(): void {
    if (props.commitUrl && !changed.value)
        confirmation.post(props.commitUrl, { preserveScroll: true });
}
function cancel(): void {
    if (props.destroyUrl) cancellation.delete(props.destroyUrl);
}
</script>

<template>
    <div class="flex flex-col gap-6" :aria-busy="busy">
        <Alert v-if="errors.length" variant="destructive" role="alert">
            <AlertTitle>Vérifiez les informations</AlertTitle>
            <AlertDescription
                ><ul class="list-inside list-disc">
                    <li v-for="(error, index) in errors" :key="index">
                        {{ error }}
                    </li>
                </ul></AlertDescription
            >
        </Alert>
        <Card v-if="!batch">
            <CardHeader
                ><CardTitle>1. Choisir le fichier</CardTitle
                ><CardDescription
                    >CSV UTF-8, 2 Mo maximum et 1 000 lignes. La première ligne
                    contient les en-têtes.</CardDescription
                ></CardHeader
            >
            <CardContent>
                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="upload.post(uploadUrl)"
                >
                    <FieldGroup>
                        <Field :data-invalid="!!upload.errors.file">
                            <FieldLabel for="csv-file">Fichier CSV</FieldLabel>
                            <Input
                                id="csv-file"
                                type="file"
                                accept=".csv,.txt,text/csv"
                                :disabled="busy"
                                :aria-invalid="!!upload.errors.file"
                                required
                                @change="
                                    upload.file =
                                        ($event.target as HTMLInputElement)
                                            .files?.[0] ?? null
                                "
                            />
                        </Field>
                        <Field>
                            <FieldLabel for="csv-delimiter"
                                >Séparateur</FieldLabel
                            >
                            <Select v-model="upload.delimiter" :disabled="busy"
                                ><SelectTrigger id="csv-delimiter"
                                    ><SelectValue /></SelectTrigger
                                ><SelectContent
                                    ><SelectGroup>
                                        <SelectItem value=";"
                                            >Point-virgule (;)</SelectItem
                                        ><SelectItem value=","
                                            >Virgule (,)</SelectItem
                                        ><SelectItem value="tab"
                                            >Tabulation</SelectItem
                                        >
                                    </SelectGroup></SelectContent
                                ></Select
                            >
                            <FieldDescription
                                >Choisissez « Virgule » pour réimporter un
                                export de l’application.</FieldDescription
                            >
                        </Field>
                    </FieldGroup>
                    <progress
                        v-if="upload.progress"
                        class="w-full"
                        :value="upload.progress.percentage"
                        max="100"
                        aria-label="Progression du transfert"
                    />
                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" :disabled="busy || !upload.file"
                            ><Upload data-icon="inline-start" />{{
                                upload.processing ? 'Chargement…' : 'Continuer'
                            }}</Button
                        ><Button variant="outline" as-child
                            ><Link :href="returnUrl">Annuler</Link></Button
                        >
                    </div>
                </form>
            </CardContent>
        </Card>
        <template v-else-if="batch.completed">
            <Alert role="status"
                ><AlertTitle>Import terminé</AlertTitle
                ><AlertDescription
                    >{{ batch.preview?.counts.create }} création(s),
                    {{ batch.preview?.counts.update }} mise(s) à jour et
                    {{ batch.preview?.counts.skip }} ligne(s) ignorée(s). Les
                    données du fichier ont été supprimées.</AlertDescription
                ></Alert
            >
            <div>
                <Button as-child
                    ><Link :href="returnUrl">Retour à la liste</Link></Button
                >
            </div>
        </template>
        <template v-else>
            <p class="text-muted-foreground text-sm">
                {{ batch.total }} ligne(s) chargée(s). Cet import privé expire
                le {{ new Date(batch.expiresAt).toLocaleString('fr-CH') }}.
            </p>
            <Card>
                <CardHeader
                    ><CardTitle>2. Associer les colonnes</CardTitle
                    ><CardDescription>{{
                        mappingHelp
                    }}</CardDescription></CardHeader
                >
                <CardContent>
                    <form class="flex flex-col gap-6" @submit.prevent="preview">
                        <FieldGroup class="grid gap-4 md:grid-cols-2">
                            <Field v-for="field in fields" :key="field.key">
                                <FieldLabel :for="`mapping-${field.key}`"
                                    >{{ field.label }}
                                    {{ field.required ? '*' : '' }}</FieldLabel
                                >
                                <Select
                                    v-model="mapping.mapping[field.key]"
                                    :disabled="busy"
                                    ><SelectTrigger :id="`mapping-${field.key}`"
                                        ><SelectValue /></SelectTrigger
                                    ><SelectContent
                                        ><SelectGroup>
                                            <SelectItem value="none">{{
                                                field.required
                                                    ? 'Choisir une colonne'
                                                    : 'Ne pas importer ce champ'
                                            }}</SelectItem>
                                            <SelectItem
                                                v-for="(
                                                    header, index
                                                ) in batch.headers"
                                                :key="index"
                                                :value="String(index)"
                                                >{{ header }}</SelectItem
                                            >
                                        </SelectGroup></SelectContent
                                    ></Select
                                >
                                <FieldDescription
                                    v-if="mapping.mapping[field.key] !== 'none'"
                                    >Exemple :
                                    {{
                                        batch.samples[0]?.[
                                            Number(mapping.mapping[field.key])
                                        ] || '(vide)'
                                    }}</FieldDescription
                                >
                            </Field>
                            <Field class="md:col-span-2">
                                <FieldLabel for="duplicate-mode"
                                    >Si l’identifiant existe déjà</FieldLabel
                                >
                                <Select
                                    v-model="mapping.duplicate_mode"
                                    :disabled="busy"
                                    ><SelectTrigger id="duplicate-mode"
                                        ><SelectValue /></SelectTrigger
                                    ><SelectContent
                                        ><SelectGroup>
                                            <SelectItem value="skip"
                                                >Ignorer les éléments
                                                existants</SelectItem
                                            ><SelectItem
                                                v-if="canUpdate"
                                                value="update"
                                                >Mettre à jour les éléments
                                                existants</SelectItem
                                            >
                                        </SelectGroup></SelectContent
                                    ></Select
                                >
                                <FieldDescription
                                    >Les identifiants doivent correspondre aux
                                    éléments existants. Un identifiant répété
                                    dans le fichier doit être corrigé. Les
                                    champs facultatifs non associés restent
                                    inchangés ; une cellule vide associée efface
                                    le contenu facultatif.</FieldDescription
                                >
                            </Field>
                        </FieldGroup>
                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" :disabled="busy">{{
                                mapping.processing
                                    ? 'Validation…'
                                    : 'Valider et prévisualiser'
                            }}</Button
                            ><Button
                                type="button"
                                variant="outline"
                                :disabled="busy"
                                @click="cancel"
                                >Supprimer cet import et recommencer</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>
            <Card v-if="batch.preview">
                <CardHeader
                    ><CardTitle>3. Vérifier et confirmer</CardTitle
                    ><CardDescription
                        >Toutes les lignes sont validées avant l’import. Aucune
                        modification n’est appliquée tant que vous ne confirmez
                        pas.</CardDescription
                    ></CardHeader
                >
                <CardContent class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-2" role="status">
                        <Badge variant="secondary"
                            >{{ batch.preview.counts.create }} à créer</Badge
                        ><Badge variant="secondary"
                            >{{ batch.preview.counts.update }} à modifier</Badge
                        ><Badge variant="outline"
                            >{{ batch.preview.counts.skip }} ignorée(s)</Badge
                        ><Badge
                            :variant="
                                batch.preview.counts.error
                                    ? 'destructive'
                                    : 'outline'
                            "
                            >{{ batch.preview.counts.error }} erreur(s)</Badge
                        >
                    </div>
                    <Alert v-if="changed"
                        ><AlertTitle>Aperçu à actualiser</AlertTitle
                        ><AlertDescription
                            >Les options ont changé. Validez à nouveau les
                            colonnes avant de confirmer.</AlertDescription
                        ></Alert
                    >
                    <Alert
                        v-else-if="batch.preview.counts.error"
                        variant="destructive"
                        ><AlertTitle>Aucune ligne ne sera importée</AlertTitle
                        ><AlertDescription
                            >Corrigez le fichier ou les associations, puis
                            générez un nouvel aperçu. Le rapport contient toutes
                            les lignes en erreur.</AlertDescription
                        ></Alert
                    >
                    <Table>
                        <TableHeader
                            ><TableRow
                                ><TableHead>Ligne</TableHead
                                ><TableHead
                                    v-for="(column, index) in columns"
                                    :key="index"
                                    >{{ column }}</TableHead
                                ><TableHead>Action</TableHead
                                ><TableHead>Erreurs</TableHead></TableRow
                            ></TableHeader
                        >
                        <TableBody
                            ><TableRow v-for="row in rows" :key="row.number"
                                ><TableCell>{{ row.number }}</TableCell
                                ><TableCell
                                    v-for="(cell, index) in row.cells"
                                    :key="index"
                                    class="max-w-64 break-words whitespace-normal"
                                    >{{ cell }}</TableCell
                                ><TableCell>{{
                                    actionLabels[row.action]
                                }}</TableCell
                                ><TableCell class="max-w-80 whitespace-normal"
                                    ><ul>
                                        <li
                                            v-for="(error, index) in row.errors"
                                            :key="index"
                                        >
                                            {{ error }}
                                        </li>
                                    </ul></TableCell
                                ></TableRow
                            ></TableBody
                        >
                    </Table>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="pageNumber <= 1 || busy"
                            @click="pageNumber--"
                            >Précédent</Button
                        ><span class="text-sm"
                            >Page {{ pageNumber }} / {{ totalPages }}</span
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="pageNumber >= totalPages || busy"
                            @click="pageNumber++"
                            >Suivant</Button
                        >
                    </div>
                    <p class="text-muted-foreground text-sm">
                        Aperçu limité aux 100 premières lignes ; la validation
                        et l’import portent sur les {{ batch.total }} lignes.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            :disabled="
                                busy ||
                                changed ||
                                batch.preview.counts.error > 0
                            "
                            @click="confirm"
                            >{{
                                confirmation.processing
                                    ? 'Import en cours…'
                                    : `Confirmer l’import de ${batch.preview.counts.create + batch.preview.counts.update} élément(s)`
                            }}</Button
                        >
                        <Button
                            v-if="
                                batch.preview.counts.error &&
                                !changed &&
                                errorsUrl
                            "
                            variant="outline"
                            as-child
                            ><a :href="errorsUrl"
                                ><Download
                                    data-icon="inline-start"
                                />Télécharger les erreurs</a
                            ></Button
                        >
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
