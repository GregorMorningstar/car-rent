import { Link, router } from '@inertiajs/react';
import StatusBadge from '@/components/ui/status-badge';
import { formatDateHour, formatDateTime, formatMoney } from '@/lib/format';
import userRoutes from '@/routes/user';

/** Stan wynajmu - App\Enums\RentalState. */
export type RentalState = 'pending' | 'upcoming' | 'active' | 'completed' | 'cancelled';

/** Wynajem z bazy - dane z UserPanelService. */
export type UserRental = {
    id: number;
    number: string;
    car: string;
    pickup: string | null;
    dropoff: string | null;
    from: string;
    to: string;
    days: number;
    daily_rate: string;
    total_price: string;
    state: RentalState;
    confirm_by: string | null;
    can_edit: boolean;
    can_delete: boolean;
};

export const rentalStates: Record<RentalState, { label: string; className: string }> = {
    pending: { label: 'Oczekuje', className: 'bg-amber-50 text-amber-700 ring-amber-600/20' },
    upcoming: { label: 'Nadchodzący', className: 'bg-blue-50 text-blue-700 ring-blue-600/20' },
    active: { label: 'W trakcie', className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
    completed: { label: 'Zakończony', className: 'bg-gray-100 text-gray-700 ring-gray-500/20' },
    cancelled: { label: 'Anulowany', className: 'bg-brand-50 text-brand-700 ring-brand-600/20' },
};

const removeRental = (rental: UserRental) => {
    if (window.confirm(`Usunąć rezerwację ${rental.number}?`)) {
        router.delete(userRoutes.reservations.destroy(rental.id).url, { preserveScroll: true });
    }
};

export const rentalLength = (rental: UserRental) => (rental.days === 1 ? '1 dzień' : `${rental.days} dni`);

/** Historia wynajmów: pozycje jedna pod drugą, na każdej szerokości ekranu. */
export default function RentalHistory({
    rentals,
    failed,
    emptyText = 'Brak wynajmów.',
}: {
    rentals: UserRental[];
    failed: boolean;
    emptyText?: string;
}) {
    if (failed) {
        return (
            <p role="alert" className="rounded-2xl border border-gray-200 bg-white px-6 py-8 text-center text-red-600">
                Nie udało się wczytać historii wynajmów. Spróbuj ponownie później.
            </p>
        );
    }

    if (rentals.length === 0) {
        return (
            <p className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-500">
                {emptyText}
            </p>
        );
    }

    return (
        <ul className="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
            {rentals.map((rental) => (
                <li key={rental.id} className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 p-4 sm:px-5">
                    <div className="min-w-0 flex-1 basis-56">
                        <p className="font-semibold text-gray-900">{rental.car}</p>
                        <p className="text-xs text-gray-500">{rental.number}</p>
                    </div>
                    <div className="basis-80 text-sm text-gray-700">
                        <p>
                            {formatDateHour(rental.from)} – {formatDateHour(rental.to)}
                            <span className="text-gray-500"> · {rentalLength(rental)}</span>
                        </p>
                        {rental.pickup && (
                            <p className="text-xs text-gray-500">
                                {rental.pickup}
                                {rental.dropoff && rental.dropoff !== rental.pickup && ` → ${rental.dropoff}`}
                            </p>
                        )}
                        {rental.confirm_by && (
                            <p className="text-xs font-medium text-amber-700">Rezerwacja ważna do {formatDateTime(rental.confirm_by)}</p>
                        )}
                    </div>
                    <StatusBadge {...rentalStates[rental.state]} />
                    <p className="ml-auto text-right font-semibold whitespace-nowrap text-gray-900 sm:w-28">
                        {formatMoney(Number(rental.total_price))}
                    </p>
                    {(rental.can_edit || rental.can_delete) && (
                        <div className="flex w-full justify-end gap-1">
                            {rental.can_edit && (
                                <Link
                                    href={userRoutes.reservations.edit(rental.id)}
                                    aria-label={`Edytuj rezerwację ${rental.number}`}
                                    className="flex h-10 items-center px-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                                >
                                    Edytuj
                                </Link>
                            )}
                            {rental.can_delete && (
                                <button
                                    type="button"
                                    onClick={() => removeRental(rental)}
                                    aria-label={`Usuń rezerwację ${rental.number}`}
                                    className="h-10 px-2 text-sm font-medium text-brand-600 hover:text-brand-700"
                                >
                                    Usuń
                                </button>
                            )}
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}
