import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import StatusBadge from '@/components/ui/status-badge';
import RentalHistory, { rentalLength, rentalStates } from '@/components/user/rental-history';
import type { UserRental } from '@/components/user/rental-history';
import UserLayout from '@/layouts/user-layout';
import { formatDateHour, formatMoney } from '@/lib/format';
import { home } from '@/routes';
import user from '@/routes/user';

type Stats = { rentals: number; days: number; spent: string };

function StatTile({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="mt-1 text-2xl font-bold text-gray-900 tabular-nums sm:text-3xl">{value}</dd>
        </div>
    );
}

function RentalHighlight({ title, rental, emptyText }: { title: string; rental: UserRental | null; emptyText: string }) {
    return (
        <section className="flex flex-col rounded-2xl border border-gray-200 bg-white p-5">
            <h2 className="text-sm font-semibold text-gray-500">{title}</h2>

            {rental ? (
                <>
                    <div className="mt-3 flex items-start justify-between gap-3">
                        <p className="text-lg font-semibold text-gray-900">{rental.car}</p>
                        <StatusBadge {...rentalStates[rental.state]} />
                    </div>
                    <p className="mt-1 text-sm text-gray-600">
                        {formatDateHour(rental.from)} – {formatDateHour(rental.to)} · {rentalLength(rental)}
                    </p>
                    {rental.pickup && (
                        <p className="mt-3 text-sm text-gray-500">
                            Odbiór: <span className="text-gray-900">{rental.pickup}</span>
                        </p>
                    )}
                    {rental.dropoff && (
                        <p className="text-sm text-gray-500">
                            Zwrot: <span className="text-gray-900">{rental.dropoff}</span>
                        </p>
                    )}
                </>
            ) : (
                <p className="mt-3 flex-1 text-sm text-gray-500">{emptyText}</p>
            )}
        </section>
    );
}

export default function UserDashboard({
    stats,
    current,
    next,
    rentals,
    failed,
}: {
    stats: Stats;
    current: UserRental | null;
    next: UserRental | null;
    rentals: UserRental[];
    failed: boolean;
}) {
    const { auth, flash } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0];

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

            {!failed && (
                <>
                    <dl className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
                        <StatTile label="Wynajmy" value={String(stats.rentals)} />
                        <StatTile label="Dni w trasie" value={String(stats.days)} />
                        <div className="col-span-2 xl:col-span-1">
                            <StatTile label="Wydano łącznie" value={formatMoney(Number(stats.spent))} />
                        </div>
                    </dl>

                    <div className="mt-4 grid gap-4 sm:mt-6 md:grid-cols-2">
                        <RentalHighlight title="Trwający wynajem" rental={current} emptyText="Nie masz teraz wynajętego auta." />
                        <RentalHighlight
                            title="Nadchodząca rezerwacja"
                            rental={next}
                            emptyText="Brak – zarezerwuj auto na stronie głównej."
                        />
                    </div>
                </>
            )}

            <section className="mt-8 lg:mt-10">
                <div className="mb-4 flex items-center justify-between gap-4">
                    <h2 className="text-lg font-semibold text-gray-900">Ostatnie wynajmy</h2>
                    <Link href={user.rentals()} className="text-sm font-medium text-brand-600 hover:text-brand-700">
                        Zobacz wszystkie →
                    </Link>
                </div>
                {flash && (
                    <p role="status" className="mb-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {flash}
                    </p>
                )}
                <RentalHistory rentals={rentals} failed={failed} emptyText="Nie masz jeszcze żadnych wynajmów." />
            </section>
        </>
    );
}

UserDashboard.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
