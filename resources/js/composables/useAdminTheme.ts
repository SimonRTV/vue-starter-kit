import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import type { AdminTheme } from '@/types';

export type { AdminTheme };

export type UseAdminThemeReturn = {
    adminTheme: Ref<AdminTheme>;
    updateAdminTheme: (value: AdminTheme) => void;
};

const defaultAdminTheme: AdminTheme = 'neutral';
const adminThemes: readonly AdminTheme[] = ['neutral', 'ocean', 'forest'];

export function isAdminTheme(value: unknown): value is AdminTheme {
    return (
        typeof value === 'string' && adminThemes.includes(value as AdminTheme)
    );
}

function applyAdminTheme(value: AdminTheme): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.adminTheme = value;
}

function getStoredAdminTheme(): AdminTheme {
    if (typeof window === 'undefined') {
        return defaultAdminTheme;
    }

    const storedTheme = localStorage.getItem('admin_theme');

    if (isAdminTheme(storedTheme)) {
        return storedTheme;
    }

    const documentTheme = document.documentElement.dataset.adminTheme;

    return isAdminTheme(documentTheme) ? documentTheme : defaultAdminTheme;
}

function setCookie(name: string, value: string, days = 365): void {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

export function initializeAdminTheme(): void {
    applyAdminTheme(getStoredAdminTheme());
}

const adminTheme = ref<AdminTheme>(defaultAdminTheme);

export function useAdminTheme(): UseAdminThemeReturn {
    onMounted(() => {
        adminTheme.value = getStoredAdminTheme();
    });

    function updateAdminTheme(value: AdminTheme): void {
        adminTheme.value = value;
        localStorage.setItem('admin_theme', value);
        setCookie('admin_theme', value);
        applyAdminTheme(value);
    }

    return {
        adminTheme,
        updateAdminTheme,
    };
}
