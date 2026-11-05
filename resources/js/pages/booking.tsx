import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { coveringLicenses, licenseLabels } from '@/components/admin/data/labels';
import type { DrivingLicense } from '@/components/admin/data/labels';
import { publicCarName } from '@/components/cars/car-card';
import type { PublicCar } from '@/components/cars/car-card';
import LocationPicker from '@/components/home/location-picker';
import type { MapLocation } from '@/components/home/location-picker';
import { buttonClass } from '@/components/ui/button';
import { FormField, Select } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import SiteLayout from '@/layouts/site-layout';
import { daysBetween, formatMoney, localToday } from '@/lib/format';
import { tierForDays, tierLabel } from '@/lib/pricing';
import { cars as carsRoute } from '@/routes';
import carsRoutes from '@/routes/cars';
import userRoutes from '@/routes/user';

/** Limity z zakładki Opcje. */
type Limits = {
    pickup_earliest: string;
    pickup_latest: string;
    rental_min_days: number;
    rental_max_days: number;
    pickup_time_from: string;
    pickup_time_to: string;
    return_time_from: string;
    return_time_to: string;
    saturday_enabled: boolean;
    saturday_time_from: string;
    saturday_time_to: string;
    availability_mode: 'after_time' | 'next_day';
    buffer_minutes: number;
    holidays: Record<string, string>;
};

const mapButtonClass = 'mt-1 h-10 text-sm font-medium text-brand-600 hover:text-brand-700';

const isSaturday = (date: string) => date !== '' && new Date(date).getUTCDay() === 6;

const addDays = (date: string, days: number) => {
    const result = new Date(date);
    result.setUTCDate(result.getUTCDate() + days);

    return result.toISOString().slice(0, 10);
};

/** Edytowana rezerwacja - dane z ReservationService::formData. */
type EditedReservation = {
    id: number;
    confirmed: boolean;
    from: string;
    to: string;
    from_time: string;
    to_time: string;
    pickup: number | null;
    dropoff: number | null;
    notes: string | null;
    driver_name: string | null;
    driver_license_number: string | null;
    driver_license_category: DrivingLicense | null;
};

type Defaults = Partial<Record<'from' | 'to' | 'from_time' | 'to_time' | 'pickup' | 'dropoff', string>>;

