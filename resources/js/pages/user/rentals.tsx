import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/ui/page-header';
import RentalHistory, { rentalStates } from '@/components/user/rental-history';
import type { RentalState, UserRental } from '@/components/user/rental-history';
import UserLayout from '@/layouts/user-layout';
import { cn } from '@/lib/utils';
import userRoutes from '@/routes/user';

const tabs: { value: RentalState | null; label: string }[] = [
    { value: null, label: 'Wszystkie' },
    ...(Object.keys(rentalStates) as RentalState[]).map((value) => ({ value, label: rentalStates[value].label })),
];

export default function UserRentals({
    rentals,
    counts,
    status,
    failed,
}: {
    rentals: UserRental[];
    counts: Record<RentalState, number>;
    status: RentalState | null;
    failed: boolean;
}) {
    const { flash } = usePage().props;
    const total = Object.values(counts).reduce((sum, count) => sum + count, 0);

    return (
        <>
            <Head title="Historia wynajmu" />

            <PageHeader title="Historia wynajmu" description="Wszystkie Twoje wynajmy i rezerwacje w jednym miejscu." />

            {flash && (
                <p role="status" className="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {flash}
                </p>
            )}

            <nav aria-label="Filtruj wynajmy" className="scrollbar-none -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
                {tabs.map((tab) => (
                    <Link
                        key={tab.label}
                        href={userRoutes.rentals(tab.value ? { query: { status: tab.value } } : undefined)}
                        preserveScroll
                        aria-current={status === tab.value ? 'page' : undefined}
                        className={cn(
                            'flex h-10 shrink-0 items-center gap-2 rounded-lg px-3.5 text-sm font-medium transition-colors',
                            status === tab.value ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100',
                        )}
                    >
                        {tab.label}
                        <span
                            className={cn(
                                'rounded-full px-1.5 text-xs tabular-nums',
                                status === tab.value ? 'bg-white/20' : 'bg-gray-100 text-gray-500',
                            )}
                        >
                            {tab.value ? counts[tab.value] : total}
                        </span>
                    </Link>
                ))}
            </nav>

            <RentalHistory rentals={rentals} failed={failed} emptyText="Brak wynajmów w tej kategorii." />
        </>
    );
}

UserRentals.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
