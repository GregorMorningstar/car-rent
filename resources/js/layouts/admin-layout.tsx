import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import AdminSidebar from '@/components/admin/layout/admin-sidebar';
import NotificationsMenu from '@/components/admin/layout/notifications-menu';
import useLiveUpdates from '@/hooks/use-live-updates';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import admin from '@/routes/admin';

export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth, name } = usePage().props;
    const [drawerOpen, setDrawerOpen] = useState(false);

    useLiveUpdates('admin.inbox');

    // Menu mobilne zamyka się po przejściu na inną stronę i klawiszem Esc.
    useEffect(() => router.on('navigate', () => setDrawerOpen(false)), []);

    useEffect(() => {
        if (!drawerOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && setDrawerOpen(false);
        document.addEventListener('keydown', onKeyDown);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = '';
        };
    }, [drawerOpen]);

    return (
        <div className="min-h-dvh bg-gray-100 text-gray-900">
            {/* Komputer: stałe menu boczne */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 lg:block">
                <AdminSidebar />
            </aside>

            {/* Telefon i tablet: wysuwane menu */}
            <div
                className={cn('fixed inset-0 z-40 lg:hidden', !drawerOpen && 'pointer-events-none')}
                aria-hidden={!drawerOpen}
            >
                <div
                    onClick={() => setDrawerOpen(false)}
                    className={cn(
                        'absolute inset-0 bg-gray-950/60 transition-opacity duration-300',
                        drawerOpen ? 'opacity-100' : 'opacity-0',
                    )}
                />
                <div
                    id="admin-drawer"
                    inert={!drawerOpen}
                    className={cn(
                        'absolute inset-y-0 left-0 w-72 max-w-[85vw] shadow-2xl transition-transform duration-300 motion-reduce:transition-none',
                        drawerOpen ? 'translate-x-0' : '-translate-x-full',
                    )}
                >
                    <AdminSidebar />
                </div>
            </div>

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <button
                        type="button"
                        onClick={() => setDrawerOpen(true)}
                        aria-expanded={drawerOpen}
                        aria-controls="admin-drawer"
                        aria-label="Otwórz menu"
                        className="-ml-2 flex size-11 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 lg:hidden"
                    >
                        <svg className="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} aria-hidden="true">
                            <path strokeLinecap="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <Link href={admin.dashboard()} className="mr-auto lg:hidden">
                        <img src="/images/logo.png" alt={name} width={330} height={66} className="h-6 w-auto" />
                    </Link>

                    <div className="ml-auto flex items-center gap-3 text-sm">
                        <NotificationsMenu />
                        <span className="hidden truncate text-gray-600 sm:block">{auth.user?.name}</span>
                        <Link
                            href={logout()}
                            as="button"
                            className="h-10 rounded-lg px-3.5 font-medium text-gray-700 ring-1 ring-gray-200 transition-colors hover:bg-gray-100"
                        >
                            Wyloguj
                        </Link>
                    </div>
                </header>

                <main className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">{children}</main>
            </div>
        </div>
    );
}
