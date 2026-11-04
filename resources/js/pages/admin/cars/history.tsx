import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { carName, cars, findCar } from '@/components/admin/data/cars';
import { allReservations } from '@/components/admin/data/reservations';
import ReservationsBrowser from '@/components/admin/reservations/reservations-browser';
import { Select } from '@/components/ui/form-field';
import PageHeader from '@/components/ui/page-header';
import { daysBetween, formatMoney } from '@/lib/format';
import AdminLayout from '@/layouts/admin-layout';
import admin from '@/routes/admin';

/** Historia wypożyczeń - zrealizowane i trwające wynajmy (wszystkich aut albo jednego). */
export default function AdminCarsHistory({ carId }: { carId: number | null }) {
    const car = carId ? findCar(carId) : undefined;

    const rentals = allReservations().filter(
        (reservation) =>
            (reservation.state === 'completed' || reservation.state === 'confirmed') &&
            (!car || reservation.car_id === car.id),
    );
    const days = rentals.reduce((sum, rental) => sum + daysBetween(rental.start_date, rental.end_date), 0);
    const revenue = rentals.reduce((sum, rental) => sum + rental.total_price, 0);

    // Wybór auta zmienia adres (?car=ID), więc widok da się podlinkować.
    const selectCar = (value: string) =>
        router.get(admin.cars.history.url(value ? { query: { car: value } } : undefined), {}, { preserveScroll: true });

    return (
        <>
            <Head title="Historia wypożyczeń" />
            <PageHeader
                title="Historia wypożyczeń"
                description={car ? `${carName(car)} · ${car.registration_number}` : 'Wynajmy wszystkich samochodów.'}
            />

            <div className="mb-6 grid gap-3 sm:grid-cols-[minmax(0,20rem)_1fr] sm:items-center">
                <Select
                    aria-label="Samochód"
                    value={car ? String(car.id) : ''}
                    onChange={(event) => selectCar(event.target.value)}
                    placeholder="Wszystkie samochody"
                    options={cars.map((item) => ({ value: item.id, label: `${carName(item)} (${item.registration_number})` }))}
                />
                <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm sm:justify-end">
                    <div>
                        <dt className="inline text-gray-500">Wynajmy: </dt>
                        <dd className="inline font-semibold text-gray-900">{rentals.length}</dd>
                    </div>
                    <div>
                        <dt className="inline text-gray-500">Dni: </dt>
                        <dd className="inline font-semibold text-gray-900">{days}</dd>
                    </div>
                    <div>
                        <dt className="inline text-gray-500">Przychód: </dt>
                        <dd className="inline font-semibold text-gray-900">{formatMoney(revenue)}</dd>
                    </div>
                </dl>
            </div>

            {/* key - przy zmianie auta wyszukiwarka i strona startują od nowa */}
            <ReservationsBrowser key={car?.id ?? 'all'} reservations={rentals} states={['confirmed', 'completed']} />
        </>
    );
}

AdminCarsHistory.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
