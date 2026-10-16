<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import FaviconController from '@/actions/App/Http/Controllers/Settings/FaviconController';
import ConfirmationAction from '@/components/application/ConfirmationAction.vue';
import FormLayout from '@/components/application/FormLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/favicons';

const props = defineProps<{
    icons: {
        custom: boolean;
        customSvg: boolean;
        svg: string | null;
        png: string;
        ico: string;
        appleTouchIcon: string;
        manifestSmallIcon: string;
        manifestLargeIcon: string;
    };
    colors: {
        theme_color: string;
        background_color: string;
    };
    applicationName: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Favicons', href: edit() }],
    },
});

const themeColor = ref(props.colors.theme_color);
const backgroundColor = ref(props.colors.background_color);
const resetOpen = ref(false);
const resetting = ref(false);
const svgRemoveOpen = ref(false);
const svgRemoving = ref(false);

function resetFavicons(): void {
    resetting.value = true;

    router.delete(FaviconController.destroy.url(), {
        preserveScroll: true,
        onSuccess: () => {
            resetOpen.value = false;
        },
        onFinish: () => {
            resetting.value = false;
        },
    });
}

function removeSvg(): void {
    svgRemoving.value = true;

    router.delete(FaviconController.destroySvg.url(), {
        preserveScroll: true,
        onSuccess: () => {
            svgRemoveOpen.value = false;
        },
        onFinish: () => {
            svgRemoving.value = false;
        },
    });
}
</script>

