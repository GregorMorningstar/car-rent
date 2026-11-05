import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { coveringLicenses, licenseLabels } from '@/components/admin/data/labels';
import type { DrivingLicense } from '@/components/admin/data/labels';
import { buttonClass } from '@/components/ui/button';
import { FormField, Select } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import { formatDateHour } from '@/lib/format';
import admin from '@/routes/admin';

export type Handover = {
    id: number;
    driver: { name: string | null; license_number: string | null; license_category: DrivingLicense | null };
    required_license: DrivingLicense | null;
    car_mileage_km: number | null;
    picked_up_at: string | null;
    returned_at: string | null;
    returned_mileage_km: number | null;
};

const kilometers = new Intl.NumberFormat('pl-PL');

const ErrorLine = ({ message }: { message?: string }) => (message ? <p role="alert" className="text-sm text-red-600">{message}</p> : null);

/** Dane kierowcy sprawdzane przy wydaniu samochodu. */
function HandOverForm({ reservation }: { reservation: Handover }) {
    const required = reservation.required_license ?? 'b';
    const { data, setData, post, processing, errors } = useForm({
        driver_name: reservation.driver.name ?? '',
        driver_license_number: reservation.driver.license_number ?? '',
        driver_license_category: reservation.driver.license_category ?? required,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(admin.reservations.handover(reservation.id).url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mt-3 grid gap-4 sm:grid-cols-2">
            <FormField label="Kto prowadzi" id="handover_name" error={errors.driver_name}>
                <input
                    id="handover_name"
                    required
                    maxLength={150}
                    value={data.driver_name}
                    onChange={(event) => setData('driver_name', event.target.value)}
                    className={inputClass}
                />
            </FormField>
            <FormField label="Numer prawa jazdy" id="handover_number" error={errors.driver_license_number}>
                <input
                    id="handover_number"
                    required
                    maxLength={30}
                    value={data.driver_license_number}
                    onChange={(event) => setData('driver_license_number', event.target.value)}
                    className={inputClass}
                />
            </FormField>
            <FormField
                label="Kategoria prawa jazdy"
                id="handover_category"
                error={errors.driver_license_category}
                hint={`Wymagane do tego auta: ${licenseLabels[required]}`}
                className="sm:col-span-2"
            >
                <Select
                    id="handover_category"
                    required
                    value={data.driver_license_category}
                    onChange={(event) => setData('driver_license_category', event.target.value as DrivingLicense)}
                    options={coveringLicenses(required).map((license) => ({ value: license, label: licenseLabels[license] }))}
                />
            </FormField>
            <ErrorLine message={(errors as Record<string, string>).reservation} />
            <button type="submit" disabled={processing} className={`${buttonClass('dark')} sm:col-span-2 sm:justify-self-start`}>
                Wydaj samochód
            </button>
        </form>
    );
}

/** Przebieg przy zwrocie - nie może być mniejszy od aktualnego, bo staje się aktualnym przebiegiem auta. */
function TakeBackForm({ reservation }: { reservation: Handover }) {
    const current = reservation.car_mileage_km ?? 0;
    const { data, setData, post, processing, errors } = useForm({ mileage_km: '' });
    const driven = data.mileage_km === '' ? null : Number(data.mileage_km) - current;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(admin.reservations.takeback(reservation.id).url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mt-3 flex flex-col gap-3">
            <FormField
                label="Przebieg przy zwrocie (km)"
                id="return_mileage"
                error={errors.mileage_km}
                hint={`Aktualny przebieg: ${kilometers.format(current)} km${driven !== null && driven >= 0 ? `, przejechano ${kilometers.format(driven)} km` : ''}`}
            >
                <input
                    id="return_mileage"
                    type="number"
                    inputMode="numeric"
                    required
                    min={current}
                    step={1}
                    value={data.mileage_km}
                    onChange={(event) => setData('mileage_km', event.target.value)}
                    className={inputClass}
                />
            </FormField>
            <ErrorLine message={(errors as Record<string, string>).reservation} />
            <button type="submit" disabled={processing} className={`${buttonClass('dark')} self-start`}>
                Przyjmij zwrot
            </button>
        </form>
    );
}

/** Kto prowadzi, wydanie samochodu i przyjęcie zwrotu. */
export default function HandoverPanel({ reservation, canHandOver }: { reservation: Handover; canHandOver: boolean }) {
    const { driver } = reservation;

    return (
        <section aria-label="Odbiór i zwrot" className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <h2 className="font-semibold text-gray-900">Odbiór i zwrot</h2>

            {(reservation.picked_up_at || !canHandOver) && (
                <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt className="text-gray-500">Kto prowadzi</dt>
                    <dd className="text-gray-900">{driver.name ?? '–'}</dd>
                    <dt className="text-gray-500">Prawo jazdy</dt>
                    <dd className="text-gray-900">
                        {driver.license_number ?? 'brak numeru'}
                        {driver.license_category && `, ${licenseLabels[driver.license_category]}`}
                    </dd>
                    {reservation.picked_up_at && (
                        <>
                            <dt className="text-gray-500">Wydano</dt>
                            <dd className="text-gray-900">{formatDateHour(reservation.picked_up_at)}</dd>
                        </>
                    )}
                    {reservation.returned_at && (
                        <>
                            <dt className="text-gray-500">Zwrócono</dt>
                            <dd className="text-gray-900">{formatDateHour(reservation.returned_at)}</dd>
                            <dt className="text-gray-500">Przebieg</dt>
                            <dd className="text-gray-900">{kilometers.format(reservation.returned_mileage_km ?? 0)} km</dd>
                        </>
                    )}
                </dl>
            )}

            {!reservation.picked_up_at && canHandOver && <HandOverForm reservation={reservation} />}
            {reservation.picked_up_at && !reservation.returned_at && <TakeBackForm reservation={reservation} />}
        </section>
    );
}
