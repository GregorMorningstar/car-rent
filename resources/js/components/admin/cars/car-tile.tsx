import { Link } from '@inertiajs/react';
import ColorSwatch from '@/components/ui/color-swatch';
import EquipmentList from '@/components/ui/equipment-list';
import StatusBadge from '@/components/ui/status-badge';
import { formatMoney } from '@/lib/format';
import { kmLimitLabel, tierLabel } from '@/lib/pricing';
import type { PriceTier } from '@/lib/pricing';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import { carName } from '../data/cars';
import type { CarStatus, FuelType, Transmission, VehicleType } from '../data/labels';
import { carStatuses, fuelTypeLabels, transmissionLabels, vehicleTypeLabels } from '../data/labels';
import type { CarImage, CarOption } from './car-form';

/** Samochód z bazy na liście w panelu - dane z CarService::allCars. */
export type FleetCar = {
    id: number;
    brand: string;
    model: string;
    vehicle_type: VehicleType;
    registration_number: string;
    vin: string;
    production_year: number;
    color: string | null;
    color_option: { hex: string } | null;
    fuel_type: FuelType;
    transmission: Transmission;
    seats: number;
    doors: number;
    mileage_km: number;
    daily_rate: string;
    prices: PriceTier[];
    km_limit_per_day: number | null;
    deposit_amount: string;
    operational_status: CarStatus;
    images: CarImage[];
    equipment: CarOption[];
    locations: CarOption[];
};

const mileage = new Intl.NumberFormat('pl-PL');

export default function CarTile({ car }: { car: FleetCar }) {
    const cover = car.images[0];

    return (
        <article className="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <div className="relative aspect-[392/250] bg-gray-100">
                {cover ? (
                    <img src={cover.url} alt={carName(car)} loading="lazy" className="size-full object-cover" />
                ) : (
                    <p className="flex size-full items-center justify-center text-sm text-gray-500">Brak zdjęcia</p>
                )}
                <div className="absolute top-3 left-3">
                    <StatusBadge {...carStatuses[car.operational_status]} />
                </div>
                {car.images.length > 1 && (
                    <span className="absolute right-3 bottom-3 rounded-full bg-gray-950/70 px-2.5 py-0.5 text-xs font-medium text-white tabular-nums">
                        {car.images.length} zdjęć
                    </span>
                )}
            </div>

            <div className="flex flex-1 flex-col gap-3 p-4">
                <div>
                    <h2 className="font-semibold text-gray-900">{carName(car)}</h2>
                    <p className="flex flex-wrap items-center gap-x-1.5 text-sm text-gray-500">
                        {vehicleTypeLabels[car.vehicle_type]} · {car.production_year}
                        {car.color && (
                            <>
                                {' · '}
                                <ColorSwatch hex={car.color_option?.hex} className="size-3.5" />
                                {car.color}
                            </>
                        )}
                    </p>
                </div>

                <dl className="grid grid-cols-2 gap-x-3 gap-y-1 text-sm text-gray-600">
                    <dt className="sr-only">Rejestracja</dt>
                    <dd className="font-mono text-xs leading-5">{car.registration_number}</dd>
                    <dt className="sr-only">Przebieg</dt>
                    <dd className="tabular-nums">{mileage.format(car.mileage_km)} km</dd>
                    <dt className="sr-only">Paliwo i skrzynia</dt>
                    <dd>
                        {fuelTypeLabels[car.fuel_type]}, {transmissionLabels[car.transmission].toLowerCase()}
                    </dd>
                    <dt className="sr-only">Limit kilometrów</dt>
                    <dd>{kmLimitLabel(car.km_limit_per_day)}</dd>
                    <dt className="sr-only">Drzwi i miejsca</dt>
                    <dd>
                        {car.doors} drzwi, {car.seats} miejsc
                    </dd>
                </dl>

                <p className={cn('text-sm', car.locations.length > 0 ? 'text-gray-600' : 'font-medium text-amber-700')}>
                    {car.locations.length > 0
                        ? car.locations.map((location) => location.name).join(', ')
                        : 'Brak miejsca - auto nie pojawi się w wyszukiwarce'}
                </p>

                <EquipmentList equipment={car.equipment} />

                <div className="mt-auto flex items-end justify-between gap-3 border-t border-gray-100 pt-3">
                    <div>
                        <p className="font-semibold text-gray-900">od {formatMoney(Number(car.daily_rate))}</p>
                        <p className="text-xs text-gray-500">
                            {car.prices.map((price) => `${tierLabel(price)}: ${formatMoney(Number(price.daily_rate))}`).join(' · ')}
                        </p>
                        <p className="text-xs text-gray-500">kaucja {formatMoney(Number(car.deposit_amount))}</p>
                    </div>
                    <div className="flex gap-1">
                        <Link
                            href={admin.cars.history({ query: { car: car.id } })}
                            className="flex h-10 items-center px-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                        >
                            Historia
                        </Link>
                        <Link
                            href={admin.cars.edit(car.id)}
                            className="flex h-10 items-center px-2 text-sm font-medium text-brand-600 hover:text-brand-700"
                        >
                            Edytuj
                        </Link>
                    </div>
                </div>
            </div>
        </article>
    );
}
