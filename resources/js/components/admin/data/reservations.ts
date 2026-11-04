// Przykładowe rezerwacje - pola jak w tabeli reservations.
import { daysBetween } from '@/lib/format';
import type { AdminCar } from './cars';
import { findCar } from './cars';
import type { Client } from './clients';
import { findClient } from './clients';
import type { ReservationState, ReservationStatus } from './labels';

export type Reservation = {
    id: number;
    user_id: number;
    car_id: number;
    start_date: string;
    end_date: string;
    status: ReservationStatus;
    daily_rate: number;
    deposit_amount: number;
    total_price: number;
    confirmation_deadline: string;
    notes: string | null;
    created_at: string;
};

/** Rezerwacja z dołączonym klientem i samochodem (jak z ->with(['user.profile', 'car'])). */
export type ReservationWithRelations = Reservation & {
    client: Client;
    car: AdminCar;
    state: ReservationState;
};

type Seed = Omit<Reservation, 'daily_rate' | 'deposit_amount' | 'total_price' | 'confirmation_deadline'>;

const seeds: Seed[] = [
    { id: 131, user_id: 7, car_id: 5, start_date: '2026-11-14', end_date: '2026-11-17', status: 'pending', notes: 'Odbiór po 18:00.', created_at: '2026-11-03T09:41:00' },
    { id: 130, user_id: 2, car_id: 1, start_date: '2026-11-08', end_date: '2026-11-12', status: 'pending', notes: null, created_at: '2026-11-03T08:12:00' },
    { id: 129, user_id: 9, car_id: 4, start_date: '2026-11-20', end_date: '2026-11-27', status: 'pending', notes: 'Fotelik dziecięcy.', created_at: '2026-11-02T21:05:00' },
    { id: 128, user_id: 4, car_id: 2, start_date: '2026-11-06', end_date: '2026-11-07', status: 'pending', notes: null, created_at: '2026-11-02T17:33:00' },
    { id: 127, user_id: 6, car_id: 5, start_date: '2026-12-01', end_date: '2026-12-05', status: 'pending', notes: 'Wyjazd do Krakowa.', created_at: '2026-11-02T11:20:00' },
    { id: 126, user_id: 3, car_id: 4, start_date: '2026-11-10', end_date: '2026-11-13', status: 'pending', notes: null, created_at: '2026-11-01T19:48:00' },
    { id: 125, user_id: 8, car_id: 1, start_date: '2026-11-15', end_date: '2026-11-16', status: 'pending', notes: null, created_at: '2026-11-01T10:02:00' },
    { id: 124, user_id: 5, car_id: 2, start_date: '2026-11-02', end_date: '2026-11-06', status: 'confirmed', notes: null, created_at: '2026-10-28T14:10:00' },
    { id: 123, user_id: 2, car_id: 4, start_date: '2026-11-18', end_date: '2026-11-22', status: 'confirmed', notes: null, created_at: '2026-10-27T09:55:00' },
    { id: 122, user_id: 6, car_id: 1, start_date: '2026-10-20', end_date: '2026-10-24', status: 'confirmed', notes: null, created_at: '2026-10-12T12:30:00' },
    { id: 121, user_id: 3, car_id: 5, start_date: '2026-10-10', end_date: '2026-10-12', status: 'cancelled', notes: 'Klient zrezygnował.', created_at: '2026-10-05T08:44:00' },
    { id: 120, user_id: 9, car_id: 2, start_date: '2026-10-03', end_date: '2026-10-08', status: 'confirmed', notes: null, created_at: '2026-09-25T16:17:00' },
    { id: 119, user_id: 4, car_id: 3, start_date: '2026-09-15', end_date: '2026-09-18', status: 'confirmed', notes: null, created_at: '2026-09-08T10:00:00' },
    { id: 118, user_id: 8, car_id: 6, start_date: '2026-09-01', end_date: '2026-09-03', status: 'expired', notes: 'Brak potwierdzenia w terminie.', created_at: '2026-08-28T19:29:00' },
    { id: 117, user_id: 5, car_id: 3, start_date: '2026-08-12', end_date: '2026-08-19', status: 'confirmed', notes: null, created_at: '2026-08-01T07:51:00' },
    { id: 116, user_id: 2, car_id: 6, start_date: '2026-07-04', end_date: '2026-07-06', status: 'confirmed', notes: null, created_at: '2026-06-25T15:36:00' },
];

export const reservations: Reservation[] = seeds.map((seed) => {
    const car = findCar(seed.car_id)!;
    const deadline = new Date(seed.created_at);
    deadline.setHours(deadline.getHours() + 24);

    return {
        ...seed,
        daily_rate: car.daily_rate,
        deposit_amount: car.deposit_amount,
        total_price: car.daily_rate * daysBetween(seed.start_date, seed.end_date),
        confirmation_deadline: deadline.toISOString(),
    };
});

const today = () => new Date().toISOString().slice(0, 10);

/** Status do wyświetlenia: potwierdzona rezerwacja po terminie zwrotu jest "zrealizowana". */
export const reservationState = (reservation: Reservation): ReservationState =>
    reservation.status === 'confirmed' && reservation.end_date < today()
        ? 'completed'
        : reservation.status;

export const withRelations = (reservation: Reservation): ReservationWithRelations => ({
    ...reservation,
    client: findClient(reservation.user_id)!,
    car: findCar(reservation.car_id)!,
    state: reservationState(reservation),
});

/** Wszystkie rezerwacje z relacjami, od najnowszej. */
export const allReservations = (): ReservationWithRelations[] =>
    reservations
        .map(withRelations)
        .sort((a, b) => b.created_at.localeCompare(a.created_at));

export const findReservation = (id: number) => {
    const reservation = reservations.find((item) => item.id === id);

    return reservation ? withRelations(reservation) : undefined;
};

export const reservationNumber = (reservation: Pick<Reservation, 'id' | 'created_at'>) =>
    `REZ/${reservation.created_at.slice(0, 4)}/${String(reservation.id).padStart(4, '0')}`;
