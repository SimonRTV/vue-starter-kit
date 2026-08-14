<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { FormLayout } from '@/components/application';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
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
import type { ManagedRoleDetail, RolePermissionOption } from '@/types';

type PermissionGroup = {
    name: string;
    label: string;
    permissions: RolePermissionOption[];
    visiblePermissions: RolePermissionOption[];
};

const props = defineProps<{
    role?: ManagedRoleDetail;
    permissions: RolePermissionOption[];
}>();

const selectedPermissions = ref<string[]>([...(props.role?.permissions ?? [])]);
const permissionSearch = ref('');
const allPermissionGroups = computed<
    Omit<PermissionGroup, 'visiblePermissions'>[]
>(() => {
    const groups = new Map<string, RolePermissionOption[]>();

    for (const permission of props.permissions) {
        const groupPermissions = groups.get(permission.group) ?? [];

        groupPermissions.push(permission);
        groups.set(permission.group, groupPermissions);
    }

    return [...groups.entries()].map(([name, permissions]) => ({
        name,
        label: permissions[0]?.group_label ?? name,
        permissions,
    }));
});
const permissionGroups = computed<PermissionGroup[]>(() => {
    const search = permissionSearch.value.trim().toLocaleLowerCase();

    return allPermissionGroups.value
        .map((group) => ({
            ...group,
            visiblePermissions:
                search === ''
                    ? group.permissions
                    : group.permissions.filter((permission) =>
                          [
                              permission.name,
                              permission.label,
                              permission.description,
                              permission.group_label,
                              permission.is_sensitive ? 'sensible' : '',
                              permission.is_orphaned ? 'orpheline' : '',
                          ].some((value) =>
                              value.toLocaleLowerCase().includes(search),
                          ),
                      ),
        }))
        .filter((group) => group.visiblePermissions.length > 0);
});
const formAttributes = computed(() =>
    props.role
        ? RoleController.update.form(props.role.id)
        : RoleController.store.form(),
);
const cancelTarget = computed(() =>
    props.role ? RoleController.show(props.role.id) : RoleController.index(),
);
const submitLabel = computed(() =>
    props.role ? 'Enregistrer les modifications' : 'Créer le rôle',
);

function transformForm(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        permissions: selectedPermissions.value,
    };
}

function togglePermission(
    permissionName: string,
    checked: boolean | 'indeterminate',
): void {
    if (
        checked === true &&
        !selectedPermissions.value.includes(permissionName)
    ) {
        selectedPermissions.value = [
            ...selectedPermissions.value,
            permissionName,
        ];

        return;
    }

    if (checked === false) {
        selectedPermissions.value = selectedPermissions.value.filter(
            (selectedPermission) => selectedPermission !== permissionName,
        );
    }
}

function groupSelectionState(
    group: PermissionGroup,
): boolean | 'indeterminate' {
    const selectedCount = group.permissions.filter((permission) =>
        selectedPermissions.value.includes(permission.name),
    ).length;

    if (selectedCount === 0) {
        return false;
    }

    return selectedCount === group.permissions.length ? true : 'indeterminate';
}

function selectedGroupCount(group: PermissionGroup): number {
    return group.permissions.filter((permission) =>
        selectedPermissions.value.includes(permission.name),
    ).length;
}

function togglePermissionGroup(
    group: PermissionGroup,
    checked: boolean | 'indeterminate',
): void {
    const groupPermissionNames = group.permissions.map(
        (permission) => permission.name,
    );

    if (checked === true) {
        selectedPermissions.value = [
            ...new Set([...selectedPermissions.value, ...groupPermissionNames]),
        ];

        return;
    }

    if (checked === false) {
        selectedPermissions.value = selectedPermissions.value.filter(
            (permissionName) => !groupPermissionNames.includes(permissionName),
        );
    }
}

function permissionError(errors: Record<string, string>): string | undefined {
    if (errors.permissions) {
        return errors.permissions;
    }

    const nestedPermissionError = Object.keys(errors).find((key) =>
        key.startsWith('permissions.'),
    );

    return nestedPermissionError ? errors[nestedPermissionError] : undefined;
}
</script>

