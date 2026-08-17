import { useHttp, usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import AppearanceController from '@/actions/App/Http/Controllers/Settings/AppearanceController';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const appearances: readonly Appearance[] = ['light', 'dark', 'system'];
const appearance = ref<Appearance>('system');
const systemPrefersDark = ref(false);
let activeAppearance: Appearance = 'system';
let listensForSystemChanges = false;

export function isAppearance(value: unknown): value is Appearance {
    return (
        typeof value === 'string' && appearances.includes(value as Appearance)
    );
}

function mediaQuery(): MediaQueryList | null {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
}

function handleSystemThemeChange(event: MediaQueryListEvent): void {
    systemPrefersDark.value = event.matches;

    if (activeAppearance === 'system') {
        updateTheme('system');
    }
}

function listenForSystemThemeChanges(): void {
    const query = mediaQuery();

    if (!query) {
        return;
    }

    systemPrefersDark.value = query.matches;

    if (!listensForSystemChanges) {
        query.addEventListener('change', handleSystemThemeChange);
        listensForSystemChanges = true;
    }
}

export function resolveAppearance(value: Appearance): ResolvedAppearance {
    if (value === 'system') {
        return systemPrefersDark.value ? 'dark' : 'light';
    }

    return value;
}

export function updateTheme(value: Appearance): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle(
        'dark',
        resolveAppearance(value) === 'dark',
    );
}

export function applyAppearance(
    value: Appearance,
    surface: 'dashboard' | 'frontend',
): void {
    activeAppearance = value;
    listenForSystemThemeChanges();

    if (typeof document !== 'undefined') {
        document.documentElement.dataset.appearance = value;
        document.documentElement.dataset.appearanceSurface = surface;
    }

    updateTheme(value);
}

export function useAppearance(): UseAppearanceReturn {
    const page = usePage();
    const request = useHttp<{ appearance: Appearance }>({
        appearance: 'system',
    });

    onMounted(() => {
        const userAppearance = page.props.auth.user?.appearance;
        const initialAppearance = isAppearance(userAppearance)
            ? userAppearance
            : 'system';

        appearance.value = initialAppearance;
        request.appearance = initialAppearance;
        applyAppearance(initialAppearance, 'dashboard');
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() =>
        resolveAppearance(appearance.value),
    );

    async function persistAppearance(
        value: Appearance,
        previousAppearance: Appearance,
    ): Promise<void> {
        request.appearance = value;

        try {
            await request.submit(AppearanceController.update());
        } catch {
            if (appearance.value === value) {
                appearance.value = previousAppearance;
                request.appearance = previousAppearance;
                applyAppearance(previousAppearance, 'dashboard');
            }
        }
    }

    function updateAppearance(value: Appearance): void {
        const previousAppearance = appearance.value;

        appearance.value = value;
        applyAppearance(value, 'dashboard');
        void persistAppearance(value, previousAppearance);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
