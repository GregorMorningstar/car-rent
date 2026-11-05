import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { licenseLabels, toOptions, transmissionLabels } from '@/components/admin/data/labels';
import CarCard from '@/components/cars/car-card';
import type { PublicCar, PublicEquipment } from '@/components/cars/car-card';
import { doorOptions, fuelTypes, vehicleTypes } from '@/components/home/data';
import LocationPicker from '@/components/home/location-picker';
import type { MapLocation } from '@/components/home/location-picker';
import { buttonClass } from '@/components/ui/button';
import EquipmentIcon from '@/components/ui/equipment-icon';
import { FormField, Select } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import SiteLayout from '@/layouts/site-layout';
import { daysBetween, localToday } from '@/lib/format';
import { cn } from '@/lib/utils';
import { cars as carsRoute } from '@/routes';

const emptyFilters = {
    pickup: '',
    from: '',
    to: '',
    days: '',
    vehicle_type: '',
    license: '',
    fuel: '',
    transmission: '',
    doors: '',
    priceFrom: '',
    priceTo: '',
};

type Filters = typeof emptyFilters;
type InitialFilters = Partial<Filters> & { equipment?: (string | number)[] };

/** Adres z aktywnymi filtrami. Termin liczy się dopiero, gdy podane są obie daty. */
const toQuery = (filters: Filters, equipment: number[]) => {
    const complete = filters.from !== '' && filters.to !== '';
    const query = Object.entries(filters).filter(
        ([key, value]) => value !== '' && (complete ? key !== 'days' : key !== 'from' && key !== 'to'),
    );

    return { ...Object.fromEntries(query), ...(equipment.length > 0 ? { equipment } : {}) };
};