export default function Booking({
    car,
    pickupLocations,
    dropoffLocations,
    limits,
    defaults,
    reservation,
}: {
    car: PublicCar;
    pickupLocations: MapLocation[];
    dropoffLocations: MapLocation[];
    limits: Limits;
    defaults: Defaults;
    reservation?: EditedReservation;
}) {
    const { auth } = usePage().props;
    const [mapFor, setMapFor] = useState<'pickup' | 'dropoff' | null>(null);
    const { data, setData, post, put, processing, errors } = useForm({
        from: reservation?.from ?? defaults.from ?? '',
        to: reservation?.to ?? defaults.to ?? '',
        from_time: reservation?.from_time ?? defaults.from_time ?? (limits.pickup_time_from > '10:00' ? limits.pickup_time_from : '10:00'),
        to_time: reservation?.to_time ?? defaults.to_time ?? (limits.return_time_from > '10:00' ? limits.return_time_from : '10:00'),
        pickup: reservation?.pickup ? String(reservation.pickup) : (defaults.pickup ?? ''),
        dropoff: reservation?.dropoff ? String(reservation.dropoff) : (defaults.dropoff ?? ''),
        notes: reservation?.notes ?? '',
        driver_name: reservation?.driver_name ?? auth.user?.name ?? '',
        driver_license_number: reservation?.driver_license_number ?? '',
        driver_license_category: reservation?.driver_license_category ?? car.required_license,
    });

    const earliest = limits.pickup_earliest > localToday() ? limits.pickup_earliest : localToday();
    const name = publicCarName(car);
    // Stała referencja - nowa tablica przy każdym renderze tworzyłaby mapę od nowa.
    const pickups = useMemo(
        () => pickupLocations.filter((location) => car.locations.some((item) => item.id === location.id)),
        [pickupLocations, car.locations],
    );
    const days = data.from && data.to && data.to >= data.from ? daysBetween(data.from, data.to) : undefined;
    const tier = days ? tierForDays(car.prices, days) : undefined;

    /** Powód, dla którego tego dnia nie można odebrać ani zwrócić auta. Serwer sprawdza to samo. */
    const closedReason = (date: string, label: string) => {
        if (limits.holidays[date]) {
            return `${label} nie jest możliwy w święto: ${limits.holidays[date]}.`;
        }

        return isSaturday(date) && !limits.saturday_enabled ? `${label} nie jest możliwy w soboty.` : undefined;
    };

    const fromClosed = closedReason(data.from, 'Odbiór');
    const toClosed = closedReason(data.to, 'Zwrot');
    const pickupHours = isSaturday(data.from)
        ? [limits.saturday_time_from, limits.saturday_time_to]
        : [limits.pickup_time_from, limits.pickup_time_to];
    const returnHours = isSaturday(data.to)
        ? [limits.saturday_time_from, limits.saturday_time_to]
        : [limits.return_time_from, limits.return_time_to];

    const toOptions = (locations: MapLocation[]) => locations.map((location) => ({ value: location.id, label: location.name }));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (reservation) {
            put(userRoutes.reservations.update(reservation.id).url);
        } else {
            post(carsRoutes.book.store(car.id).url);
        }
    };

    return (
        <>
            <Head title={reservation ? `Edycja rezerwacji: ${name}` : `Rezerwacja: ${name}`} />

            <div className="gutter py-8 sm:py-12">
                <Link
                    href={reservation ? userRoutes.rentals() : carsRoutes.show(car.id)}
                    className="text-sm font-medium text-brand-600 hover:text-brand-700"
                >
                    {reservation ? '← Wróć do historii' : '← Wróć do samochodu'}
                </Link>

                <h1 className="mt-3 mb-6 text-3xl font-bold text-balance text-gray-900 sm:text-4xl">
                    {reservation ? 'Edycja rezerwacji' : 'Rezerwacja'}: {name}
                </h1>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <form onSubmit={submit} className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Data odbioru" id="from" error={fromClosed ?? errors.from}>
                                <input
                                    id="from"
                                    type="date"
                                    required
                                    min={earliest}
                                    max={limits.pickup_latest}
                                    value={data.from}
                                    onChange={(event) => setData('from', event.target.value)}
                                    className={inputClass}
                                />
                            </FormField>
                            <FormField label="Data zwrotu" id="to" error={toClosed ?? errors.to}>
                                <input
                                    id="to"
                                    type="date"
                                    required
                                    min={addDays(data.from || earliest, limits.rental_min_days)}
                                    max={addDays(data.from || limits.pickup_latest, limits.rental_max_days)}
                                    value={data.to}
                                    onChange={(event) => setData('to', event.target.value)}
                                    className={inputClass}
                                />
                            </FormField>
                            <FormField
                                label="Godzina odbioru"
                                id="from_time"
                                error={errors.from_time}
                                hint={`Od ${pickupHours[0]} do ${pickupHours[1]}`}
                            >
                                <input
                                    id="from_time"
                                    type="time"
                                    required
                                    step={900}
                                    min={pickupHours[0]}
                                    max={pickupHours[1]}
                                    value={data.from_time}
                                    onChange={(event) => setData('from_time', event.target.value)}
                                    className={inputClass}
                                />
                            </FormField>
                            <FormField
                                label="Godzina zwrotu"
                                id="to_time"
                                error={errors.to_time}
                                hint={`Od ${returnHours[0]} do ${returnHours[1]}`}
                            >
                                <input
                                    id="to_time"
                                    type="time"
                                    required
                                    step={900}
                                    min={returnHours[0]}
                                    max={returnHours[1]}
                                    value={data.to_time}
                                    onChange={(event) => setData('to_time', event.target.value)}
                                    className={inputClass}
                                />
                            </FormField>
                            <FormField label="Miejsce odbioru" id="pickup" error={errors.pickup}>
                                <Select
                                    id="pickup"
                                    required
                                    value={data.pickup}
                                    onChange={(event) => setData('pickup', event.target.value)}
                                    placeholder="Wybierz miejsce"
                                    options={toOptions(pickups)}
                                />
                                <button type="button" onClick={() => setMapFor('pickup')} className={mapButtonClass}>
                                    Wybierz na mapie
                                </button>
                            </FormField>
                            <FormField label="Miejsce zwrotu" id="dropoff" error={errors.dropoff}>
                                <Select
                                    id="dropoff"
                                    required
                                    value={data.dropoff}
                                    onChange={(event) => setData('dropoff', event.target.value)}
                                    placeholder="Wybierz miejsce"
                                    options={toOptions(dropoffLocations)}
                                />
                                <button type="button" onClick={() => setMapFor('dropoff')} className={mapButtonClass}>
                                    Wybierz na mapie
                                </button>
                            </FormField>
                            <FormField label="Kto prowadzi" id="driver_name" error={errors.driver_name}>
                                <input
                                    id="driver_name"
                                    required
                                    maxLength={150}
                                    autoComplete="name"
                                    value={data.driver_name}
                                    onChange={(event) => setData('driver_name', event.target.value)}
                                    className={inputClass}
                                />
                            </FormField>
                            <FormField
                                label="Kategoria prawa jazdy"
                                id="driver_license_category"
                                error={errors.driver_license_category}
                                hint={`Wymagane do tego auta: ${licenseLabels[car.required_license]}`}
                            >
                                <Select
                                    id="driver_license_category"
                                    required
                                    value={data.driver_license_category}
                                    onChange={(event) => setData('driver_license_category', event.target.value as DrivingLicense)}
                                    options={coveringLicenses(car.required_license).map((license) => ({ value: license, label: licenseLabels[license] }))}
                                />
                            </FormField>
                            <FormField
                                label="Numer prawa jazdy (opcjonalnie)"
                                id="driver_license_number"
                                error={errors.driver_license_number}
                                hint="Obsługa sprawdzi dokument przy odbiorze."
                                className="sm:col-span-2"
                            >
                                <input
                                    id="driver_license_number"
                                    maxLength={30}
                                    value={data.driver_license_number}
                                    onChange={(event) => setData('driver_license_number', event.target.value)}
                                    className={inputClass}
                                />
                            </FormField>
                            <FormField label="Uwagi (opcjonalnie)" id="notes" error={errors.notes} className="sm:col-span-2">
                                <textarea
                                    id="notes"
                                    rows={3}
                                    maxLength={500}
                                    value={data.notes}
                                    onChange={(event) => setData('notes', event.target.value)}
                                    className={`${inputClass} h-auto py-3`}
                                />
                            </FormField>
                        </div>

                        {pickups.length === 0 && (
                            <p role="alert" className="mt-4 text-sm text-red-600">
                                Ten samochód nie ma teraz miejsca odbioru.{' '}
                                <Link href={carsRoute()} className="font-medium underline">
                                    Wybierz inny
                                </Link>
                            </p>
                        )}

                        {limits.availability_mode === 'next_day' && (
                            <p className="mt-4 text-sm text-gray-600">
                                Po zwrocie samochód jest dostępny dla kolejnego klienta dopiero następnego dnia.
                            </p>
                        )}

                        {limits.availability_mode === 'after_time' && limits.buffer_minutes > 0 && (
                            <p className="mt-4 text-sm text-gray-600">
                                Między wynajmami tego samochodu jest {limits.buffer_minutes} minut przerwy na zwrot i przygotowanie auta.
                            </p>
                        )}

                        <p className="mt-4 text-sm text-gray-600">
                            {reservation?.confirmed
                                ? 'Rezerwacja jest potwierdzona. Po zapisaniu zmian obsługa musi potwierdzić ją ponownie, w ciągu 24 godzin, nie licząc weekendu i świąt.'
                                : 'Zatrzymamy samochód na 24 godziny, nie licząc weekendu i świąt. Jeśli rezerwacja nie zostanie w tym czasie potwierdzona, samochód wróci do oferty.'}
                        </p>

                        <button
                            type="submit"
                            disabled={processing || pickups.length === 0 || Boolean(fromClosed || toClosed)}
                            className={buttonClass('primary', 'lg', 'mt-4 w-full sm:w-auto')}
                        >
                            {processing ? 'Zapisuję…' : reservation ? 'Zapisz zmiany' : 'Zarezerwuj'}
                        </button>
                    </form>

                    <aside className="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 className="font-semibold text-gray-900">Cena</h2>

                        <dl className="mt-2 text-sm">
                            {car.prices.map((price) => (
                                <div key={price.min_days} className="flex items-center justify-between gap-4 py-1.5">
                                    <dt className="text-gray-600">{tierLabel(price)}</dt>
                                    <dd className="font-medium text-gray-900">{formatMoney(Number(price.daily_rate))} / dzień</dd>
                                </div>
                            ))}
                        </dl>

                        {days && tier && (
                            <p className="mt-3 border-t border-gray-100 pt-3 text-sm text-gray-500">
                                Razem za {days === 1 ? '1 dzień' : `${days} dni`}:{' '}
                                <span className="block text-2xl font-bold text-gray-900">
                                    {formatMoney(Number(tier.daily_rate) * days)}
                                </span>
                            </p>
                        )}
                    </aside>
                </div>
            </div>

            <LocationPicker
                type={mapFor}
                locations={mapFor === 'dropoff' ? dropoffLocations : pickups}
                selected={mapFor ? data[mapFor] : ''}
                selectNearest
                onSelect={(id) => mapFor && setData(mapFor, id)}
                onClose={() => setMapFor(null)}
            />
        </>
    );
}

Booking.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
