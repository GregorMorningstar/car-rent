import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    formatDate,
    formatMoney,
    payments,
    paymentStatuses,
} from '@/components/user/data';
import PageHeader from '@/components/ui/page-header';
import StatusBadge from '@/components/ui/status-badge';
import UserLayout from '@/layouts/user-layout';

function DownloadButton() {
    return (
        <button
            type="button"
            disabled
            title="Pobieranie faktur będzie dostępne wkrótce"
            className="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <svg
                className="size-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth={1.75}
                aria-hidden="true"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
                />
            </svg>
            PDF
        </button>
    );
}

export default function UserPayments() {
    const pending = payments
        .filter((payment) => payment.status === 'pending')
        .reduce((sum, payment) => sum + payment.amount, 0);

    return (
        <>
            <Head title="Płatności" />

            <PageHeader
                title="Płatności"
                description="Historia płatności i faktury za wynajem."
            />

            {pending > 0 && (
                <div className="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Do zapłaty:{' '}
                    <span className="font-semibold">{formatMoney(pending)}</span>
                </div>
            )}

            <ul className="flex flex-col gap-3 md:hidden">
                {payments.map((payment) => (
                    <li
                        key={payment.invoice}
                        className="rounded-2xl border border-gray-200 bg-white p-4"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="font-semibold text-gray-900">
                                    {payment.invoice}
                                </p>
                                <p className="text-xs text-gray-500">
                                    {formatDate(payment.date)} · {payment.method}
                                </p>
                            </div>
                            <StatusBadge {...paymentStatuses[payment.status]} />
                        </div>
                        <div className="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
                            <span className="text-base font-bold text-gray-900">
                                {formatMoney(payment.amount)}
                            </span>
                            <DownloadButton />
                        </div>
                    </li>
                ))}
            </ul>

            <div className="hidden overflow-x-auto rounded-2xl border border-gray-200 bg-white md:block">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-gray-200 bg-gray-50 text-xs tracking-wide text-gray-500 uppercase">
                        <tr>
                            <th scope="col" className="px-5 py-3 font-medium">Faktura</th>
                            <th scope="col" className="px-5 py-3 font-medium">Data</th>
                            <th scope="col" className="hidden px-5 py-3 font-medium lg:table-cell">Metoda</th>
                            <th scope="col" className="px-5 py-3 font-medium">Status</th>
                            <th scope="col" className="px-5 py-3 text-right font-medium">Kwota</th>
                            <th scope="col" className="px-5 py-3">
                                <span className="sr-only">Pobierz</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {payments.map((payment) => (
                            <tr key={payment.invoice} className="hover:bg-gray-50">
                                <td className="px-5 py-4">
                                    <p className="font-medium text-gray-900">{payment.invoice}</p>
                                    <p className="text-xs text-gray-500">{payment.rental}</p>
                                </td>
                                <td className="px-5 py-4 whitespace-nowrap text-gray-700">
                                    {formatDate(payment.date)}
                                </td>
                                <td className="hidden px-5 py-4 text-gray-700 lg:table-cell">
                                    {payment.method}
                                </td>
                                <td className="px-5 py-4">
                                    <StatusBadge {...paymentStatuses[payment.status]} />
                                </td>
                                <td className="px-5 py-4 text-right font-semibold whitespace-nowrap text-gray-900">
                                    {formatMoney(payment.amount)}
                                </td>
                                <td className="px-5 py-4 text-right">
                                    <DownloadButton />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

UserPayments.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
