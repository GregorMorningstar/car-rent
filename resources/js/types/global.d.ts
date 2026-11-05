import type { Auth } from '@/types/auth';
import type { Notifications } from '@/types/notifications';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash: string | null;
            notifications: Notifications | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
