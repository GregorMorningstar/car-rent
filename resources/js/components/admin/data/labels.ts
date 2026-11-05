// Etykiety zgodne z enumami PHP (app/Enums) - do ujednolicenia, gdy dane przyjdą z backendu.

type Badge = { label: string; className: string };

/** App\Enums\ReservationStatus + wyliczane: "completed" (potwierdzona i po terminie), "deleted" (usunięta przez klienta). */
export type ReservationStatus = 'pending' | 'confirmed' | 'cancelled' | 'expired';
export type ReservationState = ReservationStatus | 'completed' | 'deleted';

export const reservationStates: Record<ReservationState, Badge> = {
    pending: { label: 'Oczekuje', className: 'bg-amber-50 text-amber-700 ring-amber-600/20' },
    confirmed: { label: 'Potwierdzona', className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
    completed: { label: 'Zrealizowana', className: 'bg-blue-50 text-blue-700 ring-blue-600/20' },
    cancelled: { label: 'Anulowana', className: 'bg-brand-50 text-brand-700 ring-brand-600/20' },
    expired: { label: 'Wygasła', className: 'bg-gray-100 text-gray-600 ring-gray-500/20' },
    deleted: { label: 'Usunięta przez klienta', className: 'bg-orange-50 text-orange-800 ring-orange-600/20' },
};

/** App\Enums\CarStatus */
export type CarStatus = 'active' | 'maintenance' | 'retired';

export const carStatuses: Record<CarStatus, Badge> = {
    active: { label: 'Aktywny', className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
    maintenance: { label: 'W serwisie', className: 'bg-amber-50 text-amber-700 ring-amber-600/20' },
    retired: { label: 'Wycofany', className: 'bg-gray-100 text-gray-600 ring-gray-500/20' },
};

/** App\Enums\VehicleType */
export type VehicleType = 'passenger' | 'van' | 'bus' | 'tow_truck';

export const vehicleTypeLabels: Record<VehicleType, string> = {
    passenger: 'Osobowy',
    van: 'Dostawczy',
    bus: 'Autobus',
    tow_truck: 'Laweta',
};

/** App\Enums\DrivingLicense */
export type DrivingLicense = 'b' | 'c' | 'd';

export const licenseLabels: Record<DrivingLicense, string> = {
    b: 'B (do 3,5 t)',
    c: 'C (powyżej 3,5 t)',
    d: 'D (autobus)',
};

/** Kategorię B ma każdy kierowca C i D, ale C nie uprawnia do D ani odwrotnie - jak w DrivingLicense::covers. */
export const coveringLicenses = (required: DrivingLicense) => (Object.keys(licenseLabels) as DrivingLicense[]).filter((license) => license === required || required === 'b');

/** App\Enums\FuelType */
export type FuelType = 'petrol' | 'diesel' | 'hybrid' | 'electric';

export const fuelTypeLabels: Record<FuelType, string> = {
    petrol: 'Benzyna',
    diesel: 'Diesel',
    hybrid: 'Hybryda',
    electric: 'Elektryczny',
};

/** App\Enums\Transmission */
export type Transmission = 'manual' | 'automatic';

export const transmissionLabels: Record<Transmission, string> = {
    manual: 'Manualna',
    automatic: 'Automatyczna',
};

/** Zamienia słownik etykiet na listę opcji dla <select>. */
export const toOptions = <T extends string>(labels: Record<T, string | Badge>) =>
    (Object.keys(labels) as T[]).map((value) => {
        const label = labels[value];

        return { value, label: typeof label === 'string' ? label : label.label };
    });
