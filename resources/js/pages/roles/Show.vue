<script setup lang="ts">
import { Link, setLayoutProps } from '@inertiajs/vue3';
import { Edit3, LockKeyhole, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { PageHeader } from '@/components/application';
import DeleteRoleButton from '@/components/roles/DeleteRoleButton.vue';
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
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatRoleName } from '@/lib/roleNames';
import type { ManagedRoleDetail } from '@/types';

type PermissionCapability = {
    name: string;
    label: string;
};

type PermissionGroup = {
    name: string;
    label: string;
    capabilities: PermissionCapability[];
};

const props = defineProps<{
    role: ManagedRoleDetail;
}>();

const roleName = formatRoleName(props.role.name);

const permissionPartLabels: Record<string, string> = {
    application_settings: 'Paramètres de l’application',
    assign_administrator: 'Attribuer le rôle Administrateur',
    assign_roles: 'Attribuer les rôles',
    create: 'Créer',
    delete: 'Supprimer',
    other: 'Autres',
    pages: 'Pages',
    reset_password: 'Réinitialiser le mot de passe',
    reset_security: 'Réinitialiser la sécurité',
    roles: 'Rôles',
    suspend: 'Suspendre',
    update: 'Modifier',
    users: 'Utilisateurs',
    verify_email: 'Vérifier l’adresse e-mail',
    view: 'Consulter',
};

const permissionGroups = computed<PermissionGroup[]>(() => {
    const groups = new Map<string, PermissionCapability[]>();

    for (const permission of props.role.permissions) {
        const [resource, ...capabilityParts] = permission.split('.');
        const capability = capabilityParts.join('.');
        const groupName = capability ? resource : 'other';
        const groupCapabilities = groups.get(groupName) ?? [];

        groupCapabilities.push({
            name: permission,
            label: formatPermissionPart(capability || permission),
        });
        groups.set(groupName, groupCapabilities);
    }

    return [...groups.entries()].map(([name, capabilities]) => ({
        name,
        label: formatPermissionPart(name),
        capabilities,
    }));
});

function formatPermissionPart(value: string): string {
    if (permissionPartLabels[value]) {
        return permissionPartLabels[value];
    }

    const label = value.replaceAll(/[._-]/g, ' ');

    return label.charAt(0).toUpperCase() + label.slice(1);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-CH', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(value));
}

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Rôles',
            href: RoleController.index(),
        },
        {
            title: roleName,
            href: RoleController.show(props.role.id),
        },
    ],
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader :title="roleName">
                <template v-if="role.is_protected" #badge>
                    <Badge variant="secondary">Protégé</Badge>
                </template>
                <template #meta>
                    <p class="text-sm text-muted-foreground">
                        {{ role.permissions_count }}
                        {{
                            role.permissions_count === 1
                                ? 'autorisation'
                                : 'autorisations'
                        }}
                        · {{ role.users_count }}
                        {{
                            role.users_count === 1
                                ? 'utilisateur'
                                : 'utilisateurs'
                        }}
                    </p>
                </template>
                <template v-if="role.can.update || role.can.delete" #actions>
                    <Button v-if="role.can.update" variant="outline" as-child>
                        <Link :href="RoleController.edit(role.id)">
                            <Edit3 data-icon="inline-start" />
                            Modifier le rôle
                        </Link>
                    </Button>
                    <DeleteRoleButton v-if="role.can.delete" :role="role" />
                </template>
            </PageHeader>

            <Alert v-if="role.is_protected">
                <LockKeyhole />
                <AlertTitle>Rôle Administrateur protégé</AlertTitle>
                <AlertDescription>
                    Ce rôle est géré par l’application et ne peut pas être
                    renommé, modifié ou supprimé.
                </AlertDescription>
            </Alert>

            <Alert v-else-if="role.assigned_to_current_user">
                <ShieldAlert />
                <AlertTitle>Attribué à votre compte</AlertTitle>
                <AlertDescription>
                    Vous ne pouvez pas modifier un rôle attribué à votre propre
                    compte. Cette protection évite une perte d’accès
                    accidentelle.
                </AlertDescription>
            </Alert>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Autorisations</CardTitle>
                        <CardDescription>
                            Autorisations héritées par chaque utilisateur ayant
                            ce rôle.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="permissionGroups.length">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Ressource</TableHead>
                                    <TableHead>Autorisations</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="group in permissionGroups"
                                    :key="group.name"
                                >
                                    <TableCell class="align-top font-medium">
                                        {{ group.label }}
                                    </TableCell>
                                    <TableCell class="whitespace-normal">
                                        <div class="flex flex-wrap gap-2">
                                            <Badge
                                                v-for="capability in group.capabilities"
                                                :key="capability.name"
                                                variant="outline"
                                                :title="capability.name"
                                            >
                                                {{ capability.label }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="text-sm text-muted-foreground">
                            Ce rôle n’accorde aucune autorisation.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Utilisateurs attribués</CardTitle>
                        <CardDescription>
                            <template v-if="role.can_view_assigned_users">
                                Comptes qui reçoivent actuellement les accès de
                                ce rôle.
                            </template>
                            <template v-else>
                                Les totaux d’attribution sont visibles sans
                                exposer l’identité des utilisateurs.
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <p
                            v-if="!role.can_view_assigned_users"
                            class="text-sm text-muted-foreground"
                        >
                            {{ role.users_count }}
                            {{
                                role.users_count === 1
                                    ? 'compte est'
                                    : 'comptes sont'
                            }}
                            attribué. Vous devez avoir l’autorisation de
                            consulter les utilisateurs pour afficher leurs noms
                            et adresses e-mail.
                        </p>
                        <template v-else-if="role.assigned_users.length">
                            <template
                                v-for="(user, index) in role.assigned_users"
                                :key="user.id"
                            >
                                <Separator v-if="index > 0" />
                                <div class="flex min-w-0 flex-col gap-1">
                                    <Link
                                        :href="UserController.show(user.id)"
                                        class="truncate text-sm font-medium hover:underline"
                                    >
                                        {{ user.name }}
                                    </Link>
                                    <span
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ user.email }}
                                    </span>
                                </div>
                            </template>
                            <p
                                v-if="
                                    role.users_count >
                                    role.assigned_users.length
                                "
                                class="text-xs text-muted-foreground"
                            >
                                Affichage de
                                {{ role.assigned_users.length }} utilisateurs
                                sur {{ role.users_count }}.
                            </p>
                        </template>
                        <p v-else class="text-sm text-muted-foreground">
                            Aucun utilisateur n’a ce rôle.
                        </p>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Historique du rôle</CardTitle>
                        <CardDescription>
                            Dates de création et de dernière modification.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 text-sm sm:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">Création</span>
                            <span class="font-medium">
                                {{ formatDate(role.created_at) }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">
                                Dernière modification
                            </span>
                            <span class="font-medium">
                                {{ formatDate(role.updated_at) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </div>
</template>