export default function Cars({
    cars,
    equipment,
    pickupLocations,
    filters: initialFilters,
}: {
    cars: PublicCar[];
    equipment: PublicEquipment[];
    pickupLocations: MapLocation[];
    filters: InitialFilters;
}) {
    const errors = Object.values(usePage().props.errors);
    const [filters, setFilters] = useState<Filters>({ ...emptyFilters, ...initialFilters });
    const [selected, setSelected] = useState<number[]>((initialFilters.equipment ?? []).map(Number));
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [mapOpen, setMapOpen] = useState(false);
    // Filtry przeładowują propsy - stała lista nie tworzy otwartej mapy od nowa.
    const [mapLocations] = useState(pickupLocations);

    // Ceny na kartach odpowiadają filtrom już zastosowanym na serwerze.
    const rentalDays =
        initialFilters.from && initialFilters.to
            ? daysBetween(initialFilters.from, initialFilters.to)
            : Number(initialFilters.days) || undefined;
    // Termin i miejsce z filtrów wypełniają formularz rezerwacji.
    const bookingQuery = { from: initialFilters.from, to: initialFilters.to, pickup: initialFilters.pickup };
    const hasDates = filters.from !== '' && filters.to !== '';

    const query = JSON.stringify(toQuery(filters, selected));
    const applied = useRef(query);

    // Filtry działają od razu, z krótką przerwą na pisanie cen.
    useEffect(() => {
        if (query === applied.current) {
            return;
        }

        const timer = setTimeout(() => {
            applied.current = query;
            router.get(carsRoute.url(), JSON.parse(query), { preserveState: true, preserveScroll: true, replace: true });
        }, 300);

        return () => clearTimeout(timer);
    }, [query]);

    const set = (key: keyof Filters) => (value: string) => setFilters((previous) => ({ ...previous, [key]: value }));

    const toggleEquipment = (id: number) =>
        setSelected((previous) => (previous.includes(id) ? previous.filter((item) => item !== id) : [...previous, id]));

    const clear = () => {
        setFilters(emptyFilters);
        setSelected([]);
    };

    return (
        <>
            <Head title="Samochody" />
            <div className="gutter border-b border-gray-200 bg-gray-50 py-12 sm:py-20">
                <h1 className="text-3xl font-bold text-balance text-gray-900 uppercase sm:text-5xl">Samochody</h1>
            </div>

            <div className="gutter grid items-start gap-6 py-8 sm:py-12 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <aside className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-semibold text-gray-900">Filtry</h2>
                        <button
                            type="button"
                            onClick={() => setFiltersOpen((value) => !value)}
                            aria-expanded={filtersOpen}
                            aria-controls="car-filters"
                            className="h-10 px-2 text-sm font-medium text-brand-600 lg:hidden"
                        >
                            {filtersOpen ? 'Ukryj' : 'Pokaż'}
                        </button>
                    </div>

                    <form
                        id="car-filters"
                        onSubmit={(event) => event.preventDefault()}
                        className={cn('mt-4 flex-col gap-4 lg:flex', filtersOpen ? 'flex' : 'hidden')}
                    >
                        <FormField label="Miejsce odbioru" id="filter-pickup">
                            <Select
                                id="filter-pickup"
                                value={filters.pickup}
                                onChange={(event) => set('pickup')(event.target.value)}
                                placeholder="Dowolne"
                                options={pickupLocations.map((location) => ({ value: location.id, label: location.name }))}
                            />
                            <button
                                type="button"
                                onClick={() => setMapOpen(true)}
                                className="mt-1 h-10 text-sm font-medium text-brand-600 hover:text-brand-700"
                            >
                                Wybierz na mapie
                            </button>
                        </FormField>
                        <FormField label="Data odbioru" id="filter-from">
                            <input
                                id="filter-from"
                                type="date"
                                min={localToday()}
                                value={filters.from}
                                onChange={(event) => set('from')(event.target.value)}
                                className={inputClass}
                            />
                        </FormField>
                        <FormField label="Data zwrotu" id="filter-to">
                            <input
                                id="filter-to"
                                type="date"
                                min={filters.from || localToday()}
                                value={filters.to}
                                onChange={(event) => set('to')(event.target.value)}
                                className={inputClass}
                            />
                        </FormField>
                        <FormField
                            label="Liczba dni wynajmu"
                            id="filter-days"
                            hint={hasDates ? 'Liczona z wybranego terminu.' : 'Cena zależy od długości wynajmu.'}
                        >
                            <input
                                id="filter-days"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={365}
                                disabled={hasDates}
                                placeholder={hasDates ? String(daysBetween(filters.from, filters.to)) : 'np. 4'}
                                value={hasDates ? '' : filters.days}
                                onChange={(event) => set('days')(event.target.value)}
                                className={cn(inputClass, 'disabled:bg-gray-50')}
                            />
                        </FormField>
                        <FormField label="Rodzaj pojazdu" id="filter-type">
                            <Select
                                id="filter-type"
                                value={filters.vehicle_type}
                                onChange={(event) => set('vehicle_type')(event.target.value)}
                                placeholder="Dowolny"
                                options={vehicleTypes}
                            />
                        </FormField>
                        <FormField label="Wymagane prawo jazdy" id="filter-license">
                            <Select
                                id="filter-license"
                                value={filters.license}
                                onChange={(event) => set('license')(event.target.value)}
                                placeholder="Dowolne"
                                options={toOptions(licenseLabels)}
                            />
                        </FormField>
                        <FormField label="Rodzaj paliwa" id="filter-fuel">
                            <Select
                                id="filter-fuel"
                                value={filters.fuel}
                                onChange={(event) => set('fuel')(event.target.value)}
                                placeholder="Dowolny"
                                options={fuelTypes}
                            />
                        </FormField>
                        <FormField label="Skrzynia biegów" id="filter-transmission">
                            <Select
                                id="filter-transmission"
                                value={filters.transmission}
                                onChange={(event) => set('transmission')(event.target.value)}
                                placeholder="Dowolna"
                                options={toOptions(transmissionLabels)}
                            />
                        </FormField>
                        <FormField label="Ilość drzwi" id="filter-doors">
                            <Select
                                id="filter-doors"
                                value={filters.doors}
                                onChange={(event) => set('doors')(event.target.value)}
                                placeholder="Dowolna"
                                options={doorOptions}
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-3">
                            <FormField label="Cena od" id="filter-price-from">
                                <input
                                    id="filter-price-from"
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    step={10}
                                    placeholder="0"
                                    value={filters.priceFrom}
                                    onChange={(event) => set('priceFrom')(event.target.value)}
                                    className={inputClass}
                                />
                            </FormField>
                            <FormField label="Cena do" id="filter-price-to">
                                <input
                                    id="filter-price-to"
                                    type="number"
                                    inputMode="numeric"
                                    min={filters.priceFrom || 0}
                                    step={10}
                                    placeholder="bez limitu"
                                    value={filters.priceTo}
                                    onChange={(event) => set('priceTo')(event.target.value)}
                                    className={inputClass}
                                />
                            </FormField>
                        </div>

                        {equipment.length > 0 && (
                            <fieldset>
                                <legend className="mb-1 text-sm font-medium text-gray-700">Wyposażenie</legend>
                                {equipment.map((item) => (
                                    <label key={item.id} className="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(item.id)}
                                            onChange={() => toggleEquipment(item.id)}
                                            className="size-5 shrink-0 accent-brand-600"
                                        />
                                        <EquipmentIcon name={item.icon} svg={item.icon_svg} />
                                        {item.name}
                                    </label>
                                ))}
                            </fieldset>
                        )}

                        <button type="button" onClick={clear} className={buttonClass('outline', 'sm', 'border-gray-300')}>
                            Wyczyść filtry
                        </button>
                    </form>
                </aside>

                <section aria-label="Lista samochodów" aria-live="polite">
                    {errors.length > 0 && (
                        <ul role="alert" className="mb-4 space-y-1 text-sm text-red-600">
                            {errors.map((error) => (
                                <li key={error}>{error}</li>
                            ))}
                        </ul>
                    )}

                    <p className="mb-4 text-sm text-gray-500 tabular-nums">Liczba samochodów: {cars.length}</p>

                    {cars.length === 0 ? (
                        <p className="rounded-2xl border border-gray-200 bg-white p-8 text-center text-gray-600">
                            Żaden samochód nie spełnia tych kryteriów.
                        </p>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {cars.map((car) => (
                                <CarCard key={car.id} car={car} days={rentalDays} bookable bookingQuery={bookingQuery} />
                            ))}
                        </div>
                    )}
                </section>
            </div>

            <LocationPicker
                type={mapOpen ? 'pickup' : null}
                locations={mapLocations}
                selected={filters.pickup}
                selectNearest
                onSelect={set('pickup')}
                onClose={() => setMapOpen(false)}
            />
        </>
    );
}

Cars.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
