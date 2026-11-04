import type { Rental } from './data';
import { formatDate, formatMoney, rentalDays, rentalStatuses } from './data';
import StatusBadge from '@/components/ui/status-badge';

function Period({ rental }: { rental: Rental }) {
    return (
        <>
            {formatDate(rental.from)} – {formatDate(rental.to)}
            <span className="text-gray-400">
                {' '}
                · {rentalDays(rental)} dni
            </span>
        </>
    );
}

/** Lista wynajmów: karty na telefonie, tabela od md. */
export default function RentalsList({ rentals }: { rentals: Rental[] }) {
    if (rentals.length === 0) {
        return (
            <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-500">
                Brak wynajmów w tej kategorii.
            </div>
        );
    }

    return (
        <>
            <ul className="flex flex-col gap-3 md:hidden">
                {rentals.map((rental) => (
                    <li
                        key={rental.number}
                        className="rounded-2xl border border-gray-200 bg-white p-4"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="truncate font-semibold text-gray-900">
                                    {rental.car}
                                </p>
                                <p className="text-xs text-gray-500">
                                    {rental.number}
                                </p>
                            </div>
                            <StatusBadge {...rentalStatuses[rental.status]} />
                        </div>

                        <dl className="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                            <dt className="text-gray-500">Termin</dt>
                            <dd className="text-gray-900">
                                <Period rental={rental} />
                            </dd>
                            <dt className="text-gray-500">Odbiór</dt>
                            <dd className="text-gray-900">{rental.pickup}</dd>
                            <dt className="text-gray-500">Zwrot</dt>
                            <dd className="text-gray-900">{rental.dropoff}</dd>
                        </dl>

                        <p className="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-sm text-gray-500">
                            Kwota
                            <span className="text-base font-bold text-gray-900">
                                {formatMoney(rental.total)}
                            </span>
                        </p>
                    </li>
                ))}
            </ul>

            <div className="hidden overflow-x-auto rounded-2xl border border-gray-200 bg-white md:block">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-gray-200 bg-gray-50 text-xs tracking-wide text-gray-500 uppercase">
                        <tr>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Samochód
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Termin
                            </th>
                            <th scope="col" className="hidden px-5 py-3 font-medium xl:table-cell">
                                Odbiór / zwrot
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Status
                            </th>
                            <th scope="col" className="px-5 py-3 text-right font-medium">
                                Kwota
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rentals.map((rental) => (
                            <tr key={rental.number} className="hover:bg-gray-50">
                                <td className="px-5 py-4">
                                    <p className="font-medium text-gray-900">
                                        {rental.car}
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {rental.number}
                                    </p>
                                </td>
                                <td className="px-5 py-4 whitespace-nowrap text-gray-700">
                                    <Period rental={rental} />
                                </td>
                                <td className="hidden px-5 py-4 text-gray-700 xl:table-cell">
                                    <p>{rental.pickup}</p>
                                    {rental.dropoff !== rental.pickup && (
                                        <p className="text-gray-500">
                                            → {rental.dropoff}
                                        </p>
                                    )}
                                </td>
                                <td className="px-5 py-4">
                                    <StatusBadge {...rentalStatuses[rental.status]} />
                                </td>
                                <td className="px-5 py-4 text-right font-semibold whitespace-nowrap text-gray-900">
                                    {formatMoney(rental.total)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
