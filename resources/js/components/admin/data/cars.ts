// Przykładowe samochody - pola jak w tabeli cars.
import type { CarStatus, FuelType, Transmission, VehicleType } from './labels';

export type AdminCar = {
    id: number;
    brand: string;
    model: string;
    vehicle_type: VehicleType;
    registration_number: string;
    vin: string;
    production_year: number;
    fuel_type: FuelType;
    transmission: Transmission;
    seats: number;
    doors: number;
    mileage_km: number;
    color: string | null;
    daily_rate: number;
    deposit_amount: number;
    operational_status: CarStatus;
};

export const cars: AdminCar[] = [
    { id: 1, brand: 'Toyota', model: 'Corolla', vehicle_type: 'passenger', registration_number: 'RKR 12345', vin: 'SB1K93BE10E123456', production_year: 2023, fuel_type: 'hybrid', transmission: 'automatic', seats: 5, doors: 5, mileage_km: 28400, color: 'Biały', daily_rate: 189, deposit_amount: 1000, operational_status: 'active' },
    { id: 2, brand: 'Škoda', model: 'Octavia Combi', vehicle_type: 'passenger', registration_number: 'RKR 23456', vin: 'TMBJJ7NX5PY012345', production_year: 2022, fuel_type: 'diesel', transmission: 'manual', seats: 5, doors: 5, mileage_km: 61200, color: 'Grafitowy', daily_rate: 169, deposit_amount: 1000, operational_status: 'active' },
    { id: 3, brand: 'Volkswagen', model: 'Passat', vehicle_type: 'passenger', registration_number: 'RKR 34567', vin: 'WVWZZZ3CZNE098765', production_year: 2021, fuel_type: 'diesel', transmission: 'automatic', seats: 5, doors: 4, mileage_km: 94300, color: 'Czarny', daily_rate: 199, deposit_amount: 1500, operational_status: 'maintenance' },
    { id: 4, brand: 'Kia', model: 'Sportage', vehicle_type: 'passenger', registration_number: 'RKR 45678', vin: 'U5YPU81BBPL654321', production_year: 2024, fuel_type: 'petrol', transmission: 'automatic', seats: 5, doors: 5, mileage_km: 12800, color: 'Czerwony', daily_rate: 239, deposit_amount: 1500, operational_status: 'active' },
    { id: 5, brand: 'Tesla', model: 'Model 3', vehicle_type: 'passenger', registration_number: 'RKR 56789', vin: '5YJ3E7EB4NF112233', production_year: 2023, fuel_type: 'electric', transmission: 'automatic', seats: 5, doors: 4, mileage_km: 35600, color: 'Niebieski', daily_rate: 319, deposit_amount: 2500, operational_status: 'active' },
    { id: 6, brand: 'Fiat', model: '500', vehicle_type: 'passenger', registration_number: 'RKR 67890', vin: 'ZFA3120000J445566', production_year: 2019, fuel_type: 'petrol', transmission: 'manual', seats: 4, doors: 3, mileage_km: 118900, color: 'Miętowy', daily_rate: 109, deposit_amount: 500, operational_status: 'retired' },
    { id: 7, brand: 'Ford', model: 'Transit', vehicle_type: 'van', registration_number: 'RKR 78901', vin: 'WF0XXXTTGXNA12345', production_year: 2022, fuel_type: 'diesel', transmission: 'manual', seats: 3, doors: 4, mileage_km: 74200, color: 'Biały', daily_rate: 259, deposit_amount: 2000, operational_status: 'active' },
    { id: 8, brand: 'Mercedes-Benz', model: 'Sprinter Travel', vehicle_type: 'bus', registration_number: 'RKR 89012', vin: 'WDB9076331P678901', production_year: 2021, fuel_type: 'diesel', transmission: 'automatic', seats: 9, doors: 4, mileage_km: 132500, color: 'Srebrny', daily_rate: 449, deposit_amount: 3000, operational_status: 'active' },
    { id: 9, brand: 'Iveco', model: 'Daily Laweta', vehicle_type: 'tow_truck', registration_number: 'RKR 90123', vin: 'ZCFC35A0005432109', production_year: 2020, fuel_type: 'diesel', transmission: 'manual', seats: 3, doors: 2, mileage_km: 158700, color: 'Czerwony', daily_rate: 399, deposit_amount: 3000, operational_status: 'active' },
];

export const findCar = (id: number) => cars.find((car) => car.id === id);

export const carName = (car: Pick<AdminCar, 'brand' | 'model'>) => `${car.brand} ${car.model}`;
