<script setup lang="ts">
import { Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    CalendarDays,
    Clock3,
    Download,
    Edit3,
    ExternalLink,
    FileText,
    Info,
    Maximize2,
    Minimize2,
    Paperclip,
} from '@lucide/vue';
import { useEventListener } from '@vueuse/core';
import { computed, ref, useTemplateRef, watch } from 'vue';
import {
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/PageController';
import DeletePageButton from '@/components/pages/DeletePageButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { show as showPublicPage } from '@/routes/content';
import type { PageDetail } from '@/types';
import type { MediaItem } from '@/types/media';

const shared = usePage();
const props = defineProps<{
    page: PageDetail;
    attachments: MediaItem[];
    canUpdate: boolean;
    canDelete: boolean;
}>();
const readingMode = ref(false);
const detailsOpen = ref(false);
const contentElement = useTemplateRef<HTMLDivElement>('contentElement');
const wordCount = ref(0);
const readingMinutes = computed(() =>
    Math.max(1, Math.ceil(wordCount.value / 200)),
);
const publicPageAvailable = computed(
    () => props.page.is_published && shared.props.features.public_site,
);

watch(
    [contentElement, () => props.page.body_html],
    () => {
        const text = contentElement.value?.innerText.trim() ?? '';
        wordCount.value = text ? text.split(/\s+/).length : 0;
    },
    { flush: 'post' },
);

useEventListener('keydown', (event: KeyboardEvent) => {
    if (
        event.key === 'Escape' &&
        !event.defaultPrevented &&
        !detailsOpen.value
    ) {
        readingMode.value = false;
    }
});

function formatDate(value: string | null, includeTime = false): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-CH', {
        dateStyle: 'long',
        ...(includeTime ? { timeStyle: 'short' } : {}),
    }).format(new Date(value));
}

