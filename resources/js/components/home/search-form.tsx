import { Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode, RefObject } from 'react';
import { publicCarName } from '@/components/cars/car-card';
import RentalPrice from '@/components/cars/rental-price';
import type { PublicCar, PublicEquipment } from '@/components/cars/car-card';
import { buttonClass } from '@/components/ui/button';
import EquipmentIcon from '@/components/ui/equipment-icon';
import EquipmentList from '@/components/ui/equipment-list';
import { inputClass } from '@/components/ui/input';
import { daysBetween } from '@/lib/format';
import { cn } from '@/lib/utils';
import { cars as carsRoute } from '@/routes';
import carsRoutes from '@/routes/cars';
import { doorOptions, fuelTypes, vehicleTypes } from './data';
import LocationPicker from './location-picker';
import type { MapLocation } from './location-picker';

type Option = { value: string; label: string };

const toLocationOptions = (locations: MapLocation[]): Option[] =>
    locations.map((location) => ({ value: String(location.id), label: location.name }));

function Field({
    label,
    id,
    action,
    children,
}: {
    label: string;
    id: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex items-center justify-between gap-2">
                <label htmlFor={id} className="text-sm font-medium text-gray-700">
                    {label}
                </label>
                {action}
            </div>
            {children}
        </div>
    );
}

function Chevron({ className }: { className?: string }) {
    return (
        <svg
            className={cn('size-5', className)}
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z"
                clipRule="evenodd"
            />
        </svg>
    );
}

function Select({
    id,
    value,
    placeholder,
    options,
    required,
    onChange,
}: {
    id: string;
    value: string;
    placeholder: string;
    options: Option[];
    required?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="relative">
            <select
                id={id}
                required={required}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={cn(inputClass, 'truncate pr-10', !value && 'text-gray-500')}
            >
                {/* Pole wymagane: podpowiedź nie do wybrania; opcjonalne: "dowolne". */}
                <option value="" disabled={required}>
                    {placeholder}
                </option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <Chevron className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-gray-400" />
        </div>
    );
}

function PriceInput({
    id,
    value,
    min,
    placeholder,
    onChange,
}: {
    id: string;
    value: string;
    min?: string;
    placeholder: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="relative">
            <input
                id={id}
                type="number"
                inputMode="numeric"
                min={min || 0}
                step={10}
                placeholder={placeholder}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={cn(inputClass, 'pr-16')}
            />
            <span className="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-sm text-gray-400">
                zł/dzień
            </span>
        </div>
    );
}

