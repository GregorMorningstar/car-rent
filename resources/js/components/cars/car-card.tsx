import { Link, usePage } from '@inertiajs/react';
import type { DrivingLicense, VehicleType } from '@/components/admin/data/labels';
import { vehicleTypeLabels } from '@/components/admin/data/labels';
import { buttonClass } from '@/components/ui/button';
import type { IconSvg } from '@/components/ui/equipment-icon';
import EquipmentList from '@/components/ui/equipment-list';
import { kmLimitLabel } from '@/lib/pricing';
import type { PriceTier } from '@/lib/pricing';
import carsRoutes from '@/routes/cars';
import RentalPrice from './rental-price';

export type PublicEquipment = { id: number; name: string; icon?: string | null; icon_svg?: IconSvg | null };
export type PublicLocation = { id: number; name: string };

/** Samochód w serwisie - dane z CarService, bez VIN i rejestracji. */
export type PublicCar = {
    id: number;
    brand: string;
    model: string;
    vehicle_type: VehicleType;
    required_license: DrivingLicense;
    production_year: number;
    seats: number;
    doors: number;
    daily_rate: string;
    prices: PriceTier[];
    km_limit_per_day: number | null;
    images: { id: number; url: string }[];
    equipment: PublicEquipment[];
    locations: PublicLocation[];
};

export const publicCarName = (car: PublicCar) => `${car.brand} ${car.model}`;

export default function CarCard({
    car,
    bookable = false,
    days,
    bookingQuery,
}: {
    car: PublicCar;
    bookable?: boolean;
    days?: number;
    bookingQuery?: Record<string, string | undefined>;
}) {
    const name = publicCarName(car);
    const { auth } = usePage().props;
    const cover = car.images[0];
    const href = carsRoutes.show(car.id);

    return (
        <article className="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white">
            {/* Zdjęcie to drugi link do tej samej strony, dlatego czytniki ekranu go pomijają. */}
            <Link href={href} tabIndex={-1} aria-hidden="true" className="group block aspect-[392/250] overflow-hidden bg-gray-100">
                {cover ? (
                    <img
                        src={cover.url}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover transition-transform duration-500 group-hover:scale-105 motion-reduce:transition-none"
                    />
                ) : (
                    <span className="flex size-full items-center justify-center text-sm text-gray-500">Brak zdjęcia</span>
                )}
            </Link>

            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-5">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">
                        <Link href={href} className="transition-colors hover:text-brand-600">
                            {name}
                        </Link>
                    </h2>
                    <p className="text-sm text-gray-500">
                        {vehicleTypeLabels[car.vehicle_type]} · {car.production_year}
                    </p>
                </div>

                {car.locations.length > 0 && (
                    <p className="text-sm text-gray-600">{car.locations.map((location) => location.name).join(', ')}</p>
                )}

                <p className="text-sm font-medium text-gray-700">{kmLimitLabel(car.km_limit_per_day)}</p>

                <EquipmentList equipment={car.equipment} />

                <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
                    <RentalPrice fromRate={car.daily_rate} prices={car.prices} days={days} />
                    {bookable && auth.user?.role !== 'administrator' && (
                        <Link href={carsRoutes.book(car.id, { query: bookingQuery })} className={buttonClass('dark', 'sm')}>
                            Zarezerwuj
                        </Link>
                    )}
                </div>
            </div>
        </article>
    );
}
