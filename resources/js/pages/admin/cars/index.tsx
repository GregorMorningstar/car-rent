import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import CarTile from '@/components/admin/cars/car-tile';
import type { FleetCar } from '@/components/admin/cars/car-tile';
import { carName } from '@/components/admin/data/cars';
import { carStatuses, toOptions, vehicleTypeLabels } from '@/components/admin/data/labels';
import { buttonClass } from '@/components/ui/button';
import { Select } from '@/components/ui/form-field';
import PageHeader from '@/components/ui/page-header';
import SearchInput from '@/components/ui/search-input';
import AdminLayout from '@/layouts/admin-layout';
import admin from '@/routes/admin';

export default function AdminCars({ cars }: { cars: FleetCar[] }) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('');
    const [vehicleType, setVehicleType] = useState('');
    const [equipmentId, setEquipmentId] = useState('');
    const normalized = query.trim().toLowerCase();

    // Lista filtra powstaje z wyposażenia dodanego do samochodów.
    const equipmentOptions = [...new Map(cars.flatMap((car) => car.equipment).map((item) => [item.id, item])).values()]
        .sort((first, second) => first.name.localeCompare(second.name, 'pl'))
        .map((item) => ({ value: item.id, label: item.name }));

    const filtered = cars.filter(
        (car) =>
            (!status || car.operational_status === status) &&
            (!vehicleType || car.vehicle_type === vehicleType) &&
            (!equipmentId || car.equipment.some((item) => String(item.id) === equipmentId)) &&
            (!normalized ||
                [carName(car), car.registration_number, car.vin].some((value) => value.toLowerCase().includes(normalized))),
    );

    return (
        <>
            <Head title="Lista samochodów" />
            <PageHeader
                title="Lista samochodów"
                description="Flota wypożyczalni."
                action={
                    <Link href={admin.cars.create()} className={buttonClass('primary', 'md', 'w-full sm:w-auto')}>
                        + Dodaj samochód
                    </Link>
                }
            />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <SearchInput
                    value={query}
                    onChange={setQuery}
                    placeholder="Szukaj: marka, model, rejestracja, VIN…"
                    className="flex-1 sm:min-w-64"
                />
                <div className="sm:w-56">
                    <Select
                        aria-label="Rodzaj pojazdu"
                        value={vehicleType}
                        onChange={(event) => setVehicleType(event.target.value)}
                        placeholder="Wszystkie rodzaje"
                        options={toOptions(vehicleTypeLabels)}
                    />
                </div>
                <div className="sm:w-56">
                    <Select
                        aria-label="Wyposażenie"
                        value={equipmentId}
                        onChange={(event) => setEquipmentId(event.target.value)}
                        placeholder="Całe wyposażenie"
                        options={equipmentOptions}
                    />
                </div>
                <div className="sm:w-56">
                    <Select
                        aria-label="Status"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                        placeholder="Wszystkie statusy"
                        options={toOptions(carStatuses)}
                    />
                </div>
            </div>

            {filtered.length === 0 ? (
                <p className="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
                    Brak samochodów.
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {filtered.map((car) => (
                        <CarTile key={car.id} car={car} />
                    ))}
                </div>
            )}
        </>
    );
}

AdminCars.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
