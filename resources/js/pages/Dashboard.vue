<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BriefcaseBusiness,
    CalendarDays,
    ChartNoAxesCombined,
    CircleCheck,
    Clock,
    DollarSign,
    ShoppingBag,
    Sparkles,
    Target,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type DashboardStat = {
    label: string;
    value: string;
    change: string;
    detail: string;
    icon: Component;
};

const page = usePage();

const firstName = computed(
    () => page.props.auth.user.name.trim().split(/\s+/)[0] || 'vous',
);

const stats: DashboardStat[] = [
    {
        label: 'Chiffre d’affaires total',
        value: '124 563 $',
        change: '+12,5 %',
        detail: 'Par rapport au mois dernier',
        icon: DollarSign,
    },
    {
        label: 'Nouveaux clients',
        value: '1 429',
        change: '+8,2 %',
        detail: '108 arrivés cette semaine',
        icon: Users,
    },
    {
        label: 'Projets actifs',
        value: '24',
        change: '+3',
        detail: '7 à livrer ce mois-ci',
        icon: BriefcaseBusiness,
    },
    {
        label: 'Taux de conversion',
        value: '4,8 %',
        change: '+0,6 %',
        detail: 'Contre 4,2 % le mois dernier',
        icon: TrendingUp,
    },
];

const revenueByMonth = [
    { month: 'Fév.', amount: '42 k$', height: 42 },
    { month: 'Mars', amount: '55 k$', height: 55 },
    { month: 'Avr.', amount: '49 k$', height: 49 },
    { month: 'Mai', amount: '67 k$', height: 67 },
    { month: 'Juin', amount: '58 k$', height: 58 },
    { month: 'Juil.', amount: '76 k$', height: 76 },
    { month: 'Août', amount: '86 k$', height: 86, current: true },
];

const goals = [
    {
        label: 'Chiffre d’affaires mensuel',
        value: '86 k$ sur 100 k$',
        progress: 86,
    },
    {
        label: 'Nouveaux clients',
        value: '1 429 sur 1 800',
        progress: 79,
    },
    {
        label: 'Projets livrés',
        value: '18 sur 24',
        progress: 75,
    },
];

const recentActivity = [
    {
        initials: 'OM',
        name: 'Olivia Martin',
        action: 'a clôturé le projet de refonte du site web',
        time: 'il y a 8 minutes',
    },
    {
        initials: 'JL',
        name: 'Jackson Lee',
        action: 'a ajouté Northstar Labs comme nouveau client',
        time: 'il y a 32 minutes',
    },
    {
        initials: 'SK',
        name: 'Sofia Kim',
        action: 'a partagé le rapport de campagne du 3e trimestre',
        time: 'il y a 1 heure',
    },
    {
        initials: 'MW',
        name: 'Marcus Wright',
        action: 'a terminé 6 tâches d’intégration',
        time: 'il y a 3 heures',
    },
];

