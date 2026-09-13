<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    preferences,
    updatePreferences,
} from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';

const props = defineProps<{
    email: Record<string, boolean>;
    categories: Record<string, { label: string; description: string }>;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifications', href: preferences() }] },
});
const form = useForm({ email: { ...props.email } });
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Notifications"
            description="Choisissez les catégories pour lesquelles vous souhaitez recevoir un e-mail."
        />
        <p class="text-muted-foreground text-sm">
            Les notifications restent disponibles dans votre boîte de réception.
            Les e-mails de connexion, de vérification et de récupération de
            compte restent actifs.
        </p>
        <form
            class="space-y-5"
            @submit.prevent="
                form.put(updatePreferences.url(), { preserveScroll: true })
            "
        >
            <div
                v-for="(category, key) in categories"
                :key="key"
                class="space-y-2"
            >
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-4"
                >
                    <input
                        v-model="form.email[key]"
                        type="checkbox"
                        :disabled="form.processing"
                        class="accent-primary mt-1 size-4"
                    />
                    <span class="space-y-1"
                        ><span class="block text-sm font-medium">{{
                            category.label
                        }}</span
                        ><span class="text-muted-foreground block text-sm">{{
                            category.description
                        }}</span></span
                    >
                </label>
                <FieldError>{{ form.errors[`email.${key}`] }}</FieldError>
            </div>
            <FieldError>{{ form.errors.email }}</FieldError>
            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing"
                    >Enregistrer</Button
                >
                <p
                    v-if="form.recentlySuccessful"
                    role="status"
                    class="text-muted-foreground text-sm"
                >
                    Préférences enregistrées.
                </p>
            </div>
        </form>
    </div>
</template>
