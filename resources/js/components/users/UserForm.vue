<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { FormLayout } from '@/components/application';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatRoleName } from '@/lib/roleNames';
import type { ManagedUserDetail, UserRoleOption } from '@/types';

const props = defineProps<{
    user?: ManagedUserDetail;
    roles: UserRoleOption[];
    canManageVerification: boolean;
}>();

const emailVerified = ref(Boolean(props.user?.email_verified_at));
const selectedRoles = ref<string[]>([...(props.user?.roles ?? [])]);

const formAttributes = computed(() =>
    props.user
        ? UserController.update.form(props.user.id)
        : UserController.store.form(),
);
const cancelTarget = computed(() =>
    props.user ? UserController.show(props.user.id) : UserController.index(),
);
const submitLabel = computed(() =>
    props.user ? 'Enregistrer les modifications' : 'Créer l’utilisateur',
);
const formDescription = computed(() =>
    props.user
        ? 'Les modifications sont appliquées dès l’enregistrement du formulaire.'
        : 'Définissez l’identité du compte et ses accès initiaux. Un lien sécurisé de configuration sera envoyé automatiquement par e-mail.',
);
const rolesLocked = computed(
    () => props.user !== undefined && !props.user.can_manage_roles,
);
const hasAssignableRoles = computed(
    () => !rolesLocked.value && props.roles.some((role) => role.can_assign),
);

function transformForm(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        email_verified: emailVerified.value,
        roles: selectedRoles.value,
    };
}

function toggleRole(
    roleName: string,
    checked: boolean | 'indeterminate',
): void {
    if (rolesLocked.value) {
        return;
    }

    const role = props.roles.find((option) => option.name === roleName);

    if (!role?.can_assign) {
        return;
    }

    if (checked === true && !selectedRoles.value.includes(roleName)) {
        selectedRoles.value = [...selectedRoles.value, roleName];

        return;
    }

    if (checked === false) {
        selectedRoles.value = selectedRoles.value.filter(
            (selectedRole) => selectedRole !== roleName,
        );
    }
}

function roleError(errors: Record<string, string>): string | undefined {
    if (errors.roles) {
        return errors.roles;
    }

    const nestedRoleError = Object.keys(errors).find((key) =>
        key.startsWith('roles.'),
    );

    return nestedRoleError ? errors[nestedRoleError] : undefined;
}
</script>

<template>
    <Form
        v-bind="formAttributes"
        :transform="transformForm"
        v-slot="{ errors, processing }"
    >
        <FormLayout
            title="Détails de l’utilisateur"
            :description="formDescription"
        >
            <FieldGroup>
                <Field :data-invalid="errors.name ? true : undefined">
                    <FieldLabel for="name">Nom</FieldLabel>
                    <Input
                        id="name"
                        name="name"
                        :default-value="user?.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Jean Dupont"
                        :aria-invalid="Boolean(errors.name)"
                    />
                    <FieldError v-if="errors.name">
                        {{ errors.name }}
                    </FieldError>
                </Field>

                <Field :data-invalid="errors.email ? true : undefined">
                    <FieldLabel for="email">Adresse e-mail</FieldLabel>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        :default-value="user?.email"
                        required
                        autocomplete="email"
                        placeholder="jean@example.com"
                        :aria-invalid="Boolean(errors.email)"
                    />
                    <FieldError v-if="errors.email">
                        {{ errors.email }}
                    </FieldError>
                </Field>

                <Field
                    orientation="horizontal"
                    :data-invalid="errors.email_verified ? true : undefined"
                >
                    <Checkbox
                        id="email-verified"
                        v-model="emailVerified"
                        :disabled="!canManageVerification"
                        :aria-invalid="Boolean(errors.email_verified)"
                    />
                    <FieldContent>
                        <FieldLabel for="email-verified">
                            L’adresse e-mail est vérifiée
                        </FieldLabel>
                        <FieldDescription>
                            <template v-if="canManageVerification">
                                Les utilisateurs vérifiés peuvent accéder aux
                                pages protégées par la vérification de l’adresse
                                e-mail.
                            </template>
                            <template v-else>
                                Vous n’avez pas l’autorisation de modifier le
                                statut de vérification de l’adresse e-mail.
                            </template>
                        </FieldDescription>
                        <FieldError v-if="errors.email_verified">
                            {{ errors.email_verified }}
                        </FieldError>
                    </FieldContent>
                </Field>

                <FieldSet :data-invalid="roleError(errors) ? true : undefined">
                    <FieldLegend variant="label">Rôles</FieldLegend>
                    <FieldDescription>
                        <template v-if="rolesLocked">
                            Vos propres rôles sont verrouillés ici pour éviter
                            une perte d’accès accidentelle.
                        </template>
                        <template v-else-if="!hasAssignableRoles">
                            Vous n’avez pas l’autorisation d’attribuer les rôles
                            disponibles.
                        </template>
                        <template v-else>
                            Sélectionnez tous les rôles de cet utilisateur. Les
                            rôles accordant des accès supérieurs aux vôtres
                            restent verrouillés.
                        </template>
                    </FieldDescription>
                    <FieldGroup v-if="roles.length" class="gap-3">
                        <Field
                            v-for="role in roles"
                            :key="role.id"
                            orientation="horizontal"
                            :data-disabled="
                                rolesLocked || !role.can_assign
                                    ? true
                                    : undefined
                            "
                        >
                            <Checkbox
                                :id="'role-' + role.id"
                                :model-value="selectedRoles.includes(role.name)"
                                :disabled="rolesLocked || !role.can_assign"
                                @update:model-value="
                                    toggleRole(role.name, $event)
                                "
                            />
                            <FieldLabel
                                :for="'role-' + role.id"
                                class="font-normal"
                            >
                                {{ formatRoleName(role.name) }}
                                <span
                                    v-if="!rolesLocked && !role.can_assign"
                                    class="text-muted-foreground"
                                >
                                    (verrouillé)
                                </span>
                            </FieldLabel>
                        </Field>
                    </FieldGroup>
                    <p v-else class="text-sm text-muted-foreground">
                        Aucun rôle n’a encore été créé. L’utilisateur peut tout
                        de même être enregistré sans rôle.
                    </p>
                    <FieldError v-if="roleError(errors)">
                        {{ roleError(errors) }}
                    </FieldError>
                </FieldSet>
            </FieldGroup>

            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="cancelTarget">Annuler</Link>
                </Button>
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" data-icon="inline-start" />
                    {{ processing ? 'Enregistrement…' : submitLabel }}
                </Button>
            </template>
        </FormLayout>
    </Form>
</template>
