import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import type { ReservationState, ReservationStatus } from '@/components/admin/data/labels';
import { reservationStates } from '@/components/admin/data/labels';
import HandoverPanel from '@/components/admin/reservations/handover-panel';
import type { Handover } from '@/components/admin/reservations/handover-panel';
import PaymentsPanel from '@/components/admin/reservations/payments-panel';
import type { ReservationPayment } from '@/components/admin/reservations/payments-panel';
import ReservationStatusBadge from '@/components/admin/reservations/reservation-status-badge';
import type { AdminReservation } from '@/components/admin/reservations/types';
import { buttonClass } from '@/components/ui/button';
import { FormField, Select } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateHour, formatMoney } from '@/lib/format';
import admin from '@/routes/admin';

const lockedInputClass = `${inputClass} disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500`;

const lockedSelectClass = 'disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500';

const statusOptions = (['pending', 'confirmed', 'cancelled', 'expired'] as ReservationStatus[]).map((value) => ({
    value,
    label: reservationStates[value].label,
}));

/** Rezerwacja z ReservationRepository::staffDetails. */
type ReservationDetails = AdminReservation & Handover & {
    state: ReservationState;
    daily_rate: string;
    deposit_amount: string;
    finished: boolean;
    address: string | null;
    acceptance: { via: string; at: string; by: string | null } | null;
    payments: ReservationPayment[];
};

type CarOption = { id: number; name: string; registration_number: string; location_ids: number[] };
type LocationOption = { id: number; name: string; is_pickup: boolean; is_dropoff: boolean };

type Form = {
    car_id: number;
    from: string;
    to: string;
    from_time: string;
    to_time: string;
    pickup: number | null;
    dropoff: number | null;
    status: ReservationStatus;
    notes: string | null;
};

