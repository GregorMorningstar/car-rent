import type { ReactNode } from 'react';
import AppHeader from '@/components/app-header';
import EmailVerificationBanner from '@/components/email-verification-banner';
import UserSidebar from '@/components/user/user-sidebar';

export default function UserLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-dvh flex-col overflow-x-clip bg-gray-50 text-gray-900">
            <AppHeader />
            <EmailVerificationBanner />

            <div className="gutter flex-1 py-6 lg:grid lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start lg:gap-8 lg:py-10">
                <UserSidebar />
                <main className="min-w-0">{children}</main>
            </div>
        </div>
    );
}
