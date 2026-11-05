import { formatDateHour, formatMoney } from '@/lib/format';

export type PaymentType = 'advance' | 'deposit';

export type PaymentSettlement = {
    amount: string;
    refunded_amount: string | null;
    refund_reason: string | null;
    refunded_at: string | null;
};

export const paymentTypeLabels: Record<PaymentType, string> = {
    advance: 'Zaliczka',
    deposit: 'Kaucja',
};

/** Część wpłaty, której klient nie odzyskał. */
export const retainedAmount = (payment: PaymentSettlement) =>
    payment.refunded_at ? Number(payment.amount) - Number(payment.refunded_amount) : 0;

/** Rozliczenie wpłaty: pełny zwrot, częściowy albo brak, z powodem. */
export default function PaymentOutcome({ payment }: { payment: PaymentSettlement }) {
    if (!payment.refunded_at) {
        return null;
    }

    const date = formatDateHour(payment.refunded_at);
    const refunded = Number(payment.refunded_amount);

    return (
        <>
            <p className="text-xs font-medium text-gray-700">
                {refunded === 0 && `Bez zwrotu, ${date}`}
                {refunded > 0 && refunded < Number(payment.amount) && `Zwrócono ${formatMoney(refunded)} z ${formatMoney(Number(payment.amount))}, ${date}`}
                {refunded >= Number(payment.amount) && `Zwrócono w całości, ${date}`}
            </p>
            {payment.refund_reason && <p className="text-xs whitespace-pre-line text-gray-500">Powód: {payment.refund_reason}</p>}
        </>
    );
}
