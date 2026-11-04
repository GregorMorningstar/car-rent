import type { ReactNode } from 'react';
import AppHeader from '@/components/app-header';
import SiteFooter from '@/components/site-footer';

export default function SiteLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-dvh flex-col overflow-x-clip bg-white text-gray-900">
            <AppHeader />
            <main className="flex-1">{children}</main>
            <SiteFooter />
        </div>
    );
}
