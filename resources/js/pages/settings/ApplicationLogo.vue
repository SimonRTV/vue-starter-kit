<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ApplicationLogoController from '@/actions/App/Http/Controllers/Settings/ApplicationLogoController';
import ConfirmationAction from '@/components/application/ConfirmationAction.vue';
import FormLayout from '@/components/application/FormLayout.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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
import { edit } from '@/routes/application-logo';

defineProps<{
    iconUrl: string | null;
    fullLogoUrl: string | null;
    darkFullLogoUrl: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Paramètres de l’application',
                href: edit(),
            },
        ],
    },
});

const iconResetOpen = ref(false);
const iconResetting = ref(false);
const fullLogoResetOpen = ref(false);
const fullLogoResetting = ref(false);
const darkFullLogoResetOpen = ref(false);
const darkFullLogoResetting = ref(false);

function resetIcon(): void {
    iconResetting.value = true;

    router.delete(ApplicationLogoController.destroy.url(), {
        preserveScroll: true,
        onSuccess: () => {
            iconResetOpen.value = false;
        },
        onFinish: () => {
            iconResetting.value = false;
        },
    });
}

function resetFullLogo(): void {
    fullLogoResetting.value = true;

    router.delete(ApplicationLogoController.destroyFullLogo.url(), {
        preserveScroll: true,
        onSuccess: () => {
            fullLogoResetOpen.value = false;
        },
        onFinish: () => {
            fullLogoResetting.value = false;
        },
    });
}

function resetDarkFullLogo(): void {
    darkFullLogoResetting.value = true;

    router.delete(ApplicationLogoController.destroyDarkFullLogo.url(), {
        preserveScroll: true,
        onSuccess: () => {
            darkFullLogoResetOpen.value = false;
        },
        onFinish: () => {
            darkFullLogoResetting.value = false;
        },
    });
}
</script>

