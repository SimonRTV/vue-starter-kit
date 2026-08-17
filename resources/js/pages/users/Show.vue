<script setup lang="ts">
import { setLayoutProps } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { PageHeader } from '@/components/application';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import UserAccountActions from '@/components/users/UserAccountActions.vue';
import { formatRoleName } from '@/lib/roleNames';
import type { ManagedUserDetail } from '@/types';

const props = defineProps<{
    user: ManagedUserDetail;
}>();

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
            title: 'Utilisateurs',
            href: UserController.index(),
        },
        {
            title: props.user.name,
            href: UserController.show(props.user.id),
        },
    ],
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader :title="user.name">
                <template #badge>
                    <Badge
                        :variant="
                            user.disabled_at
                                ? 'destructive'
                                : user.email_verified_at
                                  ? 'default'
                                  : 'secondary'
                        "
                    >
                        {{
                            user.disabled_at
                                ? 'Désactivé'
                                : user.email_verified_at
                                  ? 'Actif · vérifié'
                                  : 'Actif · non vérifié'
                        }}
                    </Badge>
                </template>
                <template #meta>
                    <p class="text-sm text-muted-foreground">
                        {{ user.email }}
                    </p>
                </template>
                <template
                    v-if="
                        user.can.update ||
                        user.can.delete ||
                        user.can.disable ||
                        user.can.enable ||
                        user.can.reset_password ||
                        user.can.reset_security
                    "
                    #actions
                >
                    <UserAccountActions :user="user" />
                </template>
            </PageHeader>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Compte</CardTitle>
                        <CardDescription>
                            Identité, vérification et dates du compte.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 text-sm">
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">Email</span>
                            <span class="font-medium break-all">
                                {{ user.email }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">
                                Statut du compte
                            </span>
                            <span class="font-medium">
                                {{ user.disabled_at ? 'Désactivé' : 'Actif' }}
                                <template v-if="user.disabled_at">
                                    depuis le {{ formatDate(user.disabled_at) }}
                                </template>
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">
                                Adresse e-mail vérifiée
                            </span>
                            <span class="font-medium">
                                {{
                                    user.email_verified_at
                                        ? formatDate(user.email_verified_at)
                                        : 'Non vérifiée'
                                }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">
                                Dernière invitation envoyée
                            </span>
                            <span class="font-medium">
                                {{ formatDate(user.invitation_sent_at) }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">
                                Dernière connexion
                            </span>
                            <span class="font-medium">
                                {{ formatDate(user.last_login_at) }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">Création</span>
                            <span class="font-medium">
                                {{ formatDate(user.created_at) }}
                            </span>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">
                                Dernière modification
                            </span>
                            <span class="font-medium">
                                {{ formatDate(user.updated_at) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Accès</CardTitle>
                        <CardDescription>
                            Rôles attribués et autorisations effectives.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-5">
                        <div class="flex flex-col gap-2">
                            <h2 class="text-sm font-medium">Rôles</h2>
                            <div
                                v-if="user.roles.length"
                                class="flex flex-wrap gap-2"
                            >
                                <Badge
                                    v-for="role in user.roles"
                                    :key="role"
                                    variant="secondary"
                                >
                                    {{ formatRoleName(role) }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                Aucun rôle attribué.
                            </p>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-2">
                            <h2 class="text-sm font-medium">Autorisations</h2>
                            <div
                                v-if="user.permissions.length"
                                class="flex flex-wrap gap-2"
                            >
                                <Badge
                                    v-for="permission in user.permissions"
                                    :key="permission"
                                    variant="outline"
                                >
                                    {{ permission }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                Aucune autorisation effective.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Activité du compte</CardTitle>
                        <CardDescription>
                            Actions administratives et de sécurité récentes pour
                            ce compte.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="user.activity.length" class="flex flex-col">
                            <template
                                v-for="(activity, index) in user.activity"
                                :key="activity.id"
                            >
                                <Separator v-if="index > 0" />
                                <div
                                    class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                                >
                                    <p class="text-sm font-medium">
                                        {{ activity.description }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ activity.actor_name ?? 'Système' }} ·
                                        {{ formatDate(activity.created_at) }}
                                    </p>
                                </div>
                            </template>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            Aucune activité de gestion n’a encore été
                            enregistrée.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </main>
    </div>
</template>
