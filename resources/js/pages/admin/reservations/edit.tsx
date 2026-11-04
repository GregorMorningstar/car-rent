import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { carName, cars } from '@/components/admin/data/cars';
import { formatAddress } from '@/components/admin/data/clients';
import type { ReservationStatus } from '@/components/admin/data/labels';
import { reservationStates } from '@/components/admin/data/labels';
import { findReservation, reservationNumber } from '@/components/admin/data/reservations';
import ReservationStatusBadge from '@/components/admin/reservations/reservation-status-badge';
import { buttonClass } from '@/components/ui/button';
import { FormField, Select } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import { daysBetween, formatMoney } from '@/lib/format';
import AdminLayout from '@/layouts/admin-layout';
import admin from '@/routes/admin';

const statusOptions = (['pending', 'confirmed', 'cancelled', 'expired'] as ReservationStatus[]).map((value) => ({
    value,
    label: reservationStates[value].label,
}));

export default function AdminReservationEdit({ id }: { id: number }) {
    const reservation = findReservation(id);
    const [saved, setSaved] = useState(false);
    const [data, setData] = useState({
        car_id: String(reservation?.car_id ?? ''),
        start_date: reservation?.start_date ?? '',
        end_date: reservation?.end_date ?? '',
        status: reservation?.status ?? 'pending',
        notes: reservation?.notes ?? '',
    });

    if (!reservation) {
        return (
            <>
                <Head title="Nie znaleziono rezerwacji" />
                <PageHeader title="Nie znaleziono rezerwacji" description={`Brak rezerwacji o ID ${id}.`} />
                <Link href={admin.reservations.index()} className={buttonClass('dark')}>
                    Wróć do listy
                </Link>
            </>
        );
    }

    const set = (key: keyof typeof data) => (value: string) => {
        setData((previous) => ({ ...previous, [key]: value }));
        setSaved(false);
    };

    const car = cars.find((item) => item.id === Number(data.car_id));
    const days = data.start_date && data.end_date ? daysBetween(data.start_date, data.end_date) : 0;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSaved(true);
    };

    return (
        <>
            <Head title={`Edycja ${reservationNumber(reservation)}`} />

            <PageHeader
                title={`Edycja rezerwacji ${reservationNumber(reservation)}`}
                description={`Klient: ${reservation.client.name}`}
                action={<ReservationStatusBadge state={reservation.state} />}
            />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem] xl:items-start">
                <form onSubmit={submit} className="rounded-2xl border border-gray-200 bg-white">
                    <div className="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                        <FormField label="Samochód" id="car_id" className="sm:col-span-2">
                            <Select
                                id="car_id"
                                required
                                value={data.car_id}
                                onChange={(event) => set('car_id')(event.target.value)}
                                options={cars.map((item) => ({
                                    value: item.id,
                                    label: `${carName(item)} · ${item.registration_number} · ${formatMoney(item.daily_rate)}/dzień`,
                                }))}
                            />
                        </FormField>

                        <FormField label="Data odbioru" id="start_date">
                            <input
                                id="start_date"
                                type="date"
                                required
                                value={data.start_date}
                                onChange={(event) => set('start_date')(event.target.value)}
                                className={inputClass}
                            />
                        </FormField>

                        <FormField label="Data zwrotu" id="end_date">
                            <input
                                id="end_date"
                                type="date"
                                required
                                min={data.start_date}
                                value={data.end_date}
                                onChange={(event) => set('end_date')(event.target.value)}
                                className={inputClass}
                            />
                        </FormField>

                        <FormField label="Status" id="status" className="sm:col-span-2">
                            <Select
                                id="status"
                                value={data.status}
                                onChange={(event) => set('status')(event.target.value)}
                                options={statusOptions}
                            />
                        </FormField>

                        <FormField label="Notatki" id="notes" className="sm:col-span-2">
                            <textarea
                                id="notes"
                                rows={4}
                                value={data.notes}
                                onChange={(event) => set('notes')(event.target.value)}
                                className={`${inputClass} h-auto py-3`}
                            />
                        </FormField>
                    </div>

                    <div className="flex flex-col-reverse gap-3 border-t border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-end sm:p-6">
                        {saved && (
                            <p role="status" className="text-sm text-gray-500 sm:mr-auto">
                                Podgląd – zapis zostanie podłączony do backendu.
                            </p>
                        )}
                        <Link href={admin.reservations.index()} className={buttonClass('outline', 'md', 'border-gray-300')}>
                            Anuluj
                        </Link>
                        <button type="submit" className={buttonClass('dark')}>
                            Zapisz zmiany
                        </button>
                    </div>
                </form>

                <aside className="flex flex-col gap-4">
                    <section className="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 className="text-sm font-semibold text-gray-500">Klient</h2>
                        <Link
                            href={admin.clients.show(reservation.client.id)}
                            className="mt-2 block font-semibold text-gray-900 hover:text-brand-600"
                        >
                            {reservation.client.name}
                        </Link>
                        <p className="text-sm text-gray-600">{reservation.client.email}</p>
                        {reservation.client.profile && (
                            <>
                                <p className="text-sm text-gray-600">{reservation.client.profile.phone}</p>
                                <p className="mt-2 text-sm text-gray-600">{formatAddress(reservation.client.profile)}</p>
                            </>
                        )}
                    </section>

                    <section className="rounded-2xl border border-gray-200 bg-white p-5 text-sm">
                        <h2 className="font-semibold text-gray-500">Podsumowanie</h2>
                        <dl className="mt-3 grid grid-cols-[1fr_auto] gap-y-2">
                            <dt className="text-gray-500">Stawka</dt>
                            <dd className="text-right text-gray-900">{car ? formatMoney(car.daily_rate) : '–'} / dzień</dd>
                            <dt className="text-gray-500">Liczba dni</dt>
                            <dd className="text-right text-gray-900">{days}</dd>
                            <dt className="text-gray-500">Kaucja</dt>
                            <dd className="text-right text-gray-900">{car ? formatMoney(car.deposit_amount) : '–'}</dd>
                            <dt className="border-t border-gray-100 pt-2 font-semibold text-gray-900">Razem</dt>
                            <dd className="border-t border-gray-100 pt-2 text-right text-base font-bold text-gray-900">
                                {car ? formatMoney(car.daily_rate * days) : '–'}
                            </dd>
                        </dl>
                    </section>
                </aside>
            </div>
        </>
    );
}

AdminReservationEdit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
