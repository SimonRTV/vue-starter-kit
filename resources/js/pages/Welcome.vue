<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Layers3,
    Menu,
    ShieldCheck,
    Sparkles,
    Zap,
} from '@lucide/vue';
import { ref } from 'vue';
import AppLogoFull from '@/components/AppLogoFull.vue';
import FrontendAppearanceSwitch from '@/components/FrontendAppearanceSwitch.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { dashboard, home, login } from '@/routes';

const navigationItems = [
    { label: 'Features', href: '#features' },
    { label: 'How it works', href: '#workflow' },
    { label: 'About', href: '#about' },
] as const;

const isMobileMenuOpen = ref(false);

function navigateToSection(href: string): void {
    isMobileMenuOpen.value = false;

    window.setTimeout(() => {
        document.querySelector<HTMLElement>(href)?.scrollIntoView({
            block: 'start',
        });
        window.history.replaceState(null, '', href);
    }, 350);
}

const workflowSteps = [
    {
        number: '01',
        title: 'Bring everything together',
        description:
            'Create one dependable place for your team, priorities, and day-to-day work.',
    },
    {
        number: '02',
        title: 'Shape the way you work',
        description:
            'Start simple, then adapt your workspace as your needs and processes evolve.',
    },
    {
        number: '03',
        title: 'Move forward with clarity',
        description:
            'Keep decisions visible, make ownership clear, and turn plans into steady progress.',
    },
] as const;
</script>

