<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Eye } from '@lucide/vue';
import { computed } from 'vue';
import { edit, show } from '@/actions/App/Http/Controllers/PageController';
import { Button } from '@/components/ui/button';

const page = usePage<{
    preview?: { id: number; canUpdate: boolean; isPublished: boolean } | null;
}>();
const preview = computed(() => page.props.preview);
</script>

<template>
    <aside v-if="preview" class="bg-muted border-b" aria-label="Aperçu privé">
        <div
            class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8"
        >
            <div class="flex items-center gap-3">
                <Eye class="size-5 shrink-0" />
                <div>
                    <p class="font-semibold">
                        Aperçu privé — version enregistrée
                    </p>
                    <p class="text-muted-foreground text-sm">
                        {{
                            preview.isPublished
                                ? 'Les modifications restent privées jusqu’à leur publication. La version en ligne reste disponible.'
                                : 'Cette page n’est pas publiée. Seules les personnes autorisées peuvent voir cet aperçu.'
                        }}
                    </p>
                </div>
            </div>
            <Button as-child variant="outline">
                <Link
                    :href="
                        preview.canUpdate ? edit(preview.id) : show(preview.id)
                    "
                >
                    <ArrowLeft class="size-4" />
                    {{
                        preview.canUpdate
                            ? 'Retour à l’éditeur'
                            : 'Retour à la page'
                    }}
                </Link>
            </Button>
        </div>
    </aside>
</template>
