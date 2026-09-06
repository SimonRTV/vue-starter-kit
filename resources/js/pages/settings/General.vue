<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Settings/GeneralSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/general-settings';

defineProps<{
    settings: {
        name: string;
        tagline: string;
        contact_email: string;
        contact_phone: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Informations générales', href: edit() }],
    },
});
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Informations générales"
            description="L’identité et les coordonnées publiques de votre application."
        />
        <Form
            v-bind="update.form()"
            v-slot="{ errors, processing, recentlySuccessful }"
            class="space-y-6"
        >
            <FieldGroup>
                <Field>
                    <FieldLabel for="name">Nom de l’application</FieldLabel>
                    <Input
                        id="name"
                        name="name"
                        :default-value="settings.name"
                        required
                        maxlength="100"
                        :aria-invalid="Boolean(errors.name)"
                    />
                    <FieldError>{{ errors.name }}</FieldError>
                </Field>
                <Field>
                    <FieldLabel for="tagline">Description courte</FieldLabel>
                    <Input
                        id="tagline"
                        name="tagline"
                        :default-value="settings.tagline"
                        maxlength="200"
                        :aria-invalid="Boolean(errors.tagline)"
                    />
                    <FieldError>{{ errors.tagline }}</FieldError>
                </Field>
                <Field>
                    <FieldLabel for="contact_email"
                        >Adresse e-mail de contact</FieldLabel
                    >
                    <Input
                        id="contact_email"
                        name="contact_email"
                        type="email"
                        :default-value="settings.contact_email"
                        maxlength="255"
                        :aria-invalid="Boolean(errors.contact_email)"
                    />
                    <FieldError>{{ errors.contact_email }}</FieldError>
                </Field>
                <Field>
                    <FieldLabel for="contact_phone"
                        >Téléphone de contact</FieldLabel
                    >
                    <Input
                        id="contact_phone"
                        name="contact_phone"
                        type="tel"
                        :default-value="settings.contact_phone"
                        maxlength="40"
                        :aria-invalid="Boolean(errors.contact_phone)"
                    />
                    <FieldError>{{ errors.contact_phone }}</FieldError>
                </Field>
            </FieldGroup>
            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing"
                    ><Spinner v-if="processing" />Enregistrer</Button
                >
                <p
                    v-if="recentlySuccessful"
                    role="status"
                    class="text-sm text-muted-foreground"
                >
                    Enregistré.
                </p>
            </div>
        </Form>
    </div>
</template>
