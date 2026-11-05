import { Link } from '@inertiajs/react';
import { EmptyRow, Table, Td, Th } from '@/components/ui/table';
import { formatDate, formatDateHour, formatMoney } from '@/lib/format';
import { openOnClick } from '@/lib/open-on-click';
import admin from '@/routes/admin';
import ReservationStatusBadge from './reservation-status-badge';
import type { AdminReservation } from './types';

type Props = {
    reservations: AdminReservation[];
    /** Ukrycie kolumny, gdy lista dotyczy jednego klienta / jednego auta. */
    hideClient?: boolean;
    hideCar?: boolean;
};

export default function ReservationsTable({ reservations, hideClient, hideCar }: Props) {
    const columns = 6 - Number(!!hideClient) - Number(!!hideCar);

    return (
        <Table
            head={
                <>
                    <Th>Numer</Th>
                    {!hideClient && <Th>Klient</Th>}
                    {!hideCar && <Th>Samochód</Th>}
                    <Th>Termin</Th>
                    <Th>Status</Th>
                    <Th className="text-right">Kwota</Th>
                    <Th>
                        <span className="sr-only">Akcje</span>
                    </Th>
                </>
            }
        >
            {reservations.length === 0 && <EmptyRow colSpan={columns + 1}>Brak rezerwacji.</EmptyRow>}

            {reservations.map((reservation) => (
                <tr key={reservation.id} onClick={openOnClick(admin.reservations.edit(reservation.id).url)} className="cursor-pointer hover:bg-gray-50">
                    <Td className="font-medium whitespace-nowrap text-gray-900">
                        <Link href={admin.reservations.edit(reservation.id)} className="hover:text-brand-600">
                            {reservation.number}
                        </Link>
                    </Td>
                    {!hideClient && (
                        <Td>
                            <Link
                                href={admin.clients.show(reservation.client.id)}
                                className="font-medium whitespace-nowrap text-gray-900 hover:text-brand-600"
                            >
                                {reservation.client.name}
                            </Link>
                            <p className="text-xs text-gray-500">{reservation.client.email}</p>
                        </Td>
                    )}
                    {!hideCar && (
                        <Td>
                            <p className="whitespace-nowrap text-gray-900">{reservation.car.name}</p>
                            <p className="text-xs text-gray-500">{reservation.car.registration_number}</p>
                        </Td>
                    )}
                    <Td className="whitespace-nowrap">
                        {formatDate(reservation.start_date)} – {formatDate(reservation.end_date)}
                        <p className="text-xs text-gray-500">
                            {reservation.days} dni
                        </p>
                    </Td>
                    <Td>
                        <ReservationStatusBadge state={reservation.state} />
                        {reservation.deleted_at && <p className="mt-1 text-xs whitespace-nowrap text-gray-500">{formatDateHour(reservation.deleted_at)}</p>}
                    </Td>
                    <Td className="text-right font-semibold whitespace-nowrap text-gray-900">
                        {formatMoney(Number(reservation.total_price))}
                    </Td>
                    <Td className="text-right">
                        <Link
                            href={admin.reservations.edit(reservation.id)}
                            className="font-medium whitespace-nowrap text-brand-600 hover:text-brand-700"
                        >
                            {reservation.deleted_at ? 'Szczegóły' : 'Edytuj'}
                        </Link>
                    </Td>
                </tr>
            ))}
        </Table>
    );
}
