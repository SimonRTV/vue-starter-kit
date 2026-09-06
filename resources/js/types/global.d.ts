import type { Auth } from '@/types/auth';
import type {
    FrontendNavigationItem,
    SidebarFooterLink,
} from '@/types/navigation';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            notifications: { unreadCount: number };
            features: {
                pages: boolean;
                public_site: boolean;
                media: boolean;
                activity: boolean;
                notifications: boolean;
            };
            application: {
                name: string;
                tagline: string;
                contact_email: string;
                contact_phone: string;
            };
            branding: {
                iconUrl: string | null;
                fullLogoUrl: string | null;
                darkFullLogoUrl: string | null;
            };
            navigation: {
                frontend: FrontendNavigationItem[];
                resources: { title: string; url: string }[];
                sidebarFooterLinks: SidebarFooterLink[];
            };
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
