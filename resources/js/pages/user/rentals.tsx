import { Head } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import type { RentalStatus } from '@/components/user/data';
import { rentals, rentalStatuses } from '@/components/user/data';
import PageHeader from '@/components/ui/page-header';
import RentalsList from '@/components/user/rentals-list';
import UserLayout from '@/layouts/user-layout';
import { cn } from '@/lib/utils';

type Filter = RentalStatus | 'all';

const filters: { value: Filter; label: string }[] = [
    { value: 'all', label: 'Wszystkie' },
    ...(Object.keys(rentalStatuses) as RentalStatus[]).map((status) => ({
        value: status,
        label: rentalStatuses[status].label,
    })),
];

export default function UserRentals() {
    const [filter, setFilter] = useState<Filter>('all');

    const count = (value: Filter) =>
        value === 'all'
            ? rentals.length
            : rentals.filter((rental) => rental.status === value).length;

    const visible =
        filter === 'all'
            ? rentals
            : rentals.filter((rental) => rental.status === filter);

    return (
        <>
            <Head title="Historia wynajmu" />

            <PageHeader
                title="Historia wynajmu"
                description="Wszystkie Twoje wynajmy i rezerwacje w jednym miejscu."
            />

            <div
                role="tablist"
                aria-label="Filtruj wynajmy"
                className="scrollbar-none -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0"
            >
                {filters.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={filter === item.value}
                        onClick={() => setFilter(item.value)}
                        className={cn(
                            'flex h-10 shrink-0 items-center gap-2 rounded-lg px-3.5 text-sm font-medium transition-colors',
                            filter === item.value
                                ? 'bg-gray-900 text-white'
                                : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100',
                        )}
                    >
                        {item.label}
                        <span
                            className={cn(
                                'rounded-full px-1.5 text-xs tabular-nums',
                                filter === item.value
                                    ? 'bg-white/20'
                                    : 'bg-gray-100 text-gray-500',
                            )}
                        >
                            {count(item.value)}
                        </span>
                    </button>
                ))}
            </div>

            <RentalsList rentals={visible} />
        </>
    );
}

UserRentals.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
