import { useHttp, usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import AppearanceController from '@/actions/App/Http/Controllers/Settings/AppearanceController';
import type { AdminTheme } from '@/types';

export type { AdminTheme };

export type UseAdminThemeReturn = {
    adminTheme: Ref<AdminTheme>;
    updateAdminTheme: (value: AdminTheme) => void;
};

const defaultAdminTheme: AdminTheme = 'neutral';
const adminThemes: readonly AdminTheme[] = ['neutral', 'ocean', 'forest'];
const adminTheme = ref<AdminTheme>(defaultAdminTheme);

export function isAdminTheme(value: unknown): value is AdminTheme {
    return (
        typeof value === 'string' && adminThemes.includes(value as AdminTheme)
    );
}

export function applyAdminTheme(value: AdminTheme): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.adminTheme = value;
}

export function useAdminTheme(): UseAdminThemeReturn {
    const page = usePage();
    const request = useHttp<{ admin_theme: AdminTheme }>({
        admin_theme: defaultAdminTheme,
    });

    onMounted(() => {
        const userAdminTheme = page.props.auth.user?.admin_theme;
        const initialAdminTheme = isAdminTheme(userAdminTheme)
            ? userAdminTheme
            : defaultAdminTheme;

        adminTheme.value = initialAdminTheme;
        request.admin_theme = initialAdminTheme;
        applyAdminTheme(initialAdminTheme);
    });

    async function persistAdminTheme(
        value: AdminTheme,
        previousAdminTheme: AdminTheme,
    ): Promise<void> {
        request.admin_theme = value;

        try {
            await request.submit(AppearanceController.update());
        } catch {
            if (adminTheme.value === value) {
                adminTheme.value = previousAdminTheme;
                request.admin_theme = previousAdminTheme;
                applyAdminTheme(previousAdminTheme);
            }
        }
    }

    function updateAdminTheme(value: AdminTheme): void {
        const previousAdminTheme = adminTheme.value;

        adminTheme.value = value;
        applyAdminTheme(value);
        void persistAdminTheme(value, previousAdminTheme);
    }

    return {
        adminTheme,
        updateAdminTheme,
    };
}
