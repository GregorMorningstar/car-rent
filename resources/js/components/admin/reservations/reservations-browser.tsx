import { useState } from 'react';
import { Select } from '@/components/ui/form-field';
import Pagination from '@/components/ui/pagination';
import SearchInput from '@/components/ui/search-input';
import { usePagination } from '@/hooks/use-pagination';
import type { ReservationState } from '../data/labels';
import { reservationStates } from '../data/labels';
import ReservationsTable from './reservations-table';
import type { AdminReservation } from './types';

type Props = {
    reservations: AdminReservation[];
    /** Odczyt z bazy się nie udał - pusta lista nie znaczy wtedy, że nie ma rezerwacji. */
    failed?: boolean;
    /** Statusy do wyboru w filtrze; pominięte - bez filtra statusu. */
    states?: ReservationState[];
    perPage?: number;
};

const matches = (reservation: AdminReservation, query: string) =>
    [
        reservation.number,
        reservation.client.name,
        reservation.client.email,
        reservation.car.name,
        reservation.car.registration_number,
    ].some((value) => value.toLowerCase().includes(query));

/** Lista rezerwacji z wyszukiwarką, filtrem statusu i stronicowaniem. */
export default function ReservationsBrowser({ reservations, failed, states, perPage = 10 }: Props) {
    const [query, setQuery] = useState('');
    const [state, setState] = useState('');

    const normalized = query.trim().toLowerCase();
    const filtered = reservations.filter(
        (reservation) =>
            (!state || reservation.state === state) && (!normalized || matches(reservation, normalized)),
    );
    const pagination = usePagination(filtered, perPage);

    if (failed) {
        return (
            <p role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                Nie udało się wczytać rezerwacji. Spróbuj ponownie później.
            </p>
        );
    }

    return (
        <>
            <div className="mb-4 flex flex-col gap-3 sm:flex-row">
                <SearchInput
                    value={query}
                    onChange={(value) => {
                        setQuery(value);
                        pagination.setPage(1);
                    }}
                    placeholder="Szukaj: numer, klient, samochód, rejestracja…"
                    className="flex-1"
                />
                {states && (
                    <div className="sm:w-56">
                        <Select
                            aria-label="Status"
                            value={state}
                            onChange={(event) => {
                                setState(event.target.value);
                                pagination.setPage(1);
                            }}
                            placeholder="Wszystkie statusy"
                            options={states.map((value) => ({ value, label: reservationStates[value].label }))}
                        />
                    </div>
                )}
            </div>

            <ReservationsTable reservations={pagination.items} />
            <Pagination {...pagination} onChange={pagination.setPage} />
        </>
    );
}
