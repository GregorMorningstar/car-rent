import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import type { Rental } from '@/components/user/data';
import {
    formatDate,
    formatMoney,
    payments,
    rentalDays,
    rentals,
    rentalStatuses,
} from '@/components/user/data';
import PageHeader from '@/components/ui/page-header';
import RentalsList from '@/components/user/rentals-list';
import StatusBadge from '@/components/ui/status-badge';
import UserLayout from '@/layouts/user-layout';
import { home } from '@/routes';
import user from '@/routes/user';

function StatTile({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="mt-1 text-2xl font-bold text-gray-900 tabular-nums sm:text-3xl">
                {value}
            </dd>
        </div>
    );
}

function RentalHighlight({ title, rental }: { title: string; rental?: Rental }) {
    return (
        <section className="flex flex-col rounded-2xl border border-gray-200 bg-white p-5">
            <h2 className="text-sm font-semibold text-gray-500">{title}</h2>

            {rental ? (
                <>
                    <div className="mt-3 flex items-start justify-between gap-3">
                        <p className="text-lg font-semibold text-gray-900">
                            {rental.car}
                        </p>
                        <StatusBadge {...rentalStatuses[rental.status]} />
                    </div>
                    <p className="mt-1 text-sm text-gray-600">
                        {formatDate(rental.from)} – {formatDate(rental.to)} ·{' '}
                        {rentalDays(rental)} dni
                    </p>
                    <p className="mt-3 text-sm text-gray-500">
                        Odbiór:{' '}
                        <span className="text-gray-900">{rental.pickup}</span>
                    </p>
                    <p className="text-sm text-gray-500">
                        Zwrot:{' '}
                        <span className="text-gray-900">{rental.dropoff}</span>
                    </p>
                </>
            ) : (
                <p className="mt-3 flex-1 text-sm text-gray-500">
                    Brak – zarezerwuj auto na stronie głównej.
                </p>
            )}
        </section>
    );
}

export default function UserDashboard() {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0];

    const finished = rentals.filter((rental) => rental.status !== 'cancelled');
    const days = finished.reduce((sum, rental) => sum + rentalDays(rental), 0);
    const spent = payments
        .filter((payment) => payment.status === 'paid')
        .reduce((sum, payment) => sum + payment.amount, 0);

    return (
        <>
            <Head title="Pulpit" />

            <PageHeader
                title={`Witaj, ${firstName}!`}
                description="Podsumowanie Twoich wynajmów i rezerwacji."
                action={
                    <Link href={home()} className={buttonClass('primary', 'md', 'w-full sm:w-auto')}>
                        Nowa rezerwacja
                    </Link>
                }
            />

            <dl className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
                <StatTile label="Wynajmy" value={String(finished.length)} />
                <StatTile label="Dni w trasie" value={String(days)} />
                <div className="col-span-2 xl:col-span-1">
                    <StatTile label="Wydano łącznie" value={formatMoney(spent)} />
                </div>
            </dl>

            <div className="mt-4 grid gap-4 sm:mt-6 md:grid-cols-2">
                <RentalHighlight
                    title="Trwający wynajem"
                    rental={rentals.find((rental) => rental.status === 'active')}
                />
                <RentalHighlight
                    title="Nadchodząca rezerwacja"
                    rental={rentals.find((rental) => rental.status === 'upcoming')}
                />
            </div>

            <section className="mt-8 lg:mt-10">
                <div className="mb-4 flex items-center justify-between gap-4">
                    <h2 className="text-lg font-semibold text-gray-900">
                        Ostatnie wynajmy
                    </h2>
                    <Link
                        href={user.rentals()}
                        className="text-sm font-medium text-brand-600 hover:text-brand-700"
                    >
                        Zobacz wszystkie →
                    </Link>
                </div>
                <RentalsList rentals={rentals.slice(0, 3)} />
            </section>
        </>
    );
}

UserDashboard.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