<template>
    <Form
        v-bind="formAttributes"
        :transform="transformForm"
        v-slot="{ errors, processing }"
    >
        <FormLayout
            title="Détails du rôle"
            description="Nommez le profil d’accès et choisissez toutes les autorisations qu’il accorde."
        >
            <FieldGroup>
                <Field :data-invalid="errors.name ? true : undefined">
                    <FieldLabel for="name">Nom</FieldLabel>
                    <Input
                        id="name"
                        name="name"
                        :default-value="role?.name"
                        required
                        autofocus
                        autocomplete="off"
                        placeholder="Éditeur de contenu"
                        :aria-invalid="Boolean(errors.name)"
                    />
                    <FieldDescription>
                        Utilisez un nom court et reconnaissable qui décrit le
                        niveau d’accès.
                    </FieldDescription>
                    <FieldError v-if="errors.name">
                        {{ errors.name }}
                    </FieldError>
                </Field>

                <FieldSet
                    :data-invalid="permissionError(errors) ? true : undefined"
                >
                    <FieldLegend variant="label">Permissions</FieldLegend>
                    <FieldDescription>
                        Les utilisateurs héritent de toutes les autorisations
                        sélectionnées lorsque ce rôle leur est attribué.
                    </FieldDescription>

                    <FieldGroup v-if="permissions.length" class="gap-6">
                        <Field>
                            <FieldLabel for="permission-search">
                                Rechercher des autorisations
                            </FieldLabel>
                            <Input
                                id="permission-search"
                                v-model="permissionSearch"
                                type="search"
                                autocomplete="off"
                                placeholder="Rechercher par ressource, action ou description"
                            />
                            <FieldDescription>
                                Les actions de groupe sélectionnent ou effacent
                                toujours toutes les autorisations de la
                                ressource, y compris celles qui sont filtrées.
                            </FieldDescription>
                        </Field>

                        <template v-if="permissionGroups.length">
                            <FieldSet
                                v-for="group in permissionGroups"
                                :key="group.name"
                            >
                                <FieldLegend variant="label">
                                    {{ group.label }}
                                </FieldLegend>
                                <FieldGroup class="gap-3">
                                    <Field orientation="horizontal">
                                        <Checkbox
                                            :id="
                                                'permission-group-' + group.name
                                            "
                                            :model-value="
                                                groupSelectionState(group)
                                            "
                                            @update:model-value="
                                                togglePermissionGroup(
                                                    group,
                                                    $event,
                                                )
                                            "
                                        />
                                        <FieldContent>
                                            <FieldLabel
                                                :for="
                                                    'permission-group-' +
                                                    group.name
                                                "
                                                class="font-normal"
                                            >
                                                Sélectionner toutes les
                                                autorisations {{ group.label }}
                                            </FieldLabel>
                                            <FieldDescription>
                                                {{ selectedGroupCount(group) }}
                                                sur
                                                {{ group.permissions.length }}
                                                sélectionnées
                                            </FieldDescription>
                                        </FieldContent>
                                    </Field>

                                    <FieldGroup
                                        class="gap-3 sm:grid sm:grid-cols-2"
                                    >
                                        <Field
                                            v-for="permission in group.visiblePermissions"
                                            :key="permission.id"
                                            orientation="horizontal"
                                        >
                                            <Checkbox
                                                :id="
                                                    'permission-' +
                                                    permission.id
                                                "
                                                :model-value="
                                                    selectedPermissions.includes(
                                                        permission.name,
                                                    )
                                                "
                                                @update:model-value="
                                                    togglePermission(
                                                        permission.name,
                                                        $event,
                                                    )
                                                "
                                            />
                                            <FieldContent>
                                                <FieldLabel
                                                    :for="
                                                        'permission-' +
                                                        permission.id
                                                    "
                                                    class="font-normal"
                                                >
                                                    <span
                                                        class="flex flex-wrap items-center gap-2"
                                                    >
                                                        {{ permission.label }}
                                                        <Badge
                                                            v-if="
                                                                permission.is_sensitive
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Sensible
                                                        </Badge>
                                                        <Badge
                                                            v-if="
                                                                permission.is_orphaned
                                                            "
                                                            variant="outline"
                                                        >
                                                            Orpheline
                                                        </Badge>
                                                    </span>
                                                </FieldLabel>
                                                <FieldDescription>
                                                    {{ permission.description }}
                                                    <span
                                                        class="block font-mono text-xs"
                                                    >
                                                        {{ permission.name }}
                                                    </span>
                                                </FieldDescription>
                                            </FieldContent>
                                        </Field>
                                    </FieldGroup>
                                </FieldGroup>
                            </FieldSet>
                        </template>

                        <Empty v-else>
                            <EmptyHeader>
                                <EmptyTitle>
                                    Aucune autorisation correspondante
                                </EmptyTitle>
                                <EmptyDescription>
                                    Essayez une autre ressource, action,
                                    autorisation ou description.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </FieldGroup>
                    <Empty v-else>
                        <EmptyHeader>
                            <EmptyTitle
                                >Aucune autorisation disponible</EmptyTitle
                            >
                            <EmptyDescription>
                                Le rôle peut tout de même être enregistré sans
                                autorisation.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                    <FieldError v-if="permissionError(errors)">
                        {{ permissionError(errors) }}
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