<template>
    <div class="min-h-screen overflow-x-hidden bg-background text-foreground">
        <header
            class="sticky top-0 z-40 border-b border-border/70 bg-background/85 backdrop-blur-xl"
        >
            <div
                class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8 lg:px-10"
            >
                <Link
                    :href="home()"
                    class="inline-flex min-w-0 items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    :aria-label="`${$page.props.name} home`"
                >
                    <AppLogoFull class="h-8 w-auto max-w-40 text-foreground" />
                </Link>

                <nav
                    aria-label="Primary navigation"
                    class="hidden items-center gap-8 md:flex"
                >
                    <a
                        v-for="item in navigationItems"
                        :key="item.href"
                        :href="item.href"
                        class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {{ item.label }}
                    </a>
                </nav>

                <div class="flex items-center gap-2">
                    <FrontendAppearanceSwitch />
                    <Button
                        v-if="$page.props.auth.user"
                        as-child
                        class="hidden md:inline-flex"
                    >
                        <Link :href="dashboard()" prefetch>
                            Dashboard
                            <ArrowRight data-icon="inline-end" />
                        </Link>
                    </Button>
                    <Button
                        v-else
                        as-child
                        variant="ghost"
                        class="hidden md:inline-flex"
                    >
                        <Link :href="login()" prefetch>Log in</Link>
                    </Button>

                    <Sheet v-model:open="isMobileMenuOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="md:hidden"
                                aria-label="Open navigation menu"
                            >
                                <Menu />
                            </Button>
                        </SheetTrigger>
                        <SheetContent class="w-full sm:max-w-sm">
                            <SheetHeader>
                                <SheetTitle>Menu</SheetTitle>
                                <SheetDescription>
                                    Explore the platform or access your
                                    workspace.
                                </SheetDescription>
                            </SheetHeader>

                            <nav
                                aria-label="Mobile navigation"
                                class="flex flex-1 flex-col gap-2 px-4"
                            >
                                <a
                                    v-for="item in navigationItems"
                                    :key="item.href"
                                    :href="item.href"
                                    class="rounded-lg px-3 py-3 text-base font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @click.prevent="
                                        navigateToSection(item.href)
                                    "
                                >
                                    {{ item.label }}
                                </a>
                            </nav>

                            <div class="p-4">
                                <Button
                                    v-if="$page.props.auth.user"
                                    as-child
                                    class="w-full"
                                >
                                    <Link :href="dashboard()">
                                        Open dashboard
                                        <ArrowRight data-icon="inline-end" />
                                    </Link>
                                </Button>
                                <Button v-else as-child class="w-full">
                                    <Link :href="login()">
                                        Log in
                                        <ArrowRight data-icon="inline-end" />
                                    </Link>
                                </Button>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>

        <main>
            <section class="relative isolate">
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -top-24 left-1/2 size-96 -translate-x-1/2 rounded-full bg-primary/8 blur-3xl"
                />
                <div
                    class="mx-auto grid max-w-7xl items-center gap-16 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-[1.05fr_0.95fr] lg:px-10 lg:py-32"
                >
                    <div class="flex max-w-3xl flex-col items-start gap-7">
                        <Badge variant="secondary">
                            <Sparkles />
                            Thoughtful by default
                        </Badge>

                        <div class="flex flex-col gap-6">
                            <h1
                                class="max-w-3xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl"
                            >
                                A clearer way to turn ideas into
                                <span class="text-muted-foreground"
                                    >progress.</span
                                >
                            </h1>
                            <p
                                class="max-w-2xl text-lg leading-8 text-pretty text-muted-foreground sm:text-xl"
                            >
                                Bring your people, priorities, and everyday work
                                into one calm, flexible workspace built to help
                                everyone move in the same direction.
                            </p>
                        </div>

                        <div
                            class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row"
                        >
                            <Button
                                v-if="$page.props.auth.user"
                                as-child
                                size="lg"
                            >
                                <Link :href="dashboard()" prefetch>
                                    Open your workspace
                                    <ArrowRight data-icon="inline-end" />
                                </Link>
                            </Button>
                            <Button v-else as-child size="lg">
                                <Link :href="login()" prefetch>
                                    Log in to get started
                                    <ArrowRight data-icon="inline-end" />
                                </Link>
                            </Button>
                            <Button as-child size="lg" variant="outline">
                                <a href="#features">Explore the platform</a>
                            </Button>
                        </div>

                        <div
                            class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:gap-6"
                        >
                            <span class="inline-flex items-center gap-2">
                                <Check class="size-4 text-foreground" />
                                Quick to understand
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <Check class="size-4 text-foreground" />
                                Flexible as you grow
                            </span>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl lg:max-w-none">
                        <div
                            aria-hidden="true"
                            class="absolute -inset-5 rounded-[2rem] bg-muted/70 blur-2xl"
                        />
                        <div
                            class="relative animate-in rounded-[1.75rem] border border-border/70 bg-muted/35 p-3 shadow-2xl shadow-foreground/5 duration-1000 fade-in slide-in-from-top-8 sm:p-5 delay-150"
                        >
                            <Card>
                                <CardHeader>
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div class="flex flex-col gap-1.5">
                                            <CardTitle
                                                >Launch workspace</CardTitle
                                            >
                                            <CardDescription>
                                                Everything important, in one
                                                view.
                                            </CardDescription>
                                        </div>
                                        <Badge variant="outline"
                                            >On track</Badge
                                        >
                                    </div>
                                </CardHeader>
                                <CardContent class="flex flex-col gap-5">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-xl bg-muted p-4">
                                            <p
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                Progress
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold"
                                            >
                                                72%
                                            </p>
                                        </div>
                                        <div class="rounded-xl bg-muted p-4">
                                            <p
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                Next milestone
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold"
                                            >
                                                Friday
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <div
                                            class="flex items-center justify-between gap-4 rounded-xl border border-border/70 p-3"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="flex size-9 items-center justify-center rounded-lg bg-secondary"
                                                >
                                                    <Check class="size-4" />
                                                </span>
                                                <div>
                                                    <p
                                                        class="text-sm font-medium"
                                                    >
                                                        Direction approved
                                                    </p>
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Shared with the whole
                                                        team
                                                    </p>
                                                </div>
                                            </div>
                                            <Badge variant="secondary"
                                                >Done</Badge
                                            >
                                        </div>
                                        <div
                                            class="flex items-center justify-between gap-4 rounded-xl border border-border/70 p-3"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="flex size-9 items-center justify-center rounded-lg bg-secondary"
                                                >
                                                    <Zap class="size-4" />
                                                </span>
                                                <div>
                                                    <p
                                                        class="text-sm font-medium"
                                                    >
                                                        Final review
                                                    </p>
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Three contributors
                                                        active
                                                    </p>
                                                </div>
                                            </div>
                                            <Badge variant="outline"
                                                >Today</Badge
                                            >
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="features"
                aria-labelledby="features-heading"
                class="scroll-mt-24 border-y border-border/70 bg-muted/35"
            >
                <div
                    class="mx-auto flex max-w-7xl flex-col gap-12 px-5 py-20 sm:px-8 sm:py-24 lg:px-10"
                >
                    <div class="flex max-w-2xl flex-col gap-4">
                        <p
                            class="text-sm font-semibold tracking-wide text-primary uppercase"
                        >
                            Made for real work
                        </p>
                        <h2
                            id="features-heading"
                            class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                        >
                            The structure you need, without the noise you don’t.
                        </h2>
                        <p class="text-lg leading-8 text-muted-foreground">
                            A dependable foundation that feels simple on day one
                            and remains useful as your work becomes more
                            ambitious.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <Card class="h-full">
                            <CardHeader>
                                <span
                                    class="flex size-11 items-center justify-center rounded-xl bg-secondary"
                                >
                                    <Zap class="size-5" />
                                </span>
                                <CardTitle>Simple workflows</CardTitle>
                                <CardDescription>
                                    Keep the next step obvious with clear
                                    ownership, focused views, and fewer
                                    handoffs.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                >
                                    Spend less time managing the process and
                                    more time doing the work that matters.
                                </p>
                            </CardContent>
                        </Card>

                        <Card class="h-full">
                            <CardHeader>
                                <span
                                    class="flex size-11 items-center justify-center rounded-xl bg-secondary"
                                >
                                    <Layers3 class="size-5" />
                                </span>
                                <CardTitle>Shared visibility</CardTitle>
                                <CardDescription>
                                    Give everyone the right context, from the
                                    broad direction down to the details.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                >
                                    Decisions, updates, and priorities stay
                                    connected and easy to find.
                                </p>
                            </CardContent>
                        </Card>

                        <Card class="h-full">
                            <CardHeader>
                                <span
                                    class="flex size-11 items-center justify-center rounded-xl bg-secondary"
                                >
                                    <ShieldCheck class="size-5" />
                                </span>
                                <CardTitle>Built to grow</CardTitle>
                                <CardDescription>
                                    Begin with the essentials and add structure
                                    only when your team is ready for it.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                >
                                    A flexible foundation keeps your workspace
                                    useful through every stage.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <section
                id="workflow"
                aria-labelledby="workflow-heading"
                class="scroll-mt-24"
            >
                <div
                    class="mx-auto grid max-w-7xl gap-14 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-[0.8fr_1.2fr] lg:px-10"
                >
                    <div
                        class="flex max-w-lg flex-col gap-5 lg:sticky lg:top-28 lg:self-start"
                    >
                        <p
                            class="text-sm font-semibold tracking-wide text-primary uppercase"
                        >
                            How it works
                        </p>
                        <h2
                            id="workflow-heading"
                            class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                        >
                            From scattered ideas to shared momentum.
                        </h2>
                        <p class="text-lg leading-8 text-muted-foreground">
                            A straightforward path that helps your team find its
                            rhythm without forcing everyone into a rigid
                            process.
                        </p>
                    </div>

                    <ol class="flex flex-col gap-4">
                        <li
                            v-for="step in workflowSteps"
                            :key="step.number"
                            class="grid gap-5 rounded-2xl border border-border/70 p-6 sm:grid-cols-[auto_1fr] sm:p-8"
                        >
                            <span
                                class="flex size-12 items-center justify-center rounded-full bg-primary font-mono text-sm font-semibold text-primary-foreground"
                            >
                                {{ step.number }}
                            </span>
                            <div class="flex flex-col gap-2">
                                <h3
                                    class="text-xl font-semibold tracking-tight"
                                >
                                    {{ step.title }}
                                </h3>
                                <p class="leading-7 text-muted-foreground">
                                    {{ step.description }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <section
                id="about"
                aria-labelledby="about-heading"
                class="scroll-mt-24 px-5 pb-20 sm:px-8 sm:pb-28 lg:px-10"
            >
                <div
                    class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-8 rounded-3xl bg-primary px-6 py-10 text-primary-foreground sm:px-10 sm:py-14 lg:flex-row lg:items-center lg:px-14"
                >
                    <div class="flex max-w-2xl flex-col gap-3">
                        <p
                            class="text-sm font-semibold tracking-wide uppercase opacity-70"
                        >
                            Your work, more focused
                        </p>
                        <h2
                            id="about-heading"
                            class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                        >
                            Make room for the work you want to do.
                        </h2>
                        <p class="text-base leading-7 opacity-75 sm:text-lg">
                            Start with a calm foundation, then shape it around
                            the way your team works best.
                        </p>
                    </div>

                    <Button as-child size="lg" variant="secondary">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            prefetch
                        >
                            Open dashboard
                            <ArrowRight data-icon="inline-end" />
                        </Link>
                        <Link v-else :href="login()" prefetch>
                            Log in
                            <ArrowRight data-icon="inline-end" />
                        </Link>
                    </Button>
                </div>
            </section>
        </main>

        <footer class="border-t border-border/70">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-10 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-10"
            >
                <Link
                    :href="home()"
                    class="inline-flex w-fit items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-label="`${$page.props.name} home`"
                >
                    <AppLogoFull class="h-7 w-auto max-w-36 text-foreground" />
                </Link>

                <nav
                    aria-label="Footer navigation"
                    class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-muted-foreground"
                >
                    <a
                        v-for="item in navigationItems"
                        :key="item.href"
                        :href="item.href"
                        class="transition-colors hover:text-foreground"
                    >
                        {{ item.label }}
                    </a>
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="transition-colors hover:text-foreground"
                    >
                        Log in
                    </Link>
                </nav>
            </div>
        </footer>
    </div>
</template>
