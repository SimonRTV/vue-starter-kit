<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    ChevronRight,
    Clock3,
    Menu,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoFull from '@/components/AppLogoFull.vue';
import FrontendAppearanceSwitch from '@/components/FrontendAppearanceSwitch.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { dashboard, home, login } from '@/routes';
import type { PublicPage } from '@/types';

const props = defineProps<{
    page: PublicPage;
}>();

const navigationItems = [
    { label: 'Features', hash: '#features' },
    { label: 'How it works', hash: '#workflow' },
    { label: 'About', hash: '#about' },
] as const;

const paragraphs = computed(() =>
    (props.page.body ?? '')
        .split(/\n{2,}/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean),
);

function homepageSectionUrl(hash: string): string {
    return `${home.url()}${hash}`;
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'long',
    }).format(new Date(value));
}
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
                    <Link
                        :href="home()"
                        class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        Home
                    </Link>
                    <a
                        v-for="item in navigationItems"
                        :key="item.hash"
                        :href="homepageSectionUrl(item.hash)"
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

                    <Sheet>
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
                                <Link
                                    :href="home()"
                                    class="rounded-lg px-3 py-3 text-base font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    Home
                                </Link>
                                <a
                                    v-for="item in navigationItems"
                                    :key="item.hash"
                                    :href="homepageSectionUrl(item.hash)"
                                    class="rounded-lg px-3 py-3 text-base font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
            <section
                class="relative isolate border-b border-border/70 bg-muted/35"
            >
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -top-36 left-1/2 size-96 -translate-x-1/2 rounded-full bg-primary/8 blur-3xl"
                />

                <div
                    class="relative mx-auto flex max-w-5xl flex-col gap-8 px-5 py-16 sm:px-8 sm:py-24 lg:px-10"
                >
                    <nav
                        aria-label="Breadcrumb"
                        class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Link
                            :href="home()"
                            class="transition-colors hover:text-foreground"
                        >
                            Home
                        </Link>
                        <ChevronRight class="size-4" aria-hidden="true" />
                        <span class="truncate text-foreground">
                            {{ page.title }}
                        </span>
                    </nav>

                    <div class="flex max-w-4xl flex-col items-start gap-6">
                        <Badge variant="secondary">
                            <CalendarDays />
                            Published {{ formatDate(page.published_at) }}
                        </Badge>

                        <div class="flex flex-col gap-5">
                            <h1
                                class="text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-6xl"
                            >
                                {{ page.title }}
                            </h1>
                            <p
                                v-if="page.excerpt"
                                class="max-w-3xl text-lg leading-8 text-pretty text-muted-foreground sm:text-xl"
                            >
                                {{ page.excerpt }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="px-5 py-16 sm:px-8 sm:py-24 lg:px-10">
                <article class="mx-auto flex max-w-3xl flex-col gap-8">
                    <div
                        v-if="paragraphs.length"
                        class="flex flex-col gap-6 text-base leading-8 break-words text-foreground/90 sm:text-lg sm:leading-9"
                    >
                        <p
                            v-for="(paragraph, index) in paragraphs"
                            :key="index"
                            class="whitespace-pre-line"
                        >
                            {{ paragraph }}
                        </p>
                    </div>
                    <p v-else class="text-lg leading-8 text-muted-foreground">
                        No additional content is available for this page yet.
                    </p>

                    <Separator />

                    <div
                        class="flex flex-col items-start justify-between gap-5 sm:flex-row sm:items-center"
                    >
                        <div
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Clock3 class="size-4" aria-hidden="true" />
                            Last updated
                            {{
                                formatDate(page.updated_at ?? page.published_at)
                            }}
                        </div>

                        <Button as-child variant="outline">
                            <Link :href="home()">
                                <ArrowLeft data-icon="inline-start" />
                                Back to home
                            </Link>
                        </Button>
                    </div>
                </article>
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
                    <Link
                        :href="home()"
                        class="transition-colors hover:text-foreground"
                    >
                        Home
                    </Link>
                    <a
                        v-for="item in navigationItems"
                        :key="item.hash"
                        :href="homepageSectionUrl(item.hash)"
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
