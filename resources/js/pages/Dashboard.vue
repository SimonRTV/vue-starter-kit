<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    BriefcaseBusiness,
    CalendarDays,
    Clock,
    DollarSign,
    SlidersHorizontal,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import { index as activityIndex } from '@/actions/App/Http/Controllers/ActivityController';
import { edit as editAppearance } from '@/routes/appearance';
import { dashboard } from '@/routes';

type DashboardStat = {
    label: string;
    value: string;
    change: string;
    detail: string;
    icon: Component;
};

const page = usePage();
const period = ref<'all' | 'recent'>('all');

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

const displayedRevenue = computed(() =>
    period.value === 'recent' ? revenueByMonth.slice(-3) : revenueByMonth,
);

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
    <div class="admin-dashboard @container/main flex flex-1 flex-col">
        <div
            class="admin-page mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-8 p-5 md:p-8 lg:p-10"
        >
            <header
                class="flex flex-col justify-between gap-5 @3xl/main:flex-row @3xl/main:items-end"
            >
                <div>
                    <p
                        class="text-primary mb-4 flex items-center gap-2 text-[10px] font-semibold tracking-[0.22em] uppercase"
                    >
                        <span class="bg-primary size-1.5 rounded-full" /> Votre
                        espace, en un regard
                    </p>
                    <h1
                        class="text-4xl font-medium tracking-[-0.055em] sm:text-5xl lg:text-6xl"
                    >
                        Bonjour, {{ firstName
                        }}<span class="text-primary">.</span>
                    </h1>
                    <p class="text-muted-foreground mt-3 text-sm">
                        Une vue claire pour garder une longueur d’avance.
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    <span
                        class="text-muted-foreground rounded-full border px-3 py-1.5 text-[11px]"
                        >Données d’exemple</span
                    >
                    <Button variant="outline" size="sm" as-child
                        ><Link :href="editAppearance()"
                            ><SlidersHorizontal class="size-3.5" />
                            Personnaliser</Link
                        ></Button
                    >
                </div>
            </header>

            <section
                aria-label="Vue d’ensemble de l’activité"
                class="admin-metrics grid grid-cols-2 overflow-hidden rounded-2xl border @3xl/main:grid-cols-4"
            >
                <article
                    v-for="(stat, index) in stats"
                    :key="stat.label"
                    class="admin-metric flex min-w-0 flex-col gap-4 p-4 lg:p-6"
                >
                    <div
                        class="text-muted-foreground flex items-center justify-between gap-3"
                    >
                        <span class="text-[11px] font-medium">{{
                            stat.label
                        }}</span>
                        <component
                            :is="stat.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </div>
                    <p
                        class="text-2xl font-medium tracking-[-0.045em] tabular-nums md:text-3xl xl:text-4xl"
                    >
                        {{ stat.value }}
                    </p>
                    <div class="flex items-center gap-2 text-[11px]">
                        <span
                            class="text-primary bg-primary/8 inline-flex shrink-0 items-center gap-0.5 rounded-full px-1.5 py-0.5"
                            ><ArrowUpRight class="size-3" />{{
                                stat.change
                            }}</span
                        >
                        <span class="text-muted-foreground">{{
                            index === 0 ? 'vs. mois dernier' : stat.detail
                        }}</span>
                    </div>
                </article>
            </section>

            <section
                aria-label="Vue d’ensemble des performances"
                class="grid gap-6 @4xl/main:grid-cols-[minmax(0,1.8fr)_minmax(260px,1fr)]"
            >
                <article
                    class="admin-panel min-w-0 overflow-hidden rounded-2xl border"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4 p-6 pb-0"
                    >
                        <div>
                            <p class="admin-eyebrow">Performance</p>
                            <h2 class="mt-2 text-xl font-medium tracking-tight">
                                Le rythme de votre croissance
                            </h2>
                        </div>
                        <div
                            class="bg-muted flex rounded-full p-1"
                            role="group"
                            aria-label="Période du graphique"
                        >
                            <button
                                v-for="option in [
                                    { value: 'all', label: '7 mois' },
                                    { value: 'recent', label: '3 mois' },
                                ] as const"
                                :key="option.value"
                                type="button"
                                :aria-pressed="period === option.value"
                                :class="[
                                    'focus-visible:outline-ring rounded-full px-3 py-1.5 text-xs transition-colors focus-visible:outline-2 focus-visible:outline-offset-2',
                                    period === option.value
                                        ? 'bg-card text-foreground shadow-sm'
                                        : 'text-muted-foreground',
                                ]"
                                @click="period = option.value"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-3 px-6 pt-6">
                        <span
                            class="text-4xl font-medium tracking-[-0.05em] tabular-nums"
                            >86 000
                            <span class="text-muted-foreground text-xl"
                                >$</span
                            ></span
                        >
                        <span class="text-primary text-xs">+12,5 %</span>
                    </div>
                    <p class="text-muted-foreground px-6 pt-1 text-xs">
                        Chiffre d’affaires · août, données d’exemple
                    </p>
                    <div class="px-6 pt-6 pb-5">
                        <div
                            class="relative h-48"
                            role="img"
                            :aria-label="
                                period === 'all'
                                    ? 'Chiffre d’affaires mensuel : février 42 000, mars 55 000, avril 49 000, mai 67 000, juin 58 000, juillet 76 000, août 86 000 dollars.'
                                    : 'Chiffre d’affaires mensuel : juin 58 000, juillet 76 000, août 86 000 dollars.'
                            "
                        >
                            <div
                                class="pointer-events-none absolute inset-0 flex flex-col justify-between pb-7"
                                aria-hidden="true"
                            >
                                <div
                                    v-for="line in 4"
                                    :key="line"
                                    class="border-border/80 border-t border-dashed"
                                />
                            </div>
                            <div
                                class="relative flex h-full items-end gap-3 sm:gap-5"
                                aria-hidden="true"
                            >
                                <div
                                    v-for="month in displayedRevenue"
                                    :key="month.month"
                                    class="group flex h-full min-w-0 flex-1 flex-col justify-end gap-2"
                                >
                                    <span
                                        class="text-muted-foreground text-center text-[10px] tabular-nums"
                                        >{{ month.amount }}</span
                                    >
                                    <div
                                        :class="[
                                            'admin-chart-bar mx-auto w-full max-w-16 rounded-t-md transition-all duration-300',
                                            month.current
                                                ? 'bg-primary'
                                                : 'bg-primary/20 group-hover:bg-primary/50',
                                        ]"
                                        :style="{
                                            height: `${month.height * 1.35}px`,
                                        }"
                                    />
                                    <span
                                        class="text-muted-foreground text-center text-[10px]"
                                        >{{ month.month }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-muted/40 flex flex-wrap items-center justify-between gap-3 border-t px-6 py-4 text-xs"
                    >
                        <span class="text-muted-foreground"
                            >Croissance moyenne
                            <strong class="text-foreground ml-2 font-medium"
                                >8,4 % / mois</strong
                            ></span
                        ><span class="text-muted-foreground"
                            >Panier moyen
                            <strong class="text-foreground ml-2 font-medium"
                                >248,60 $</strong
                            ></span
                        >
                    </div>
                </article>

                <article
                    class="admin-focus-panel relative flex min-w-0 flex-col overflow-hidden rounded-2xl p-6"
                >
                    <div
                        class="admin-orbit pointer-events-none absolute -top-14 -right-16 size-56 rounded-full border"
                        aria-hidden="true"
                    >
                        <div class="absolute inset-6 rounded-full border" />
                        <div class="absolute inset-12 rounded-full border" />
                    </div>
                    <div class="relative flex items-center justify-between">
                        <p
                            class="text-[10px] font-semibold tracking-[0.2em] uppercase opacity-60"
                        >
                            Le cap du trimestre
                        </p>
                        <ArrowUpRight class="size-5 opacity-70" />
                    </div>
                    <div class="relative mt-7 mb-8">
                        <span class="text-6xl font-light tracking-[-0.06em]"
                            >86<span class="text-3xl opacity-50">%</span></span
                        >
                        <h2 class="mt-3 text-xl font-medium tracking-tight">
                            L’objectif se rapproche.
                        </h2>
                        <p
                            class="mt-2 max-w-56 text-xs leading-relaxed opacity-60"
                        >
                            Chaque avancée compte. Gardez vos priorités en
                            perspective.
                        </p>
                    </div>
                    <div class="relative mt-auto flex flex-col gap-5">
                        <div v-for="goal in goals" :key="goal.label">
                            <div
                                class="mb-2 flex items-center justify-between gap-2 text-xs"
                            >
                                <span class="opacity-75">{{ goal.label }}</span
                                ><span class="tabular-nums"
                                    >{{ goal.progress }} %</span
                                >
                            </div>
                            <div
                                role="progressbar"
                                :aria-label="goal.label"
                                :aria-valuenow="goal.progress"
                                :aria-valuemin="0"
                                :aria-valuemax="100"
                                :aria-valuetext="goal.value"
                                class="h-1 overflow-hidden rounded-full bg-current/10"
                            >
                                <div
                                    class="admin-goal-fill h-full rounded-full"
                                    :style="{ width: `${goal.progress}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <section
                aria-label="Détails de l’espace de travail"
                class="grid gap-6 @4xl/main:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]"
            >
                <article class="admin-panel min-w-0 rounded-2xl border p-6">
                    <div class="mb-6 flex items-center justify-between gap-3">
                        <div>
                            <p class="admin-eyebrow">Le fil de l’équipe</p>
                            <h2 class="mt-2 text-xl font-medium tracking-tight">
                                Activité récente
                            </h2>
                        </div>
                        <Button
                            v-if="page.props.auth.can.viewActivity"
                            variant="ghost"
                            size="icon"
                            as-child
                            ><Link
                                :href="activityIndex()"
                                aria-label="Ouvrir le journal d’activité"
                                ><ArrowUpRight class="size-5" /></Link
                        ></Button>
                    </div>
                    <ol class="flex flex-col">
                        <li
                            v-for="(activity, index) in recentActivity"
                            :key="activity.initials"
                            class="relative flex gap-4 pb-6 last:pb-0"
                        >
                            <span
                                v-if="index < recentActivity.length - 1"
                                class="bg-border absolute top-9 bottom-0 left-[17px] w-px"
                                aria-hidden="true"
                            />
                            <span
                                class="bg-muted text-muted-foreground relative flex size-9 shrink-0 items-center justify-center rounded-full text-[10px] font-medium"
                                >{{ activity.initials }}</span
                            >
                            <div class="min-w-0 pt-0.5">
                                <p class="text-xs leading-relaxed">
                                    <span class="font-semibold">{{
                                        activity.name
                                    }}</span>
                                    <span class="text-muted-foreground">{{
                                        ` ${activity.action}`
                                    }}</span>
                                </p>
                                <p
                                    class="text-muted-foreground mt-1 text-[10px]"
                                >
                                    {{ activity.time }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </article>
                <article class="admin-panel min-w-0 rounded-2xl border p-6">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <p class="admin-eyebrow">À l’horizon</p>
                            <h2 class="mt-2 text-xl font-medium tracking-tight">
                                Prochaines échéances
                            </h2>
                        </div>
                        <CalendarDays class="text-muted-foreground size-5" />
                    </div>
                    <ol class="divide-y">
                        <li
                            v-for="event in schedule"
                            :key="event.title"
                            class="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                        >
                            <div
                                class="bg-muted flex h-14 w-12 shrink-0 flex-col items-center justify-center rounded-lg"
                            >
                                <span
                                    class="text-xl font-medium tabular-nums"
                                    >{{ event.date }}</span
                                ><span
                                    class="text-muted-foreground text-[9px] uppercase"
                                    >{{ event.month }}</span
                                >
                            </div>
                            <div class="min-w-0 flex-1">
                                <span
                                    class="text-primary text-[9px] font-semibold tracking-wider uppercase"
                                    >{{ event.type }}</span
                                >
                                <p class="my-1 text-xs font-medium">
                                    {{ event.title }}
                                </p>
                                <p
                                    class="text-muted-foreground flex items-center gap-1.5 text-[10px]"
                                >
                                    <Clock class="size-3" />{{ event.time }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </article>
            </section>
            <footer
                class="text-muted-foreground flex flex-wrap items-center justify-between gap-3 border-t pt-5 text-[10px]"
            >
                <span>Votre espace de travail. Votre perspective.</span
                ><Link
                    :href="editAppearance()"
                    class="hover:text-primary inline-flex items-center gap-2"
                    >Un espace à votre image <ArrowRight class="size-3"
                /></Link>
            </footer>
        </div>
    </div>
</template>