function formatSize(bytes: number): string {
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} Mo`
        : `${Math.ceil(bytes / 1024)} Ko`;
}

setLayoutProps({
    breadcrumbs: [
        { title: 'Pages', href: index() },
        { title: props.page.title, href: show(props.page.id) },
    ],
});
</script>

<template>
    <div
        class="flex min-w-0 flex-1 flex-col"
        :class="
            readingMode
                ? 'bg-background fixed inset-0 z-40 overflow-y-auto overscroll-contain'
                : ''
        "
    >
        <header
            class="bg-background/95 sticky top-0 z-20 border-b backdrop-blur"
        >
            <div
                class="mx-auto flex w-full max-w-[1600px] flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-3">
                    <Button variant="outline" size="icon" as-child
                        ><Link :href="index()" aria-label="Retour aux pages"
                            ><ArrowLeft class="size-4" /></Link
                    ></Button>
                    <div>
                        <p class="text-sm font-semibold">Lecture de la page</p>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {{
                                readingMode
                                    ? 'Mode lecture · Échap pour quitter'
                                    : 'Contenu et pièces jointes'
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        :aria-pressed="readingMode"
                        :aria-label="
                            readingMode
                                ? 'Quitter le mode lecture'
                                : 'Mode lecture'
                        "
                        :title="
                            readingMode
                                ? 'Quitter le mode lecture'
                                : 'Mode lecture'
                        "
                        @click="readingMode = !readingMode"
                        ><Minimize2
                            v-if="readingMode"
                            class="size-4" /><Maximize2 v-else class="size-4"
                    /></Button>
                    <Sheet v-model:open="detailsOpen">
                        <SheetTrigger as-child
                            ><Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Détails de la page"
                                title="Détails de la page"
                                ><Info class="size-4" /></Button
                        ></SheetTrigger>
                        <SheetContent
                            class="w-full overflow-y-auto sm:max-w-md"
                        >
                            <SheetHeader class="border-b p-6"
                                ><SheetTitle>Détails de la page</SheetTitle
                                ><SheetDescription
                                    >Publication et informations de la
                                    page.</SheetDescription
                                ></SheetHeader
                            >
                            <dl class="space-y-6 p-6 text-sm">
                                <div class="space-y-2">
                                    <dt class="text-muted-foreground">
                                        Statut
                                    </dt>
                                    <dd>
                                        <Badge
                                            :variant="
                                                page.is_published
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                            >{{
                                                page.is_published
                                                    ? 'Publiée'
                                                    : 'Brouillon'
                                            }}</Badge
                                        >
                                    </dd>
                                </div>
                                <div class="space-y-2">
                                    <dt class="text-muted-foreground">
                                        Adresse de la page
                                    </dt>
                                    <dd class="break-all">
                                        <a
                                            v-if="publicPageAvailable"
                                            :href="
                                                showPublicPage.url(page.slug)
                                            "
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-primary underline underline-offset-4"
                                            >{{
                                                showPublicPage.url(page.slug)
                                            }}</a
                                        ><span v-else>{{
                                            showPublicPage.url(page.slug)
                                        }}</span>
                                    </dd>
                                </div>
                                <div class="space-y-2">
                                    <dt class="text-muted-foreground">
                                        Publication
                                    </dt>
                                    <dd>
                                        {{
                                            page.published_at
                                                ? formatDate(
                                                      page.published_at,
                                                      true,
                                                  )
                                                : 'Non publiée'
                                        }}
                                    </dd>
                                </div>
                                <div class="space-y-2">
                                    <dt class="text-muted-foreground">
                                        Dernière modification
                                    </dt>
                                    <dd>
                                        {{ formatDate(page.updated_at, true) }}
                                    </dd>
                                </div>
                                <div class="space-y-2">
                                    <dt class="text-muted-foreground">
                                        Création
                                    </dt>
                                    <dd>
                                        {{ formatDate(page.created_at, true) }}
                                    </dd>
                                </div>
                                <div class="space-y-2">
                                    <dt class="text-muted-foreground">
                                        Contenu
                                    </dt>
                                    <dd>
                                        {{ wordCount }}
                                        {{ wordCount === 1 ? 'mot' : 'mots'
                                        }}<template v-if="attachments.length">
                                            · {{ attachments.length }}
                                            {{
                                                attachments.length === 1
                                                    ? 'pièce jointe'
                                                    : 'pièces jointes'
                                            }}</template
                                        >
                                    </dd>
                                </div>
                            </dl>
                            <SheetFooter v-if="canDelete" class="border-t p-6"
                                ><DeletePageButton :page="page"
                            /></SheetFooter>
                        </SheetContent>
                    </Sheet>
                    <Button
                        v-if="publicPageAvailable"
                        variant="outline"
                        as-child
                        class="hidden sm:inline-flex"
                        ><a
                            :href="showPublicPage.url(page.slug)"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><ExternalLink class="size-4" />Voir en ligne</a
                        ></Button
                    >
                    <Button v-if="canUpdate" as-child
                        ><Link :href="edit(page.id)"
                            ><Edit3 class="size-4" />Modifier</Link
                        ></Button
                    >
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
            <article
                class="bg-background rounded-2xl border px-6 py-8 shadow-xs sm:px-12 sm:py-12 lg:px-16 lg:py-14"
                aria-labelledby="page-title"
            >
                <div class="mx-auto max-w-[68ch]">
                    <header class="space-y-6 border-b pb-8 sm:pb-10">
                        <div
                            class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-2 text-xs"
                        >
                            <Badge variant="secondary" class="gap-1.5"
                                ><span
                                    class="size-1.5 rounded-full"
                                    :class="
                                        page.is_published
                                            ? 'bg-emerald-500'
                                            : 'bg-amber-500'
                                    "
                                />{{
                                    page.is_published ? 'Publiée' : 'Brouillon'
                                }}</Badge
                            >
                            <span
                                v-if="page.published_at"
                                class="flex items-center gap-1.5"
                                ><CalendarDays class="size-3.5" /><time
                                    :datetime="page.published_at"
                                    >{{ formatDate(page.published_at) }}</time
                                ></span
                            >
                            <span
                                v-if="wordCount"
                                class="flex items-center gap-1.5"
                                ><Clock3 class="size-3.5" />{{
                                    readingMinutes
                                }}
                                min de lecture</span
                            >
                        </div>
                        <h1
                            id="page-title"
                            class="text-3xl leading-[1.12] font-semibold tracking-tight text-balance break-words sm:text-4xl lg:text-5xl"
                        >
                            {{ page.title }}
                        </h1>
                        <p
                            v-if="page.excerpt"
                            class="text-muted-foreground text-lg leading-relaxed text-pretty break-words sm:text-xl"
                        >
                            {{ page.excerpt }}
                        </p>
                        <p
                            v-if="!page.is_published"
                            class="text-muted-foreground flex items-start gap-2 text-sm"
                        >
                            <Info class="mt-0.5 size-4 shrink-0" />Ce brouillon
                            est visible uniquement dans l’espace de gestion.
                        </p>
                    </header>

                    <div
                        v-if="page.body_html"
                        ref="contentElement"
                        class="rich-content pt-8 text-lg leading-[1.85] sm:pt-10"
                        v-html="page.body_html"
                    />
                    <div v-else class="flex flex-col items-start gap-3 py-10">
                        <BookOpen class="text-muted-foreground size-7" />
                        <h2 class="text-lg font-medium">
                            Cette page attend son contenu
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            {{
                                canUpdate
                                    ? 'Ouvrez l’éditeur pour commencer la rédaction.'
                                    : 'Aucun contenu n’a encore été ajouté à cette page.'
                            }}
                        </p>
                        <Button v-if="canUpdate" variant="outline" as-child
                            ><Link :href="edit(page.id)"
                                ><Edit3 class="size-4" />Rédiger la page</Link
                            ></Button
                        >
                    </div>

                    <section
                        v-if="attachments.length"
                        class="mt-10 space-y-4 border-t pt-8"
                        aria-labelledby="attachments-title"
                    >
                        <h2
                            id="attachments-title"
                            class="flex items-center gap-2 text-base font-semibold"
                        >
                            <Paperclip
                                class="text-muted-foreground size-4"
                            />Pièces jointes
                            <span class="text-muted-foreground font-normal"
                                >({{ attachments.length }})</span
                            >
                        </h2>
                        <ul class="space-y-3">
                            <li v-for="file in attachments" :key="file.id">
                                <a
                                    :href="file.download_url"
                                    class="hover:bg-muted/50 focus-visible:ring-ring flex items-center gap-3 rounded-xl border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <img
                                        v-if="file.thumbnail_url"
                                        :src="file.thumbnail_url"
                                        :alt="file.alt_text ?? ''"
                                        class="size-12 shrink-0 rounded-lg object-cover"
                                        loading="lazy"
                                    />
                                    <span
                                        v-else
                                        class="bg-muted text-muted-foreground flex size-12 shrink-0 items-center justify-center rounded-lg"
                                        ><FileText class="size-5"
                                    /></span>
                                    <span class="min-w-0 flex-1"
                                        ><span
                                            class="block text-sm font-medium break-words"
                                            >{{ file.title }}</span
                                        ><span
                                            class="text-muted-foreground mt-1 block text-xs"
                                            >{{ formatSize(file.size) }} ·
                                            {{
                                                file.visibility === 'public'
                                                    ? 'Public'
                                                    : 'Privé'
                                            }}</span
                                        ></span
                                    >
                                    <Download
                                        class="text-muted-foreground size-4 shrink-0"
                                    /><span class="sr-only">Télécharger</span>
                                </a>
                            </li>
                        </ul>
                    </section>
                    <footer
                        v-if="page.updated_at"
                        class="text-muted-foreground mt-10 border-t pt-5 text-xs"
                    >
                        Mis à jour le
                        <time :datetime="page.updated_at">{{
                            formatDate(page.updated_at)
                        }}</time>
                    </footer>
                </div>
            </article>
        </main>
    </div>
</template>
