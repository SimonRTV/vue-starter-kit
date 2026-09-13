<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Layers3,
    ShieldCheck,
    Sparkles,
    Zap,
} from '@lucide/vue';
import FrontendFooter from '@/components/frontend/FrontendFooter.vue';
import FrontendHeader from '@/components/frontend/FrontendHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard, login } from '@/routes';

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
    <div class="bg-background text-foreground min-h-screen overflow-x-hidden">
        <FrontendHeader is-home-page />

        <main>
            <section class="relative isolate">
                <div
                    aria-hidden="true"
                    class="bg-primary/8 pointer-events-none absolute -top-24 left-1/2 size-96 -translate-x-1/2 rounded-full blur-3xl"
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
                                class="text-muted-foreground max-w-2xl text-lg leading-8 text-pretty sm:text-xl"
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
                            class="text-muted-foreground flex flex-col gap-3 text-sm sm:flex-row sm:gap-6"
                        >
                            <span class="inline-flex items-center gap-2">
                                <Check class="text-foreground size-4" />
                                Quick to understand
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <Check class="text-foreground size-4" />
                                Flexible as you grow
                            </span>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl lg:max-w-none">
                        <div
                            aria-hidden="true"
                            class="bg-muted/70 absolute -inset-5 rounded-[2rem] blur-2xl"
                        />
                        <div
                            class="animate-in border-border/70 bg-muted/35 shadow-foreground/5 fade-in slide-in-from-top-8 relative rounded-[1.75rem] border p-3 shadow-2xl delay-150 duration-1000 sm:p-5"
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
                                        <div class="bg-muted rounded-xl p-4">
                                            <p
                                                class="text-muted-foreground text-xs font-medium"
                                            >
                                                Progress
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold"
                                            >
                                                72%
                                            </p>
                                        </div>
                                        <div class="bg-muted rounded-xl p-4">
                                            <p
                                                class="text-muted-foreground text-xs font-medium"
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
                                            class="border-border/70 flex items-center justify-between gap-4 rounded-xl border p-3"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="bg-secondary flex size-9 items-center justify-center rounded-lg"
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
                                                        class="text-muted-foreground text-xs"
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
                                            class="border-border/70 flex items-center justify-between gap-4 rounded-xl border p-3"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="bg-secondary flex size-9 items-center justify-center rounded-lg"
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
                                                        class="text-muted-foreground text-xs"
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
                class="border-border/70 bg-muted/35 scroll-mt-24 border-y"
            >
                <div
                    class="mx-auto flex max-w-7xl flex-col gap-12 px-5 py-20 sm:px-8 sm:py-24 lg:px-10"
                >
                    <div class="flex max-w-2xl flex-col gap-4">
                        <p
                            class="text-primary text-sm font-semibold tracking-wide uppercase"
                        >
                            Made for real work
                        </p>
                        <h2
                            id="features-heading"
                            class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                        >
                            The structure you need, without the noise you don’t.
                        </h2>
                        <p class="text-muted-foreground text-lg leading-8">
                            A dependable foundation that feels simple on day one
                            and remains useful as your work becomes more
                            ambitious.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <Card id="simple-workflows" class="h-full scroll-mt-24">
                            <CardHeader>
                                <span
                                    class="bg-secondary flex size-11 items-center justify-center rounded-xl"
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
                                    class="text-muted-foreground text-sm leading-6"
                                >
                                    Spend less time managing the process and
                                    more time doing the work that matters.
                                </p>
                            </CardContent>
                        </Card>

                        <Card
                            id="shared-visibility"
                            class="h-full scroll-mt-24"
                        >
                            <CardHeader>
                                <span
                                    class="bg-secondary flex size-11 items-center justify-center rounded-xl"
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
                                    class="text-muted-foreground text-sm leading-6"
                                >
                                    Decisions, updates, and priorities stay
                                    connected and easy to find.
                                </p>
                            </CardContent>
                        </Card>

                        <Card id="built-to-grow" class="h-full scroll-mt-24">
                            <CardHeader>
                                <span
                                    class="bg-secondary flex size-11 items-center justify-center rounded-xl"
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
                                    class="text-muted-foreground text-sm leading-6"
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
                            class="text-primary text-sm font-semibold tracking-wide uppercase"
                        >
                            How it works
                        </p>
                        <h2
                            id="workflow-heading"
                            class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                        >
                            From scattered ideas to shared momentum.
                        </h2>
                        <p class="text-muted-foreground text-lg leading-8">
                            A straightforward path that helps your team find its
                            rhythm without forcing everyone into a rigid
                            process.
                        </p>
                    </div>

                    <ol class="flex flex-col gap-4">
                        <li
                            v-for="step in workflowSteps"
                            :key="step.number"
                            class="border-border/70 grid gap-5 rounded-2xl border p-6 sm:grid-cols-[auto_1fr] sm:p-8"
                        >
                            <span
                                class="bg-primary text-primary-foreground flex size-12 items-center justify-center rounded-full font-mono text-sm font-semibold"
                            >
                                {{ step.number }}
                            </span>
                            <div class="flex flex-col gap-2">
                                <h3
                                    class="text-xl font-semibold tracking-tight"
                                >
                                    {{ step.title }}
                                </h3>
                                <p class="text-muted-foreground leading-7">
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
                    class="bg-primary text-primary-foreground mx-auto flex max-w-7xl flex-col items-start justify-between gap-8 rounded-3xl px-6 py-10 sm:px-10 sm:py-14 lg:flex-row lg:items-center lg:px-14"
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

        <FrontendFooter is-home-page />
    </div>
</template>
