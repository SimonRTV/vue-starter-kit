<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoFull from '@/components/AppLogoFull.vue';
import { home, login } from '@/routes';

const props = withDefaults(
    defineProps<{
        isHomePage?: boolean;
    }>(),
    {
        isHomePage: false,
    },
);

const page = usePage();
const footerItems = computed(() =>
    page.props.navigation.frontend.flatMap((item) =>
        item.type === 'group'
            ? item.children.map((child) => ({
                  label: child.label,
                  url: child.url,
              }))
            : item.url
              ? [{ label: item.label, url: item.url }]
              : [],
    ),
);

function isExternal(url: string): boolean {
    return /^https?:\/\//i.test(url);
}

function isAnchor(url: string): boolean {
    return url.startsWith('#');
}

function resolvedUrl(url: string): string {
    if (isAnchor(url) && !props.isHomePage) {
        return `${home.url()}${url}`;
    }

    return url;
}
</script>

<template>
    <footer class="border-border/70 border-t">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-10 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-10"
        >
            <Link
                :href="home()"
                class="focus-visible:ring-ring inline-flex w-fit items-center rounded-md outline-none focus-visible:ring-2"
                :aria-label="`${page.props.name} home`"
            >
                <AppLogoFull class="text-foreground h-7 w-auto max-w-36" />
            </Link>

            <nav
                aria-label="Footer navigation"
                class="text-muted-foreground flex flex-wrap items-center gap-x-6 gap-y-3 text-sm"
            >
                <template
                    v-for="(item, index) in footerItems"
                    :key="`${item.url}-${index}`"
                >
                    <a
                        v-if="isExternal(item.url) || isAnchor(item.url)"
                        :href="resolvedUrl(item.url)"
                        :target="isExternal(item.url) ? '_blank' : undefined"
                        :rel="
                            isExternal(item.url)
                                ? 'noopener noreferrer'
                                : undefined
                        "
                        class="hover:text-foreground transition-colors"
                    >
                        {{ item.label }}
                    </a>
                    <Link
                        v-else
                        :href="resolvedUrl(item.url)"
                        class="hover:text-foreground transition-colors"
                    >
                        {{ item.label }}
                    </Link>
                </template>
                <Link
                    v-if="!page.props.auth.user"
                    :href="login()"
                    class="hover:text-foreground transition-colors"
                >
                    Log in
                </Link>
            </nav>
        </div>
    </footer>
</template>