<template>
    <div class="flex flex-col gap-12">
        <h1 class="sr-only">Favicons</h1>

        <Form
            v-bind="FaviconController.update.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            v-slot="{ errors, processing, progress }"
        >
            <FormLayout
                title="Favicons"
                description="Téléversez une seule image : l’onglet du navigateur, l’écran d’accueil iOS et Android ainsi que l’application installable sont générés automatiquement."
            >
                <div class="flex flex-col gap-6">
                    <div class="overflow-hidden rounded-lg border">
                        <div
                            class="flex items-end gap-2 px-3 pt-2"
                            :style="{ backgroundColor: themeColor }"
                        >
                            <div
                                class="bg-background flex max-w-64 min-w-0 items-center gap-2 rounded-t-md px-3 py-2"
                            >
                                <img
                                    :src="icons.svg ?? icons.png"
                                    alt=""
                                    class="size-4 shrink-0"
                                />
                                <span class="truncate text-xs">
                                    {{ applicationName }}
                                </span>
                            </div>
                        </div>
                        <div class="bg-background h-6 border-t" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <figure
                            class="bg-muted/40 flex flex-col items-center gap-3 rounded-lg border p-4"
                        >
                            <div class="flex h-16 items-end gap-3">
                                <img
                                    :src="icons.ico"
                                    alt="Favicon 16 pixels"
                                    class="size-4"
                                />
                                <img
                                    :src="icons.ico"
                                    alt="Favicon 32 pixels"
                                    class="size-8"
                                />
                            </div>
                            <figcaption
                                class="text-muted-foreground text-center text-xs"
                            >
                                Navigateur<br />favicon.ico
                            </figcaption>
                        </figure>
                        <figure
                            class="bg-muted/40 flex flex-col items-center gap-3 rounded-lg border p-4"
                        >
                            <img
                                :src="icons.png"
                                alt="Favicon 96 pixels"
                                class="size-16"
                            />
                            <figcaption
                                class="text-muted-foreground text-center text-xs"
                            >
                                Moteurs de recherche<br />96 × 96
                            </figcaption>
                        </figure>
                        <figure
                            class="bg-muted/40 flex flex-col items-center gap-3 rounded-lg border p-4"
                        >
                            <img
                                :src="icons.appleTouchIcon"
                                alt="Icône d’écran d’accueil iOS"
                                class="size-16 rounded-[22%] shadow-sm"
                            />
                            <figcaption
                                class="text-muted-foreground text-center text-xs"
                            >
                                iOS<br />180 × 180
                            </figcaption>
                        </figure>
                        <figure
                            class="bg-muted/40 flex flex-col items-center gap-3 rounded-lg border p-4"
                        >
                            <img
                                :src="icons.manifestLargeIcon"
                                alt="Icône d’application Android"
                                class="size-16 rounded-full shadow-sm"
                            />
                            <figcaption
                                class="text-muted-foreground text-center text-xs"
                            >
                                Android et PWA<br />192 et 512
                            </figcaption>
                        </figure>
                    </div>

                    <p
                        v-if="!icons.custom"
                        class="text-muted-foreground text-sm"
                    >
                        Les favicons par défaut de l’application sont utilisés.
                    </p>
                </div>

                <FieldGroup class="mt-6">
                    <Field
                        orientation="responsive"
                        :data-invalid="Boolean(errors.favicon)"
                    >
                        <FieldContent>
                            <FieldLabel for="favicon">Image source</FieldLabel>
                            <FieldDescription>
                                Un fichier PNG, JPG ou WebP carré, idéalement à
                                fond transparent. Entre 512 × 512 et 2048 × 2048
                                pixels, jusqu’à 4 Mo.
                            </FieldDescription>
                        </FieldContent>
                        <div class="flex w-full flex-col gap-2 sm:max-w-sm">
                            <Input
                                id="favicon"
                                name="favicon"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                required
                                :aria-invalid="Boolean(errors.favicon)"
                            />
                            <FieldError :errors="[errors.favicon]" />
                            <p
                                v-if="progress"
                                class="text-muted-foreground text-sm"
                            >
                                Téléversement… {{ progress.percentage }} %
                            </p>
                        </div>
                    </Field>
                </FieldGroup>

                <template #actions>
                    <ConfirmationAction
                        v-if="icons.custom"
                        v-model:open="resetOpen"
                        title="Restaurer les favicons par défaut ?"
                        description="Les favicons générés seront supprimés pour tous les visiteurs. Les couleurs et le favicon SVG seront conservés."
                        confirm-label="Restaurer les favicons par défaut"
                        pending-label="Restauration…"
                        :processing="resetting"
                        @confirm="resetFavicons"
                    >
                        <template #trigger>
                            <Button type="button" variant="outline">
                                Restaurer les favicons par défaut
                            </Button>
                        </template>
                    </ConfirmationAction>

                    <Button type="submit" :disabled="processing || resetting">
                        <Spinner v-if="processing" data-icon="inline-start" />
                        {{
                            processing ? 'Génération…' : 'Générer les favicons'
                        }}
                    </Button>
                </template>
            </FormLayout>
        </Form>

        <Form
            v-bind="FaviconController.updateSvg.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            v-slot="{ errors, processing, progress }"
        >
            <FormLayout
                title="Favicon SVG"
                description="Facultatif. Les navigateurs récents affichent la version vectorielle, nette à toutes les tailles ; les autres plateformes utilisent les favicons générés."
            >
                <div class="grid grid-cols-2 gap-4">
                    <figure
                        class="flex flex-col items-center gap-3 rounded-lg border bg-white p-4"
                    >
                        <img
                            v-if="icons.svg"
                            :src="icons.svg"
                            alt="Favicon SVG sur fond clair"
                            class="size-16"
                        />
                        <img
                            v-else
                            :src="icons.png"
                            alt="Favicon PNG utilisé en remplacement"
                            class="size-16"
                        />
                        <figcaption class="text-center text-xs text-zinc-600">
                            Onglet clair
                        </figcaption>
                    </figure>
                    <figure
                        class="flex flex-col items-center gap-3 rounded-lg border bg-zinc-950 p-4"
                    >
                        <img
                            :src="icons.svg ?? icons.png"
                            alt="Favicon sur fond sombre"
                            class="size-16"
                        />
                        <figcaption class="text-center text-xs text-zinc-400">
                            Onglet sombre
                        </figcaption>
                    </figure>
                </div>

                <p class="text-muted-foreground mt-4 text-sm">
                    {{
                        icons.customSvg
                            ? 'Un favicon SVG personnalisé est utilisé.'
                            : icons.custom
                              ? 'Aucun SVG : les navigateurs utilisent les favicons générés.'
                              : 'Le favicon SVG par défaut est utilisé.'
                    }}
                    Un SVG peut adapter ses couleurs au mode sombre avec
                    <code class="text-xs"
                        >@media (prefers-color-scheme: dark)</code
                    >.
                </p>

                <FieldGroup class="mt-6">
                    <Field
                        orientation="responsive"
                        :data-invalid="Boolean(errors.svg)"
                    >
                        <FieldContent>
                            <FieldLabel for="svg">Fichier SVG</FieldLabel>
                            <FieldDescription>
                                Un SVG carré, jusqu’à 512 Ko. Les scripts et
                                gestionnaires d’événements sont retirés à
                                l’enregistrement.
                            </FieldDescription>
                        </FieldContent>
                        <div class="flex w-full flex-col gap-2 sm:max-w-sm">
                            <Input
                                id="svg"
                                name="svg"
                                type="file"
                                accept="image/svg+xml,.svg"
                                required
                                :aria-invalid="Boolean(errors.svg)"
                            />
                            <FieldError :errors="[errors.svg]" />
                            <p
                                v-if="progress"
                                class="text-muted-foreground text-sm"
                            >
                                Téléversement… {{ progress.percentage }} %
                            </p>
                        </div>
                    </Field>
                </FieldGroup>

                <template #actions>
                    <ConfirmationAction
                        v-if="icons.customSvg"
                        v-model:open="svgRemoveOpen"
                        title="Supprimer le favicon SVG ?"
                        description="Les navigateurs utiliseront les favicons PNG et ICO."
                        confirm-label="Supprimer le favicon SVG"
                        pending-label="Suppression…"
                        :processing="svgRemoving"
                        @confirm="removeSvg"
                    >
                        <template #trigger>
                            <Button type="button" variant="outline">
                                Supprimer le favicon SVG
                            </Button>
                        </template>
                    </ConfirmationAction>

                    <Button type="submit" :disabled="processing || svgRemoving">
                        <Spinner v-if="processing" data-icon="inline-start" />
                        {{ processing ? 'Téléversement…' : 'Enregistrer' }}
                    </Button>
                </template>
            </FormLayout>
        </Form>

        <Form
            v-bind="FaviconController.updateColors.form()"
            :options="{ preserveScroll: true }"
            v-slot="{ errors, processing }"
        >
            <FormLayout
                title="Couleurs"
                description="Teintez l’interface du navigateur mobile et l’écran de démarrage de l’application installée."
            >
                <FieldGroup>
                    <Field
                        orientation="responsive"
                        :data-invalid="Boolean(errors.theme_color)"
                    >
                        <FieldContent>
                            <FieldLabel for="theme_color">
                                Couleur du thème
                            </FieldLabel>
                            <FieldDescription>
                                Utilisée pour la barre d’adresse mobile et la
                                barre de titre de l’application installée.
                            </FieldDescription>
                        </FieldContent>
                        <div class="flex w-full flex-col gap-2 sm:max-w-sm">
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="themeColor"
                                    type="color"
                                    aria-label="Choisir la couleur du thème"
                                    class="border-input h-9 w-12 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                                />
                                <Input
                                    id="theme_color"
                                    v-model="themeColor"
                                    name="theme_color"
                                    maxlength="7"
                                    autocomplete="off"
                                    spellcheck="false"
                                    class="font-mono"
                                    :aria-invalid="Boolean(errors.theme_color)"
                                />
                            </div>
                            <FieldError :errors="[errors.theme_color]" />
                        </div>
                    </Field>
                    <Field
                        orientation="responsive"
                        :data-invalid="Boolean(errors.background_color)"
                    >
                        <FieldContent>
                            <FieldLabel for="background_color">
                                Couleur de fond
                            </FieldLabel>
                            <FieldDescription>
                                Remplit l’arrière-plan des icônes iOS et Android
                                générées, ainsi que l’écran de démarrage. Les
                                icônes sont régénérées à l’enregistrement.
                            </FieldDescription>
                        </FieldContent>
                        <div class="flex w-full flex-col gap-2 sm:max-w-sm">
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="backgroundColor"
                                    type="color"
                                    aria-label="Choisir la couleur de fond"
                                    class="border-input h-9 w-12 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                                />
                                <Input
                                    id="background_color"
                                    v-model="backgroundColor"
                                    name="background_color"
                                    maxlength="7"
                                    autocomplete="off"
                                    spellcheck="false"
                                    class="font-mono"
                                    :aria-invalid="
                                        Boolean(errors.background_color)
                                    "
                                />
                            </div>
                            <FieldError :errors="[errors.background_color]" />
                        </div>
                    </Field>
                </FieldGroup>

                <template #actions>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" data-icon="inline-start" />
                        {{ processing ? 'Enregistrement…' : 'Enregistrer' }}
                    </Button>
                </template>
            </FormLayout>
        </Form>
    </div>
</template>
