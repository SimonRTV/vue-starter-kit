<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Settings/AnalyticsSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/analytics-settings';

defineProps<{
    settings: {
        enabled: boolean;
        measurement_id: string;
        exclude_authenticated: boolean;
        require_consent: boolean;
        privacy_policy_url: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Google Analytics', href: edit() }],
    },
});
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Google Analytics"
            description="Mesurez l’audience du site public avec Google Analytics 4. Le script n’est chargé que sur les pages publiques."
        />
        <Form
            v-bind="update.form()"
            v-slot="{ errors, processing, recentlySuccessful }"
            class="space-y-6"
        >
            <FieldGroup>
                <Field>
                    <Label for="enabled" class="flex items-center gap-3">
                        <Checkbox
                            id="enabled"
                            name="enabled"
                            value="1"
                            :default-value="settings.enabled"
                        />
                        <span>Activer Google Analytics</span>
                    </Label>
                    <FieldError>{{ errors.enabled }}</FieldError>
                </Field>
                <Field>
                    <FieldLabel for="measurement_id"
                        >Identifiant de mesure</FieldLabel
                    >
                    <Input
                        id="measurement_id"
                        name="measurement_id"
                        :default-value="settings.measurement_id"
                        placeholder="G-XXXXXXXXXX"
                        maxlength="23"
                        autocomplete="off"
                        spellcheck="false"
                        :aria-invalid="Boolean(errors.measurement_id)"
                    />
                    <FieldDescription
                        >Commence par G- ou GT-. Disponible dans Google
                        Analytics sous Administration › Flux de
                        données.</FieldDescription
                    >
                    <FieldError>{{ errors.measurement_id }}</FieldError>
                </Field>
                <Field>
                    <Label
                        for="exclude_authenticated"
                        class="flex items-center gap-3"
                    >
                        <Checkbox
                            id="exclude_authenticated"
                            name="exclude_authenticated"
                            value="1"
                            :default-value="settings.exclude_authenticated"
                        />
                        <span>Ne pas suivre les utilisateurs connectés</span>
                    </Label>
                    <FieldError>{{ errors.exclude_authenticated }}</FieldError>
                </Field>
                <Field>
                    <Label
                        for="require_consent"
                        class="flex items-center gap-3"
                    >
                        <Checkbox
                            id="require_consent"
                            name="require_consent"
                            value="1"
                            :default-value="settings.require_consent"
                        />
                        <span>Demander le consentement des visiteurs</span>
                    </Label>
                    <FieldDescription
                        >Affiche une bannière de cookies et ne charge Google
                        Analytics qu’après acceptation. Recommandé pour
                        respecter la nLPD et le RGPD.</FieldDescription
                    >
                    <FieldError>{{ errors.require_consent }}</FieldError>
                </Field>
                <Field>
                    <FieldLabel for="privacy_policy_url"
                        >Lien vers la politique de confidentialité</FieldLabel
                    >
                    <Input
                        id="privacy_policy_url"
                        name="privacy_policy_url"
                        :default-value="settings.privacy_policy_url"
                        placeholder="/content/confidentialite"
                        maxlength="2048"
                        :aria-invalid="Boolean(errors.privacy_policy_url)"
                    />
                    <FieldDescription
                        >Facultatif. Affiché dans la bannière de
                        cookies.</FieldDescription
                    >
                    <FieldError>{{ errors.privacy_policy_url }}</FieldError>
                </Field>
            </FieldGroup>
            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing"
                    ><Spinner v-if="processing" />Enregistrer</Button
                >
                <p
                    v-if="recentlySuccessful"
                    role="status"
                    class="text-muted-foreground text-sm"
                >
                    Enregistré.
                </p>
            </div>
        </Form>
    </div>
</template>
