import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { fuelTypeLabels, licenseLabels, transmissionLabels, vehicleTypeLabels } from '@/components/admin/data/labels';
import type { FuelType, Transmission } from '@/components/admin/data/labels';
import CarCarousel from '@/components/cars/car-carousel';
import { publicCarName } from '@/components/cars/car-card';
import type { PublicCar } from '@/components/cars/car-card';
import { buttonClass } from '@/components/ui/button';
import ColorSwatch from '@/components/ui/color-swatch';
import EquipmentList from '@/components/ui/equipment-list';
import SiteLayout from '@/layouts/site-layout';
import { formatMoney } from '@/lib/format';
import { kmLimitLabel, tierLabel } from '@/lib/pricing';
import { cars as carsRoute } from '@/routes';
import carsRoutes from '@/routes/cars';

/** Szczegóły samochodu - dane z CarService::findPublicCar, bez VIN, rejestracji i przebiegu. */
type CarDetails = PublicCar & {
    fuel_type: FuelType;
    transmission: Transmission;
    color: string | null;
    color_option: { hex: string } | null;
    deposit_amount: string;
    description: string | null;
};

function Spec({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 py-2.5">
            <dt className="text-gray-500">{label}</dt>
            <dd className="flex items-center gap-2 text-right font-medium text-gray-900">{children}</dd>
        </div>
    );
}

export default function Car({ car }: { car: CarDetails }) {
    const { auth } = usePage().props;
    const name = publicCarName(car);

    return (
        <>
            <Head title={name} />

            <div className="gutter py-8 sm:py-12">
                <Link href={carsRoute()} className="text-sm font-medium text-brand-600 hover:text-brand-700">
                    ← Wszystkie samochody
                </Link>

                <h1 className="mt-3 mb-6 text-3xl font-bold text-balance text-gray-900 sm:text-4xl">{name}</h1>

                <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="flex min-w-0 flex-col gap-8">
                        <CarCarousel images={car.images} name={name} />

                        {car.description && (
                            <section>
                                <h2 className="mb-2 text-lg font-semibold text-gray-900">Opis</h2>
                                <p className="whitespace-pre-line text-gray-700">{car.description}</p>
                            </section>
                        )}

                        {car.equipment.length > 0 && (
                            <section>
                                <h2 className="mb-3 text-lg font-semibold text-gray-900">Wyposażenie</h2>
                                <EquipmentList equipment={car.equipment} className="gap-x-5 gap-y-2.5" />
                            </section>
                        )}
                    </div>

                    <aside className="rounded-2xl border border-gray-200 bg-white p-5 lg:sticky lg:top-24">
                        <p className="text-sm text-gray-500">
                            od{' '}
                            <span className="text-3xl font-bold text-gray-900">{formatMoney(Number(car.daily_rate))}</span> / dzień
                        </p>

                        {car.prices.length > 1 && (
                            <dl className="mt-3 rounded-xl bg-gray-50 px-3 py-1 text-sm">
                                {car.prices.map((price) => (
                                    <div key={price.min_days} className="flex items-center justify-between gap-4 py-1.5">
                                        <dt className="text-gray-600">{tierLabel(price)}</dt>
                                        <dd className="font-medium text-gray-900">{formatMoney(Number(price.daily_rate))} / dzień</dd>
                                    </div>
                                ))}
                            </dl>
                        )}

                        <p className="mt-3 text-sm text-gray-500">Kaucja {formatMoney(Number(car.deposit_amount))}</p>

                        <dl className="mt-4 divide-y divide-gray-100 border-y border-gray-100 text-sm">
                            <Spec label="Rodzaj">{vehicleTypeLabels[car.vehicle_type]}</Spec>
                            <Spec label="Wymagane prawo jazdy">{licenseLabels[car.required_license]}</Spec>
                            <Spec label="Rok produkcji">{car.production_year}</Spec>
                            <Spec label="Paliwo">{fuelTypeLabels[car.fuel_type]}</Spec>
                            <Spec label="Skrzynia">{transmissionLabels[car.transmission]}</Spec>
                            <Spec label="Przebieg">{kmLimitLabel(car.km_limit_per_day)}</Spec>
                            <Spec label="Miejsca">{car.seats}</Spec>
                            <Spec label="Drzwi">{car.doors}</Spec>
                            {car.color && (
                                <Spec label="Kolor">
                                    <ColorSwatch hex={car.color_option?.hex} />
                                    {car.color}
                                </Spec>
                            )}
                            {car.locations.length > 0 && (
                                <Spec label="Odbiór">{car.locations.map((location) => location.name).join(', ')}</Spec>
                            )}
                        </dl>

                        {auth.user?.role !== 'administrator' && (
                            <Link href={carsRoutes.book(car.id)} className={buttonClass('primary', 'lg', 'mt-5 w-full')}>
                                Zarezerwuj
                            </Link>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

Car.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
