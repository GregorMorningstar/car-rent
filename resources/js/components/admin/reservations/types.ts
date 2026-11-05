import type { ReservationState, ReservationStatus } from '@/components/admin/data/labels';

/** Wiersz z ReservationRepository::rows. */
export type AdminReservation = {
    id: number;
    number: string;
    status: ReservationStatus;
    state: ReservationState;
    deleted_at: string | null;
    created_at: string | null;
    start_date: string;
    end_date: string;
    days: number;
    total_price: string;
    notes: string | null;
    client: { id: number; name: string; email: string; phone: string | null };
    car: { id: number; name: string; registration_number: string };
};