/** Wybór rodzaju pojazdu - jedna opcja zaznaczona, pusta wartość to wszystkie rodzaje. */
function VehicleTypeChoice({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <fieldset className="mb-4">
            <legend className="mb-1.5 text-sm font-medium text-gray-700">Rodzaj pojazdu</legend>
            <div className="flex flex-wrap gap-2">
                {[{ value: '', label: 'Wszystkie' }, ...vehicleTypes].map((option) => (
                    <label key={option.value} className="cursor-pointer">
                        <input
                            type="radio"
                            name="vehicle_type"
                            value={option.value}
                            checked={value === option.value}
                            onChange={() => onChange(option.value)}
                            className="peer sr-only"
                        />
                        <span className="flex h-10 items-center rounded-lg border border-gray-300 px-3.5 text-sm font-medium text-gray-700 transition-colors peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-600 peer-focus-visible:ring-offset-2">
                            {option.label}
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

function today() {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 10);
}

const initialFilters = { doors: '', priceFrom: '', priceTo: '', fuel: '' };

/** Małe okno nad stroną startową z samochodami spełniającymi kryteria. */
function ResultsDialog({
    dialog,
    cars,
    days,
    allCarsHref,
}: {
    dialog: RefObject<HTMLDialogElement | null>;
    cars: PublicCar[];
    days?: number;
    allCarsHref: ReturnType<typeof carsRoute>;
}) {
    return (
        <dialog
            ref={dialog}
            aria-label="Wyniki wyszukiwania"
            onClick={(event) => event.target === dialog.current && dialog.current.close()}
            className="m-auto max-h-[85dvh] w-[min(40rem,calc(100vw-2rem))] overflow-y-auto rounded-2xl bg-white p-0 text-gray-900 shadow-2xl backdrop:bg-gray-950/60"
        >
            <div className="flex flex-col gap-4 p-4 sm:p-5">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold">
                        {cars.length > 0 ? `Dostępne samochody: ${cars.length}` : 'Brak dostępnych samochodów'}
                    </h2>
                    <button
                        type="button"
                        onClick={() => dialog.current?.close()}
                        aria-label="Zamknij"
                        className="-mr-2 flex size-10 items-center justify-center rounded-lg text-xl text-gray-500 hover:bg-gray-100"
                    >
                        ×
                    </button>
                </div>

                {cars.length === 0 ? (
                    <p className="text-gray-600">Żaden samochód nie spełnia wymagań w tym terminie. Zmień kryteria i spróbuj ponownie.</p>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {cars.map((car) => (
                            <li key={car.id} className="flex gap-3 rounded-xl border border-gray-200 p-3">
                                <Link
                                    href={carsRoutes.show(car.id)}
                                    tabIndex={-1}
                                    aria-hidden="true"
                                    className="aspect-[392/250] w-24 shrink-0 overflow-hidden rounded-lg bg-gray-100 sm:w-36"
                                >
                                    {car.images[0] && (
                                        <img src={car.images[0].url} alt="" loading="lazy" className="size-full object-cover" />
                                    )}
                                </Link>
                                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                    <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <h3 className="font-semibold">
                                            <Link href={carsRoutes.show(car.id)} className="hover:text-brand-600">
                                                {publicCarName(car)}
                                            </Link>
                                        </h3>
                                        <RentalPrice fromRate={car.daily_rate} prices={car.prices} days={days} size="text-base" />
                                    </div>
                                    <EquipmentList equipment={car.equipment} className="text-xs" />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <Link href={allCarsHref} className={buttonClass('dark', 'md', 'w-full')}>
                    {cars.length > 0 ? 'Zobacz na stronie samochodów' : 'Zobacz wszystkie samochody'}
                </Link>
            </div>
        </dialog>
    );
}

export default function SearchForm({
    equipment,
    pickupLocations,
    dropoffLocations,
}: {
    equipment: PublicEquipment[];
    pickupLocations: MapLocation[];
    dropoffLocations: MapLocation[];
}) {
    const [data, setData] = useState({
        pickup: '',
        dropoff: '',
        from: '',
        to: '',
        vehicle_type: '',
        ...initialFilters,
    });
    const [selectedEquipment, setSelectedEquipment] = useState<number[]>([]);
    const [moreOpen, setMoreOpen] = useState(false);
    const [searching, setSearching] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const [results, setResults] = useState<PublicCar[]>([]);
    const [mapFor, setMapFor] = useState<'pickup' | 'dropoff' | null>(null);
    const dialog = useRef<HTMLDialogElement>(null);

    // Puste filtry pomijamy, żeby adres był czytelny.
    const query = {
        ...Object.fromEntries(Object.entries(data).filter(([, value]) => value !== '')),
        ...(selectedEquipment.length > 0 ? { equipment: selectedEquipment } : {}),
    };

    const set = (key: keyof typeof data) => (value: string) =>
        setData((previous) => ({ ...previous, [key]: value }));

    const activeFilters =
        (Object.keys(initialFilters) as (keyof typeof initialFilters)[]).filter((key) => data[key] !== '').length +
        selectedEquipment.length;

    const toggleEquipment = (id: number) =>
        setSelectedEquipment((previous) =>
            previous.includes(id) ? previous.filter((item) => item !== id) : [...previous, id],
        );

    const clearFilters = () => {
        setData((previous) => ({ ...previous, ...initialFilters }));
        setSelectedEquipment([]);
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (searching) {
            return;
        }

        setSearching(true);
        setErrors([]);

        try {
            const response = await fetch(carsRoutes.search.url({ query }), { headers: { Accept: 'application/json' } });

            if (response.status === 422) {
                const body: { errors: Record<string, string[]> } = await response.json();
                setErrors(Object.values(body.errors).flat());
            } else if (response.status === 429) {
                setErrors(['Zbyt wiele wyszukiwań. Spróbuj ponownie za chwilę.']);
            } else if (!response.ok) {
                setErrors(['Nie udało się wyszukać samochodów. Spróbuj ponownie.']);
            } else {
                const body: { cars: PublicCar[] } = await response.json();
                setResults(body.cars);
                dialog.current?.showModal();
            }
        } catch {
            setErrors(['Nie udało się wyszukać samochodów. Sprawdź połączenie i spróbuj ponownie.']);
        } finally {
            setSearching(false);
        }
    };

    const mapButton = (type: 'pickup' | 'dropoff') => (
        <button
            type="button"
            onClick={() => setMapFor(type)}
            className="py-1 text-xs font-medium whitespace-nowrap text-brand-600 hover:text-brand-700"
        >
            Wyszukaj na mapie
        </button>
    );

    return (
        <>
        <form
            onSubmit={submit}
            className="rounded-2xl bg-white p-4 pb-2 text-gray-900 shadow-2xl ring-1 ring-black/5 sm:p-6 sm:pb-3"
        >
            <VehicleTypeChoice value={data.vehicle_type} onChange={set('vehicle_type')} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-[repeat(4,minmax(0,1fr))_auto] xl:items-end">
                <Field label="Miejsce odbioru" id="pickup" action={mapButton('pickup')}>
                    <Select
                        id="pickup"
                        required
                        value={data.pickup}
                        placeholder="Wybierz miejsce odbioru"
                        options={toLocationOptions(pickupLocations)}
                        onChange={set('pickup')}
                    />
                </Field>

                <Field label="Miejsce zwrotu" id="dropoff" action={mapButton('dropoff')}>
                    <Select
                        id="dropoff"
                        required
                        value={data.dropoff}
                        placeholder="Wybierz miejsce zwrotu"
                        options={toLocationOptions(dropoffLocations)}
                        onChange={set('dropoff')}
                    />
                </Field>

                <Field label="Data odbioru" id="from">
                    <input
                        id="from"
                        type="date"
                        required
                        min={today()}
                        value={data.from}
                        onChange={(event) => set('from')(event.target.value)}
                        className={inputClass}
                    />
                </Field>

                <Field label="Data zwrotu" id="to">
                    <input
                        id="to"
                        type="date"
                        required
                        min={data.from || today()}
                        value={data.to}
                        onChange={(event) => set('to')(event.target.value)}
                        className={inputClass}
                    />
                </Field>

                <button
                    type="submit"
                    disabled={searching}
                    className={buttonClass(
                        'primary',
                        'lg',
                        'w-full sm:col-span-2 lg:col-span-4 xl:col-span-1 xl:h-11 xl:text-sm',
                    )}
                >
                    <svg
                        className="size-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={2}
                        aria-hidden="true"
                    >
                        <path
                            strokeLinecap="round"
                            d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"
                        />
                    </svg>
                    {searching ? 'Szukam…' : 'Szukaj auta'}
                </button>
            </div>

            {errors.length > 0 && (
                <ul role="alert" className="mt-4 space-y-1 text-sm text-red-600">
                    {errors.map((error) => (
                        <li key={error}>{error}</li>
                    ))}
                </ul>
            )}

            <div className="mt-4 flex items-center justify-between gap-4 border-t border-gray-100 pt-3">
                <button
                    type="button"
                    onClick={() => setMoreOpen((value) => !value)}
                    aria-expanded={moreOpen}
                    aria-controls="search-more"
                    className="-ml-2 flex h-10 items-center gap-1.5 rounded-lg px-2 text-sm font-semibold text-gray-700 transition-colors hover:text-brand-600"
                >
                    Więcej opcji
                    {activeFilters > 0 && (
                        <span className="flex size-5 items-center justify-center rounded-full bg-brand-600 text-xs text-white tabular-nums">
                            {activeFilters}
                        </span>
                    )}
                    <Chevron
                        className={cn(
                            'transition-transform duration-300',
                            moreOpen && 'rotate-180',
                        )}
                    />
                </button>

                {activeFilters > 0 && (
                    <button
                        type="button"
                        onClick={clearFilters}
                        className="h-10 px-2 text-sm text-gray-500 hover:text-gray-900"
                    >
                        Wyczyść filtry
                    </button>
                )}
            </div>

            {/* Rozwijanie przez grid-rows 0fr → 1fr; inert wyłącza pola, gdy panel jest zwinięty. */}
            <div
                id="search-more"
                inert={!moreOpen}
                className={cn(
                    'grid transition-[grid-template-rows,opacity] duration-300 motion-reduce:transition-none',
                    moreOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0',
                )}
            >
                {/* -mx-1 px-1: miejsce na obwódkę focusa, której nie ucina overflow-hidden */}
                <div className="-mx-1 overflow-hidden px-1">
                    <div className="grid gap-4 pt-2 pb-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Field label="Ilość drzwi" id="doors">
                            <Select
                                id="doors"
                                value={data.doors}
                                placeholder="Dowolna"
                                options={doorOptions}
                                onChange={set('doors')}
                            />
                        </Field>

                        <Field label="Cena wynajmu od" id="priceFrom">
                            <PriceInput
                                id="priceFrom"
                                value={data.priceFrom}
                                placeholder="0"
                                onChange={set('priceFrom')}
                            />
                        </Field>

                        <Field label="Cena wynajmu do" id="priceTo">
                            <PriceInput
                                id="priceTo"
                                value={data.priceTo}
                                min={data.priceFrom}
                                placeholder="bez limitu"
                                onChange={set('priceTo')}
                            />
                        </Field>

                        <Field label="Rodzaj paliwa" id="fuel">
                            <Select
                                id="fuel"
                                value={data.fuel}
                                placeholder="Dowolny"
                                options={fuelTypes}
                                onChange={set('fuel')}
                            />
                        </Field>
                    </div>

                    {equipment.length > 0 && (
                        <fieldset className="pb-3">
                            <legend className="mb-1.5 text-sm font-medium text-gray-700">Wyposażenie</legend>
                            <div className="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-4">
                                {equipment.map((item) => (
                                    <label key={item.id} className="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            checked={selectedEquipment.includes(item.id)}
                                            onChange={() => toggleEquipment(item.id)}
                                            className="size-5 shrink-0 accent-brand-600"
                                        />
                                        <EquipmentIcon name={item.icon} svg={item.icon_svg} />
                                        {item.name}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                    )}
                </div>
            </div>
        </form>
        <ResultsDialog
            dialog={dialog}
            cars={results}
            days={data.from && data.to ? daysBetween(data.from, data.to) : undefined}
            allCarsHref={carsRoute({ query })}
        />
        <LocationPicker
            type={mapFor}
            locations={mapFor === 'dropoff' ? dropoffLocations : pickupLocations}
            selected={mapFor ? data[mapFor] : ''}
            onSelect={(id) => mapFor && set(mapFor)(id)}
            onClose={() => setMapFor(null)}
        />
        </>
    );
}