const schedule = [
    {
        date: '14',
        month: 'Août',
        title: 'Revue de conception',
        time: '10:00–10:45',
        type: 'Équipe',
    },
    {
        date: '14',
        month: 'Août',
        title: 'Synchronisation hebdomadaire',
        time: '13:30–14:00',
        type: 'Interne',
    },
    {
        date: '15',
        month: 'Août',
        title: 'Lancement de Northstar Labs',
        time: '09:30–10:30',
        type: 'Client',
    },
];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tableau de bord',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <div class="@container/main flex flex-1 flex-col">
        <Head title="Tableau de bord" />

        <main
            class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <header
                class="flex flex-col gap-4 @3xl/main:flex-row @3xl/main:items-center @3xl/main:justify-between"
            >
                <div class="flex min-w-0 flex-col gap-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            Bonjour, {{ firstName }}
                        </h1>
                        <Badge variant="outline">
                            <Sparkles />
                            Données d’exemple
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground sm:text-base">
                        Voici ce qui se passe aujourd’hui dans votre espace de
                        travail.
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <CircleCheck
                        class="size-4 text-primary"
                        aria-hidden="true"
                    />
                    <span>Tous les systèmes sont opérationnels</span>
                </div>
            </header>

            <section
                aria-label="Vue d’ensemble de l’activité"
                class="grid gap-4 @xl/main:grid-cols-2 @5xl/main:grid-cols-4"
            >
                <Card v-for="stat in stats" :key="stat.label" class="min-w-0">
                    <CardHeader>
                        <CardDescription>{{ stat.label }}</CardDescription>
                        <CardAction>
                            <div
                                class="rounded-lg bg-muted p-2 text-muted-foreground"
                                aria-hidden="true"
                            >
                                <component :is="stat.icon" class="size-5" />
                            </div>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-3xl font-semibold tracking-tight tabular-nums"
                        >
                            {{ stat.value }}
                        </p>
                    </CardContent>
                    <CardFooter class="flex-wrap gap-2">
                        <Badge variant="secondary">
                            <ArrowUpRight />
                            {{ stat.change }}
                        </Badge>
                        <span class="text-xs text-muted-foreground">
                            {{ stat.detail }}
                        </span>
                    </CardFooter>
                </Card>
            </section>

            <section
                aria-label="Vue d’ensemble des performances"
                class="grid gap-6 @5xl/main:grid-cols-12"
            >
                <Card class="min-w-0 @5xl/main:col-span-8">
                    <CardHeader>
                        <CardTitle
                            >Vue d’ensemble du chiffre d’affaires</CardTitle
                        >
                        <CardDescription>
                            Chiffre d’affaires mensuel récurrent des 7 derniers
                            mois
                        </CardDescription>
                        <CardAction>
                            <Badge variant="secondary">
                                <TrendingUp />
                                12,5 %
                            </Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <div
                            role="img"
                            aria-label="Le chiffre d’affaires mensuel est passé de 42 000 dollars en février à 86 000 dollars en août"
                            class="flex h-64 items-end gap-2 sm:gap-4"
                        >
                            <div
                                v-for="month in revenueByMonth"
                                :key="month.month"
                                class="flex h-full min-w-0 flex-1 flex-col justify-end gap-3"
                            >
                                <div
                                    class="flex h-full items-end rounded-lg bg-muted/60 p-1"
                                >
                                    <div
                                        :class="
                                            cn(
                                                'w-full rounded-md transition-[height] duration-500',
                                                month.current
                                                    ? 'bg-primary'
                                                    : 'bg-primary/25',
                                            )
                                        "
                                        :style="{ height: `${month.height}%` }"
                                        :title="`${month.month}: ${month.amount}`"
                                    />
                                </div>
                                <div class="flex flex-col items-center gap-0.5">
                                    <span class="text-xs font-medium">
                                        {{ month.month }}
                                    </span>
                                    <span
                                        class="hidden text-[11px] text-muted-foreground @2xl/main:inline"
                                    >
                                        {{ month.amount }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="flex-wrap gap-x-8 gap-y-3">
                        <div class="flex items-center gap-2">
                            <ChartNoAxesCombined
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="flex flex-col">
                                <span class="text-xs text-muted-foreground">
                                    Croissance moyenne
                                </span>
                                <span class="text-sm font-medium"
                                    >8,4 % par mois</span
                                >
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <ShoppingBag
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="flex flex-col">
                                <span class="text-xs text-muted-foreground">
                                    Panier moyen
                                </span>
                                <span class="text-sm font-medium"
                                    >248,60 $</span
                                >
                            </div>
                        </div>
                    </CardFooter>
                </Card>

                <Card class="min-w-0 @5xl/main:col-span-4">
                    <CardHeader>
                        <CardTitle>Objectifs trimestriels</CardTitle>
                        <CardDescription>
                            Progression de vos principaux objectifs
                        </CardDescription>
                        <CardAction>
                            <div
                                class="rounded-lg bg-muted p-2 text-muted-foreground"
                                aria-hidden="true"
                            >
                                <Target class="size-5" />
                            </div>
                        </CardAction>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-6">
                        <div
                            v-for="goal in goals"
                            :key="goal.label"
                            class="flex flex-col gap-2.5"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <span class="text-sm font-medium">
                                    {{ goal.label }}
                                </span>
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ goal.progress }} %
                                </span>
                            </div>
                            <div
                                role="progressbar"
                                :aria-label="goal.label"
                                :aria-valuenow="goal.progress"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                class="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-[width] duration-500"
                                    :style="{ width: `${goal.progress}%` }"
                                />
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ goal.value }}
                            </span>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <div class="flex items-start gap-2 text-sm">
                            <CircleCheck
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <p class="text-muted-foreground">
                                Vous êtes en bonne voie pour atteindre les trois
                                objectifs ce trimestre.
                            </p>
                        </div>
                    </CardFooter>
                </Card>
            </section>

            <section
                aria-label="Détails de l’espace de travail"
                class="grid gap-6 @5xl/main:grid-cols-12"
            >
                <Card class="min-w-0 @5xl/main:col-span-7">
                    <CardHeader>
                        <CardTitle>Activité récente</CardTitle>
                        <CardDescription>
                            Les dernières nouvelles de votre équipe
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col">
                            <template
                                v-for="(activity, index) in recentActivity"
                                :key="activity.name"
                            >
                                <Separator v-if="index > 0" />
                                <div
                                    class="flex items-start gap-3 py-4 first:pt-0 last:pb-0"
                                >
                                    <Avatar class="size-9">
                                        <AvatarFallback>
                                            {{ activity.initials }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm leading-relaxed">
                                            <span class="font-medium">
                                                {{ activity.name }}
                                            </span>
                                            {{ activity.action }}
                                        </p>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ activity.time }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </CardContent>
                </Card>

                <Card class="min-w-0 @5xl/main:col-span-5">
                    <CardHeader>
                        <CardTitle>Prochaines échéances</CardTitle>
                        <CardDescription>
                            Vos prochaines réunions et étapes clés
                        </CardDescription>
                        <CardAction>
                            <div
                                class="rounded-lg bg-muted p-2 text-muted-foreground"
                                aria-hidden="true"
                            >
                                <CalendarDays class="size-5" />
                            </div>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col">
                            <template
                                v-for="(event, index) in schedule"
                                :key="`${event.date}-${event.title}`"
                            >
                                <Separator v-if="index > 0" />
                                <div
                                    class="flex items-center gap-3 py-4 first:pt-0 last:pb-0"
                                >
                                    <div
                                        class="flex size-12 shrink-0 flex-col items-center justify-center rounded-lg bg-muted"
                                    >
                                        <span
                                            class="text-sm font-semibold tabular-nums"
                                        >
                                            {{ event.date }}
                                        </span>
                                        <span
                                            class="text-[10px] tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{ event.month }}
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            {{ event.title }}
                                        </p>
                                        <span
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <Clock
                                                class="size-3"
                                                aria-hidden="true"
                                            />
                                            {{ event.time }}
                                        </span>
                                    </div>
                                    <Badge variant="outline">
                                        {{ event.type }}
                                    </Badge>
                                </div>
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </main>
    </div>
</template>
