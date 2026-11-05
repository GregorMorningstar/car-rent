import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PaymentOutcome, { paymentTypeLabels, retainedAmount } from '@/components/payments/payment-outcome';
import type { PaymentSettlement, PaymentType } from '@/components/payments/payment-outcome';
import PageHeader from '@/components/ui/page-header';
import UserLayout from '@/layouts/user-layout';
import { formatDateHour, formatMoney } from '@/lib/format';

/** Wpłata z UserPanelService::payments - zawsze do rezerwacji zalogowanego klienta. */
type ClientPayment = PaymentSettlement & {
    id: number;
    type: PaymentType;
    number: string;
    car: string;
    payer: string;
    method: string;
    paid_at: string;
};

const sumOf = (payments: ClientPayment[], type: PaymentType) =>
    payments.filter((payment) => payment.type === type && !payment.refunded_at).reduce((sum, payment) => sum + Number(payment.amount), 0);

export default function UserPayments({ payments, failed }: { payments: ClientPayment[]; failed: boolean }) {
    const retained = payments.reduce((sum, payment) => sum + retainedAmount(payment), 0);
    const summary = [
        { label: 'Wpłacone zaliczki', value: sumOf(payments, 'advance') },
        { label: 'Kaucja do rozliczenia', value: sumOf(payments, 'deposit') },
        { label: 'Zatrzymano', value: retained },
    ];

    return (
        <>
            <Head title="Płatności" />

            <PageHeader title="Płatności" description="Twoje zaliczki i kaucje wraz z rozliczeniem." />

            {failed ? (
                <p role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Nie udało się wczytać płatności. Spróbuj ponownie później.
                </p>
            ) : payments.length === 0 ? (
                <p className="rounded-2xl border border-gray-200 bg-white p-6 text-sm text-gray-500">Nie masz jeszcze żadnych wpłat.</p>
            ) : (
                <>
                    <dl className="mb-4 grid gap-3 sm:grid-cols-3">
                        {summary.map((item) => (
                            <div key={item.label} className="rounded-2xl border border-gray-200 bg-white p-4">
                                <dt className="text-xs text-gray-500">{item.label}</dt>
                                <dd className="text-lg font-bold text-gray-900">{formatMoney(item.value)}</dd>
                            </div>
                        ))}
                    </dl>

                    <ul className="flex flex-col gap-3">
                        {payments.map((payment) => (
                            <li key={payment.id} className="rounded-2xl border border-gray-200 bg-white p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-semibold text-gray-900">{paymentTypeLabels[payment.type]}</p>
                                        <p className="text-xs text-gray-500">
                                            {payment.car} · {payment.number}
                                        </p>
                                    </div>
                                    <p className="text-base font-bold whitespace-nowrap text-gray-900">{formatMoney(Number(payment.amount))}</p>
                                </div>
                                <p className="mt-2 text-xs text-gray-500">
                                    {formatDateHour(payment.paid_at)} · {payment.method} · {payment.payer}
                                </p>
                                <PaymentOutcome payment={payment} />
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </>
    );
}

UserPayments.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