export default function AdminReservationEdit({
    reservation,
    form,
    cars,
    locations,
    paymentMethods,
    acceptanceMethods,
}: {
    reservation: ReservationDetails;
    form: Form;
    cars: CarOption[];
    locations: LocationOption[];
    paymentMethods: string[];
    acceptanceMethods: string[];
}) {
    const { flash } = usePage().props;
    // Auto, miejsca i termin ustala klient - pola odblokowuje dopiero potwierdzenie jego akceptacji.
    const [changesUnlocked, setChangesUnlocked] = useState(false);
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        car_id: String(form.car_id),
        from: form.from,
        to: form.to,
        from_time: form.from_time,
        to_time: form.to_time,
        pickup: form.pickup === null ? '' : String(form.pickup),
        dropoff: form.dropoff === null ? '' : String(form.dropoff),
        status: form.status,
        notes: form.notes ?? '',
        client_acceptance: '',
    });

    const car = cars.find((item) => item.id === Number(data.car_id));
    const sameCar = Number(data.car_id) === reservation.car.id;
    const pickups = locations.filter(
        (location) => (location.is_pickup && car?.location_ids.includes(location.id)) || (sameCar && location.id === form.pickup),
    );
    const dropoffs = locations.filter((location) => location.is_dropoff || location.id === form.dropoff);
    const deleted = reservation.deleted_at !== null;
    const closed = deleted || data.status === 'cancelled' || data.status === 'expired';

    const changeCar = (value: string) => {
        const next = cars.find((item) => item.id === Number(value));
        const available = next?.location_ids.includes(Number(data.pickup)) || (Number(value) === reservation.car.id && Number(data.pickup) === form.pickup);

        setData((current) => ({ ...current, car_id: value, pickup: available ? current.pickup : '' }));
    };

    const unlockChanges = () => {
        if (window.confirm('Czy po kontakcie z klientem zaakceptował on zmianę auta, miejsca, daty lub godziny, np. telefonicznie?')) {
            setChangesUnlocked(true);
            setData('client_acceptance', acceptanceMethods[0]);
            clearErrors('client_acceptance');
        }
    };

    const lockChanges = () => {
        setChangesUnlocked(false);
        setData((current) => ({
            ...current,
            car_id: String(form.car_id),
            pickup: form.pickup === null ? '' : String(form.pickup),
            dropoff: form.dropoff === null ? '' : String(form.dropoff),
            from: form.from,
            to: form.to,
            from_time: form.from_time,
            to_time: form.to_time,
            client_acceptance: '',
        }));
        clearErrors('client_acceptance');
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(admin.reservations.update(reservation.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setChangesUnlocked(false);
                setData('client_acceptance', '');
            },
        });
    };

    return (
        <>
            <Head title={`Rezerwacja ${reservation.number}`} />

            <PageHeader
                title={`Rezerwacja ${reservation.number}`}
                description={`Klient: ${reservation.client.name}`}
                action={<ReservationStatusBadge state={reservation.state} />}
            />

            {reservation.deleted_at && (
                <p role="status" className="mb-4 rounded-2xl bg-orange-50 p-4 text-sm text-orange-900">
                    Klient usunął tę rezerwację {formatDateHour(reservation.deleted_at)}. Dane są tylko do podglądu, a samochód jest wolny.
                </p>
            )}

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
                <div className="flex min-w-0 flex-col gap-6">
                <form onSubmit={submit} className="rounded-2xl border border-gray-200 bg-white">
                    <fieldset disabled={deleted} className="grid min-w-0 gap-4 p-5 sm:grid-cols-2 sm:p-6">
                        <FormField label="Samochód" id="car_id" error={errors.car_id} className="sm:col-span-2">
                            <Select
                                id="car_id"
                                required
                                disabled={!changesUnlocked}
                                className={lockedSelectClass}
                                value={data.car_id}
                                onChange={(event) => changeCar(event.target.value)}
                                options={cars.map((item) => ({ value: item.id, label: `${item.name} · ${item.registration_number}` }))}
                            />
                        </FormField>

                        <FormField label="Data odbioru" id="from" error={errors.from}>
                            <input
                                id="from"
                                type="date"
                                required
                                disabled={!changesUnlocked}
                                value={data.from}
                                onChange={(event) => setData('from', event.target.value)}
                                className={lockedInputClass}
                            />
                        </FormField>

                        <FormField label="Godzina odbioru" id="from_time" error={errors.from_time}>
                            <input
                                id="from_time"
                                type="time"
                                required
                                disabled={!changesUnlocked}
                                value={data.from_time}
                                onChange={(event) => setData('from_time', event.target.value)}
                                className={lockedInputClass}
                            />
                        </FormField>

                        <FormField label="Data zwrotu" id="to" error={errors.to}>
                            <input
                                id="to"
                                type="date"
                                required
                                disabled={!changesUnlocked}
                                min={data.from}
                                value={data.to}
                                onChange={(event) => setData('to', event.target.value)}
                                className={lockedInputClass}
                            />
                        </FormField>

                        <FormField label="Godzina zwrotu" id="to_time" error={errors.to_time}>
                            <input
                                id="to_time"
                                type="time"
                                required
                                disabled={!changesUnlocked}
                                value={data.to_time}
                                onChange={(event) => setData('to_time', event.target.value)}
                                className={lockedInputClass}
                            />
                        </FormField>

                        <FormField label="Miejsce odbioru" id="pickup" error={errors.pickup}>
                            <Select
                                id="pickup"
                                required
                                disabled={!changesUnlocked}
                                className={lockedSelectClass}
                                value={data.pickup}
                                onChange={(event) => setData('pickup', event.target.value)}
                                placeholder="Wybierz miejsce"
                                options={pickups.map((location) => ({ value: location.id, label: location.name }))}
                            />
                        </FormField>

                        <FormField label="Miejsce zwrotu" id="dropoff" error={errors.dropoff}>
                            <Select
                                id="dropoff"
                                required
                                disabled={!changesUnlocked}
                                className={lockedSelectClass}
                                value={data.dropoff}
                                onChange={(event) => setData('dropoff', event.target.value)}
                                placeholder="Wybierz miejsce"
                                options={dropoffs.map((location) => ({ value: location.id, label: location.name }))}
                            />
                        </FormField>

                        {!deleted && <section aria-label="Akceptacja klienta" className="flex flex-col gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 sm:col-span-2">
                            {changesUnlocked ? (
                                <>
                                    <FormField label="Jak klient zaakceptował zmianę?" id="client_acceptance" error={errors.client_acceptance}>
                                        <Select
                                            id="client_acceptance"
                                            required
                                            value={data.client_acceptance}
                                            onChange={(event) => setData('client_acceptance', event.target.value)}
                                            options={acceptanceMethods.map((method) => ({ value: method, label: method }))}
                                        />
                                    </FormField>
                                    <button type="button" onClick={lockChanges} className="h-10 self-start font-medium text-brand-600 hover:text-brand-700">
                                        Anuluj zmiany
                                    </button>
                                </>
                            ) : (
                                <>
                                    <p>Samochód, miejsca, daty i godziny ustala klient. Administrator zmienia je tylko po kontakcie z nim i jego akceptacji.</p>
                                    {errors.client_acceptance && <p className="text-red-600">{errors.client_acceptance}</p>}
                                    <button type="button" onClick={unlockChanges} className={buttonClass('outline', 'sm', 'self-start border-amber-400 bg-white')}>
                                        Klient zaakceptował zmianę
                                    </button>
                                </>
                            )}
                            {reservation.acceptance && (
                                <p className="text-xs text-amber-800">
                                    Ostatnia zmiana uzgodniona z klientem: {reservation.acceptance.via.toLowerCase()}, {formatDateHour(reservation.acceptance.at)}
                                    {reservation.acceptance.by && `, wpisał(a): ${reservation.acceptance.by}`}
                                </p>
                            )}
                        </section>}

                        <FormField
                            label="Status"
                            id="status"
                            error={errors.status}
                            hint="Klient dostanie wiadomość po potwierdzeniu albo zmianie auta lub terminu."
                            className="sm:col-span-2"
                        >
                            <Select
                                id="status"
                                value={data.status}
                                onChange={(event) => setData('status', event.target.value as ReservationStatus)}
                                options={statusOptions}
                            />
                        </FormField>

                        <FormField label="Notatki" id="notes" error={errors.notes} className="sm:col-span-2">
                            <textarea
                                id="notes"
                                rows={4}
                                maxLength={500}
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                className={`${inputClass} h-auto py-3`}
                            />
                        </FormField>
                    </fieldset>

                    <div className="flex flex-col-reverse gap-3 border-t border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-end sm:p-6">
                        {!deleted && (
                            <p role={flash ? 'status' : undefined} className={flash ? 'text-sm font-medium text-emerald-700 sm:mr-auto' : 'text-sm text-gray-500 sm:mr-auto'}>
                                {flash ?? 'Cena przeliczy się po zmianie auta lub terminu.'}
                            </p>
                        )}
                        <Link href={admin.reservations.index()} className={buttonClass('outline', 'md', 'border-gray-300')}>
                            Wróć
                        </Link>
                        {!deleted && (
                            <button type="submit" disabled={processing} className={buttonClass('dark')}>
                                Zapisz zmiany
                            </button>
                        )}
                    </div>
                </form>

                <HandoverPanel reservation={reservation} canHandOver={reservation.status === 'confirmed' && !deleted} />
                </div>

                <aside className="flex flex-col gap-4">
                    <section className="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 className="text-sm font-semibold text-gray-500">Klient</h2>
                        <Link href={admin.clients.show(reservation.client.id)} className="mt-2 block font-semibold text-gray-900 hover:text-brand-600">
                            {reservation.client.name}
                        </Link>
                        <p className="text-sm break-words text-gray-600">{reservation.client.email}</p>
                        {reservation.client.phone && <p className="text-sm text-gray-600">{reservation.client.phone}</p>}
                        {reservation.address && <p className="mt-2 text-sm text-gray-600">{reservation.address}</p>}
                    </section>

                    <section className="rounded-2xl border border-gray-200 bg-white p-5 text-sm">
                        <h2 className="font-semibold text-gray-500">Podsumowanie</h2>
                        <dl className="mt-3 grid grid-cols-[1fr_auto] gap-y-2">
                            <dt className="text-gray-500">Stawka</dt>
                            <dd className="text-right text-gray-900">{formatMoney(Number(reservation.daily_rate))} / dzień</dd>
                            <dt className="text-gray-500">Liczba dni</dt>
                            <dd className="text-right text-gray-900">{reservation.days}</dd>
                            <dt className="text-gray-500">Kaucja</dt>
                            <dd className="text-right text-gray-900">{formatMoney(Number(reservation.deposit_amount))}</dd>
                            <dt className="border-t border-gray-100 pt-2 font-semibold text-gray-900">Razem</dt>
                            <dd className="border-t border-gray-100 pt-2 text-right text-base font-bold text-gray-900">
                                {formatMoney(Number(reservation.total_price))}
                            </dd>
                        </dl>
                    </section>

                    <PaymentsPanel
                        reservationId={reservation.id}
                        type="advance"
                        client={reservation.client.name}
                        limit={reservation.total_price}
                        payments={reservation.payments.filter((payment) => payment.type === 'advance')}
                        methods={paymentMethods}
                        canAdd={!closed}
                    />
                    <PaymentsPanel
                        reservationId={reservation.id}
                        type="deposit"
                        client={reservation.client.name}
                        limit={reservation.deposit_amount}
                        payments={reservation.payments.filter((payment) => payment.type === 'deposit')}
                        methods={paymentMethods}
                        canAdd={!closed}
                    />
                </aside>
            </div>
        </>
    );
}

AdminReservationEdit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