<template>
    <div class="flex flex-col gap-12">
        <h1 class="sr-only">Paramètres de l’application</h1>

        <Form
            v-bind="ApplicationLogoController.update.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            v-slot="{ errors, processing, progress }"
        >
            <FormLayout
                title="Icône de l’application"
                description="Définissez le symbole carré utilisé dans la barre latérale et les autres zones compactes."
            >
                <div
                    class="bg-muted/40 flex min-h-36 items-center justify-center rounded-lg border p-6"
                >
                    <img
                        v-if="iconUrl"
                        :src="iconUrl"
                        alt="Icône actuelle de l’application"
                        class="size-24 object-contain"
                    />
                    <div v-else class="flex flex-col items-center gap-2">
                        <AppLogoIcon class="size-20 fill-current" />
                        <p class="text-muted-foreground text-sm">
                            Icône par défaut
                        </p>
                    </div>
                </div>

                <FieldGroup class="mt-6">
                    <Field
                        orientation="responsive"
                        :data-invalid="Boolean(errors.logo)"
                    >
                        <FieldContent>
                            <FieldLabel for="logo"
                                >Fichier de l’icône</FieldLabel
                            >
                            <FieldDescription>
                                Un fichier PNG, JPG, GIF ou WebP carré convient
                                le mieux. Jusqu’à 2 Mo et 4096 × 4096 pixels.
                            </FieldDescription>
                        </FieldContent>
                        <div class="flex w-full flex-col gap-2 sm:max-w-sm">
                            <Input
                                id="logo"
                                name="logo"
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp"
                                required
                                :aria-invalid="Boolean(errors.logo)"
                            />
                            <FieldError :errors="[errors.logo]" />
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
                        v-if="iconUrl"
                        v-model:open="iconResetOpen"
                        title="Restaurer l’icône par défaut ?"
                        description="L’icône personnalisée sera supprimée pour tous les utilisateurs."
                        confirm-label="Restaurer l’icône par défaut"
                        pending-label="Restauration…"
                        :processing="iconResetting"
                        @confirm="resetIcon"
                    >
                        <template #trigger>
                            <Button type="button" variant="outline">
                                Restaurer l’icône par défaut
                            </Button>
                        </template>
                    </ConfirmationAction>

                    <Button
                        type="submit"
                        :disabled="processing || iconResetting"
                    >
                        <Spinner v-if="processing" data-icon="inline-start" />
                        {{ processing ? 'Téléversement…' : 'Enregistrer' }}
                    </Button>
                </template>
            </FormLayout>
        </Form>

        <Form
            v-bind="ApplicationLogoController.updateFullLogo.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            v-slot="{ errors, processing, progress }"
        >
            <FormLayout
                title="Logo complet — fond clair"
                description="Définissez le logo horizontal facultatif utilisé sur les arrière-plans clairs."
            >
                <div
                    class="flex min-h-36 items-center justify-center rounded-lg border bg-white p-6"
                >
                    <img
                        v-if="fullLogoUrl"
                        :src="fullLogoUrl"
                        alt="Logo complet actuel de l’application"
                        class="max-h-24 max-w-full object-contain"
                    />
                    <div v-else class="flex flex-col items-center gap-2">
                        <AppLogoIcon
                            class="size-20 fill-current text-zinc-950"
                        />
                        <p class="text-sm text-zinc-600">
                            Utilise l’icône de l’application
                        </p>
                    </div>
                </div>

                <FieldGroup class="mt-6">
                    <Field
                        orientation="responsive"
                        :data-invalid="Boolean(errors.full_logo)"
                    >
                        <FieldContent>
                            <FieldLabel for="full_logo">
                                Fichier du logo complet
                            </FieldLabel>
                            <FieldDescription>
                                Un fichier PNG, JPG, GIF ou WebP large, au
                                format proche de 3:1, convient le mieux. Jusqu’à
                                2 Mo et 4096 × 4096 pixels.
                            </FieldDescription>
                        </FieldContent>
                        <div class="flex w-full flex-col gap-2 sm:max-w-sm">
                            <Input
                                id="full_logo"
                                name="full_logo"
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp"
                                required
                                :aria-invalid="Boolean(errors.full_logo)"
                            />
                            <FieldError :errors="[errors.full_logo]" />
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
                        v-if="fullLogoUrl"
                        v-model:open="fullLogoResetOpen"
                        title="Supprimer le logo complet ?"
                        description="Les arrière-plans clairs utiliseront l’icône de l’application. La variante pour fond sombre restera disponible."
                        confirm-label="Supprimer le logo complet"
                        pending-label="Suppression…"
                        :processing="fullLogoResetting"
                        @confirm="resetFullLogo"
                    >
                        <template #trigger>
                            <Button type="button" variant="outline">
                                Supprimer le logo complet
                            </Button>
                        </template>
                    </ConfirmationAction>

                    <Button
                        type="submit"
                        :disabled="processing || fullLogoResetting"
                    >
                        <Spinner v-if="processing" data-icon="inline-start" />
                        {{ processing ? 'Téléversement…' : 'Enregistrer' }}
                    </Button>
                </template>
            </FormLayout>
        </Form>

        <Form
            v-bind="ApplicationLogoController.updateDarkFullLogo.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            v-slot="{ errors, processing, progress }"
        >
            <FormLayout
                title="Logo complet — fond sombre"
                description="Définissez la variante automatiquement utilisée sur les arrière-plans sombres. Sans variante, le logo pour fond clair ou l’icône reste utilisé."
            >
                <div
                    class="flex min-h-36 items-center justify-center rounded-lg border bg-zinc-950 p-6"
                >
                    <img
                        v-if="darkFullLogoUrl"
                        :src="darkFullLogoUrl"
                        alt="Logo actuel de l’application pour fond sombre"
                        class="max-h-24 max-w-full object-contain"
                    />
                    <div v-else class="flex flex-col items-center gap-2">
                        <img
                            v-if="fullLogoUrl"
                            :src="fullLogoUrl"
                            alt="Logo pour fond clair utilisé en remplacement"
                            class="max-h-24 max-w-full object-contain"
                        />
                        <AppLogoIcon
                            v-else
                            class="size-20 fill-current text-white"
                        />
                        <p class="text-sm text-zinc-400">
                            {{
                                fullLogoUrl
                                    ? 'Utilise la variante pour fond clair'
                                    : 'Utilise l’icône de l’application'
                            }}
                        </p>
                    </div>
                </div>

                <FieldGroup class="mt-6">
                    <Field
                        orientation="responsive"
                        :data-invalid="Boolean(errors.dark_full_logo)"
                    >
                        <FieldContent>
                            <FieldLabel for="dark_full_logo">
                                Fichier du logo pour fond sombre
                            </FieldLabel>
                            <FieldDescription>
                                Un fichier PNG, JPG, GIF ou WebP large, au
                                format proche de 3:1, convient le mieux. Jusqu’à
                                2 Mo et 4096 × 4096 pixels.
                            </FieldDescription>
                        </FieldContent>
                        <div class="flex w-full flex-col gap-2 sm:max-w-sm">
                            <Input
                                id="dark_full_logo"
                                name="dark_full_logo"
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp"
                                required
                                :aria-invalid="Boolean(errors.dark_full_logo)"
                            />
                            <FieldError :errors="[errors.dark_full_logo]" />
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
                        v-if="darkFullLogoUrl"
                        v-model:open="darkFullLogoResetOpen"
                        title="Supprimer le logo pour fond sombre ?"
                        description="Le logo pour fond clair, ou l’icône s’il n’existe pas, sera utilisé sur les arrière-plans sombres."
                        confirm-label="Supprimer le logo pour fond sombre"
                        pending-label="Suppression…"
                        :processing="darkFullLogoResetting"
                        @confirm="resetDarkFullLogo"
                    >
                        <template #trigger>
                            <Button type="button" variant="outline">
                                Supprimer le logo pour fond sombre
                            </Button>
                        </template>
                    </ConfirmationAction>

                    <Button
                        type="submit"
                        :disabled="processing || darkFullLogoResetting"
                    >
                        <Spinner v-if="processing" data-icon="inline-start" />
                        {{ processing ? 'Téléversement…' : 'Enregistrer' }}
                    </Button>
                </template>
            </FormLayout>
        </Form>
    </div>
</template>
