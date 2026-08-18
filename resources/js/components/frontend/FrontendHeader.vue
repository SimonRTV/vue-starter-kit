<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, ChevronDown, Menu } from '@lucide/vue';
import { ref } from 'vue';
import AppLogoFull from '@/components/AppLogoFull.vue';
import FrontendNavigationChildContent from '@/components/frontend/FrontendNavigationChildContent.vue';
import FrontendAppearanceSwitch from '@/components/FrontendAppearanceSwitch.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { dashboard, home, login } from '@/routes';

const props = withDefaults(
    defineProps<{
        isHomePage?: boolean;
    }>(),
    {
        isHomePage: false,
    },
);

const page = usePage();
const isMobileMenuOpen = ref(false);

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

function handleAnchorClick(event: MouseEvent, url: string): void {
    isMobileMenuOpen.value = false;

    if (!props.isHomePage || !isAnchor(url)) {
        return;
    }

    event.preventDefault();

    window.setTimeout(() => {
        document.querySelector<HTMLElement>(url)?.scrollIntoView({
            block: 'start',
        });
        window.history.replaceState(null, '', url);
    }, 350);
}
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-border/70 bg-background/85 backdrop-blur-xl"
    >
        <div
            class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8 lg:px-10"
        >
            <Link
                :href="home()"
                class="inline-flex min-w-0 items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                :aria-label="`${page.props.name} home`"
            >
                <AppLogoFull class="h-8 w-auto max-w-40 text-foreground" />
            </Link>

            <nav
                v-if="page.props.navigation.frontend.length > 0"
                aria-label="Primary navigation"
                class="hidden items-center lg:flex"
            >
                <NavigationMenu :viewport="false">
                    <NavigationMenuList class="gap-2">
                        <NavigationMenuItem
                            v-for="(item, index) in page.props.navigation
                                .frontend"
                            :key="`${item.type}-${item.label}-${index}`"
                            :data-test="
                                item.type === 'group'
                                    ? 'desktop-navigation-group'
                                    : 'desktop-navigation-link'
                            "
                        >
                            <template v-if="item.type === 'group'">
                                <NavigationMenuTrigger>
                                    {{ item.label }}
                                </NavigationMenuTrigger>
                                <NavigationMenuContent
                                    class="w-[min(28rem,calc(100vw-2rem))] p-0 md:w-[28rem]"
                                >
                                    <div class="flex flex-col gap-1 p-3">
                                        <NavigationMenuLink
                                            v-for="(
                                                child, childIndex
                                            ) in item.children"
                                            :key="`${child.url}-${childIndex}`"
                                            as-child
                                        >
                                            <a
                                                v-if="
                                                    isExternal(child.url) ||
                                                    isAnchor(child.url)
                                                "
                                                :href="resolvedUrl(child.url)"
                                                :target="
                                                    isExternal(child.url)
                                                        ? '_blank'
                                                        : undefined
                                                "
                                                :rel="
                                                    isExternal(child.url)
                                                        ? 'noopener noreferrer'
                                                        : undefined
                                                "
                                                class="group block"
                                            >
                                                <FrontendNavigationChildContent
                                                    :label="child.label"
                                                    :description="
                                                        child.description
                                                    "
                                                />
                                            </a>
                                            <Link
                                                v-else
                                                :href="resolvedUrl(child.url)"
                                                class="group block"
                                            >
                                                <FrontendNavigationChildContent
                                                    :label="child.label"
                                                    :description="
                                                        child.description
                                                    "
                                                />
                                            </Link>
                                        </NavigationMenuLink>
                                    </div>
                                </NavigationMenuContent>
                            </template>

                            <NavigationMenuLink
                                v-else-if="item.url"
                                as-child
                                :class="navigationMenuTriggerStyle()"
                            >
                                <a
                                    v-if="
                                        isExternal(item.url) ||
                                        isAnchor(item.url)
                                    "
                                    :href="resolvedUrl(item.url)"
                                    :target="
                                        isExternal(item.url)
                                            ? '_blank'
                                            : undefined
                                    "
                                    :rel="
                                        isExternal(item.url)
                                            ? 'noopener noreferrer'
                                            : undefined
                                    "
                                >
                                    {{ item.label }}
                                </a>
                                <Link v-else :href="resolvedUrl(item.url)">
                                    {{ item.label }}
                                </Link>
                            </NavigationMenuLink>
                        </NavigationMenuItem>
                    </NavigationMenuList>
                </NavigationMenu>
            </nav>

            <div class="flex items-center gap-2">
                <FrontendAppearanceSwitch />
                <Button
                    v-if="page.props.auth.user"
                    as-child
                    class="hidden lg:inline-flex"
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
                    class="hidden lg:inline-flex"
                >
                    <Link :href="login()" prefetch>Log in</Link>
                </Button>

                <Sheet v-model:open="isMobileMenuOpen">
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="lg:hidden"
                            aria-label="Open navigation menu"
                        >
                            <Menu />
                        </Button>
                    </SheetTrigger>
                    <SheetContent class="w-full gap-0 p-0 sm:max-w-sm">
                        <SheetHeader class="border-b px-6 py-5 pr-14 text-left">
                            <SheetTitle class="sr-only">Menu</SheetTitle>
                            <SheetDescription class="sr-only">
                                Explore the site or access your workspace.
                            </SheetDescription>
                            <Link
                                :href="home()"
                                class="inline-flex w-fit items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :aria-label="`${page.props.name} home`"
                                @click="isMobileMenuOpen = false"
                            >
                                <AppLogoFull
                                    class="h-8 w-auto max-w-40 text-foreground"
                                />
                            </Link>
                        </SheetHeader>

                        <nav
                            v-if="page.props.navigation.frontend.length > 0"
                            aria-label="Mobile navigation"
                            class="flex flex-1 flex-col gap-2 overflow-y-auto px-4 py-6"
                        >
                            <template
                                v-for="(item, index) in page.props.navigation
                                    .frontend"
                                :key="`${item.type}-${item.label}-${index}`"
                            >
                                <Collapsible
                                    v-if="item.type === 'group'"
                                    class="group/collapsible"
                                    data-test="mobile-navigation-group"
                                >
                                    <CollapsibleTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            class="w-full justify-between px-3 text-base"
                                        >
                                            {{ item.label }}
                                            <ChevronDown
                                                data-icon="inline-end"
                                                class="transition-transform group-data-[state=open]/collapsible:rotate-180"
                                            />
                                        </Button>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <div
                                            class="flex flex-col gap-1 pt-2 pl-3"
                                        >
                                            <template
                                                v-for="(
                                                    child, childIndex
                                                ) in item.children"
                                                :key="`${child.url}-${childIndex}`"
                                            >
                                                <a
                                                    v-if="
                                                        isExternal(child.url) ||
                                                        isAnchor(child.url)
                                                    "
                                                    :href="
                                                        resolvedUrl(child.url)
                                                    "
                                                    :target="
                                                        isExternal(child.url)
                                                            ? '_blank'
                                                            : undefined
                                                    "
                                                    :rel="
                                                        isExternal(child.url)
                                                            ? 'noopener noreferrer'
                                                            : undefined
                                                    "
                                                    class="group block rounded-lg transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    @click="
                                                        handleAnchorClick(
                                                            $event,
                                                            child.url,
                                                        )
                                                    "
                                                >
                                                    <FrontendNavigationChildContent
                                                        compact
                                                        :label="child.label"
                                                        :description="
                                                            child.description
                                                        "
                                                    />
                                                </a>
                                                <Link
                                                    v-else
                                                    :href="
                                                        resolvedUrl(child.url)
                                                    "
                                                    class="group block rounded-lg transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    @click="
                                                        isMobileMenuOpen = false
                                                    "
                                                >
                                                    <FrontendNavigationChildContent
                                                        compact
                                                        :label="child.label"
                                                        :description="
                                                            child.description
                                                        "
                                                    />
                                                </Link>
                                            </template>
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>

                                <a
                                    v-else-if="
                                        item.url &&
                                        (isExternal(item.url) ||
                                            isAnchor(item.url))
                                    "
                                    :href="resolvedUrl(item.url)"
                                    :target="
                                        isExternal(item.url)
                                            ? '_blank'
                                            : undefined
                                    "
                                    :rel="
                                        isExternal(item.url)
                                            ? 'noopener noreferrer'
                                            : undefined
                                    "
                                    data-test="mobile-navigation-link"
                                    class="rounded-lg px-3 py-2 text-base font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @click="handleAnchorClick($event, item.url)"
                                >
                                    {{ item.label }}
                                </a>
                                <Link
                                    v-else-if="item.url"
                                    :href="resolvedUrl(item.url)"
                                    data-test="mobile-navigation-link"
                                    class="rounded-lg px-3 py-2 text-base font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @click="isMobileMenuOpen = false"
                                >
                                    {{ item.label }}
                                </Link>
                            </template>
                        </nav>

                        <div class="p-4">
                            <Button
                                v-if="page.props.auth.user"
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
</template>
