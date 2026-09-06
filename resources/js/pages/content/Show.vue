<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, ChevronRight, Clock3 } from '@lucide/vue';
import FrontendFooter from '@/components/frontend/FrontendFooter.vue';
import FrontendHeader from '@/components/frontend/FrontendHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { home } from '@/routes';
import type { PublicPage } from '@/types';

defineProps<{
    page: PublicPage;
}>();

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'long',
    }).format(new Date(value));
}
</script>

<template>
    <div class="min-h-screen overflow-x-hidden bg-background text-foreground">
        <FrontendHeader />

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
                        v-if="page.body_html"
                        class="rich-content text-lg"
                        v-html="page.body_html"
                    />
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
                    <section
                        v-if="page.attachments.length"
                        class="space-y-4"
                        aria-label="Pièces jointes"
                    >
                        <h2 class="text-xl font-semibold">Pièces jointes</h2>
                        <ul class="space-y-3">
                            <li v-for="file in page.attachments" :key="file.id">
                                <a
                                    :href="file.download_url"
                                    class="flex items-center gap-3 rounded-lg border p-4 hover:bg-muted"
                                    ><img
                                        v-if="file.thumbnail_url"
                                        :src="file.thumbnail_url"
                                        :alt="file.alt_text ?? ''"
                                        class="size-16 rounded object-contain"
                                    /><span
                                        class="font-medium underline underline-offset-4"
                                        >{{ file.title }}</span
                                    ></a
                                >
                            </li>
                        </ul>
                    </section>
                </article>
            </section>
        </main>

        <FrontendFooter />
    </div>
</template>
