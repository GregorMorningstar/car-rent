import { Link } from '@inertiajs/react';
import { EmptyRow, Table, Td, Th } from '@/components/ui/table';
import { daysBetween, formatDate, formatMoney } from '@/lib/format';
import admin from '@/routes/admin';
import { carName } from '../data/cars';
import type { ReservationWithRelations } from '../data/reservations';
import { reservationNumber } from '../data/reservations';
import ReservationStatusBadge from './reservation-status-badge';

type Props = {
    reservations: ReservationWithRelations[];
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
                <tr key={reservation.id} className="hover:bg-gray-50">
                    <Td className="font-medium whitespace-nowrap text-gray-900">{reservationNumber(reservation)}</Td>
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
                            <p className="whitespace-nowrap text-gray-900">{carName(reservation.car)}</p>
                            <p className="text-xs text-gray-500">{reservation.car.registration_number}</p>
                        </Td>
                    )}
                    <Td className="whitespace-nowrap">
                        {formatDate(reservation.start_date)} – {formatDate(reservation.end_date)}
                        <p className="text-xs text-gray-500">
                            {daysBetween(reservation.start_date, reservation.end_date)} dni
                        </p>
                    </Td>
                    <Td>
                        <ReservationStatusBadge state={reservation.state} />
                    </Td>
                    <Td className="text-right font-semibold whitespace-nowrap text-gray-900">
                        {formatMoney(reservation.total_price)}
                    </Td>
                    <Td className="text-right">
                        <Link
                            href={admin.reservations.edit(reservation.id)}
                            className="font-medium whitespace-nowrap text-brand-600 hover:text-brand-700"
                        >
                            Edytuj
                        </Link>
                    </Td>
                </tr>
            ))}
        </Table>
    );
}
