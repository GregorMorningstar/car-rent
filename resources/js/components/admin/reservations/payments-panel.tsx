import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { buttonClass } from '@/components/ui/button';
import { FormField, Select } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PaymentOutcome, { paymentTypeLabels, retainedAmount } from '@/components/payments/payment-outcome';
import type { PaymentSettlement, PaymentType } from '@/components/payments/payment-outcome';
import { formatDateHour, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

export type ReservationPayment = PaymentSettlement & {
    id: number;
    type: PaymentType;
    payer: string;
    method: string;
    paid_at: string;
};

/** Bieżąca chwila w formacie pola datetime-local, w strefie przeglądarki. */
const localNow = () => {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 16);
};

const FormError = ({ message }: { message?: string }) => (message ? <p className="text-xs text-red-600">{message}</p> : null);

/** Zwrot w całości, częściowy albo bez zwrotu - przy dwóch ostatnich trzeba podać powód. */
function RefundForm({ reservationId, payment, onClose }: { reservationId: number; payment: ReservationPayment; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({ refund_amount: payment.amount, refund_reason: '' });
    const reduced = data.refund_amount !== '' && Number(data.refund_amount) < Number(payment.amount);
    const fieldId = `refund-${payment.id}`;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(admin.reservations.payments.refund({ id: reservationId, payment: payment.id }).url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <form onSubmit={submit} className="mt-2 flex w-full flex-col gap-3">
            <FormField label="Kwota zwrotu (zł)" id={`${fieldId}-amount`} error={errors.refund_amount}>
                <input
                    id={`${fieldId}-amount`}
                    type="number"
                    inputMode="decimal"
                    required
                    min={0}
                    max={payment.amount}
                    step="0.01"
                    value={data.refund_amount}
                    onChange={(event) => setData('refund_amount', event.target.value)}
                    className={inputClass}
                />
            </FormField>
            {reduced && (
                <FormField label="Powód zmniejszenia lub braku zwrotu" id={`${fieldId}-reason`} error={errors.refund_reason}>
                    <textarea
                        id={`${fieldId}-reason`}
                        required
                        maxLength={500}
                        rows={3}
                        value={data.refund_reason}
                        onChange={(event) => setData('refund_reason', event.target.value)}
                        placeholder="np. niezgłoszenie szkody"
                        className={cn(inputClass, 'h-auto py-2')}
                    />
                </FormField>
            )}
            <FormError message={(errors as Record<string, string>).reservation} />
            <button type="button" onClick={() => setData('refund_amount', '0')} className="h-10 self-start font-medium text-brand-600 hover:text-brand-700">
                Bez zwrotu
            </button>
            <div className="flex gap-2">
                <button type="submit" disabled={processing} className={buttonClass('dark', 'sm', 'flex-1')}>
                    Rozlicz
                </button>
                <button type="button" onClick={onClose} className={buttonClass('outline', 'sm', 'flex-1 border-gray-300')}>
                    Anuluj
                </button>
            </div>
        </form>
    );
}

/** Wpłaty jednego rodzaju przy rezerwacji: lista, dodawanie i rozliczenie. */
export default function PaymentsPanel({
    reservationId,
    type,
    client,
    limit,
    payments,
    methods,
    canAdd,
}: {
    reservationId: number;
    type: PaymentType;
    client: string | null;
    limit: string;
    payments: ReservationPayment[];
    methods: string[];
    canAdd: boolean;
}) {
    const [adding, setAdding] = useState(false);
    const [refundingId, setRefundingId] = useState<number | null>(null);
    const { data, setData, transform, post, processing, errors, reset, clearErrors } = useForm({
        amount: '',
        payer: client ?? '',
        method: methods[0],
        paid_at: localNow(),
    });

    const title = paymentTypeLabels[type];
    const paid = payments.filter((payment) => !payment.refunded_at).reduce((sum, payment) => sum + Number(payment.amount), 0);
    const retained = payments.reduce((sum, payment) => sum + retainedAmount(payment), 0);
    const remaining = Math.max(Number(limit) - paid, 0);
    const fieldId = `${type}-${reservationId}`;

    const open = () => {
        setData((current) => ({ ...current, paid_at: localNow(), amount: type === 'deposit' ? remaining.toFixed(2) : '' }));
        setAdding(true);
    };

    const close = () => {
        reset();
        clearErrors();
        setAdding(false);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        transform((form) => ({ ...form, type }));
        post(admin.reservations.payments.store(reservationId).url, { preserveScroll: true, onSuccess: close });
    };

    return (
        <section aria-label={title} className="rounded-xl bg-gray-50 p-3 text-sm">
            <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                <h3 className="font-semibold text-gray-900">{title}</h3>
                <p className="text-xs text-gray-500">
                    Wpłacono {formatMoney(paid)} z {formatMoney(Number(limit))}
                    {retained > 0 && ` · zatrzymano ${formatMoney(retained)}`}
                </p>
            </div>

            {payments.length > 0 && (
                <ul className="mt-2 divide-y divide-gray-200">
                    {payments.map((payment) => (
                        <li key={payment.id} className="flex flex-wrap items-center justify-between gap-x-3 py-2">
                            <div className="min-w-0">
                                <p className={cn('font-medium text-gray-900', payment.refunded_at && 'text-gray-500')}>{formatMoney(Number(payment.amount))}</p>
                                <p className="text-xs text-gray-500">
                                    {payment.payer} · {payment.method}
                                </p>
                                <p className="text-xs text-gray-500">{formatDateHour(payment.paid_at)}</p>
                                <PaymentOutcome payment={payment} />
                            </div>
                            {!payment.refunded_at && refundingId !== payment.id && (
                                <button
                                    type="button"
                                    onClick={() => setRefundingId(payment.id)}
                                    aria-label={`Zwróć: ${title.toLowerCase()} ${formatMoney(Number(payment.amount))}`}
                                    className="h-10 shrink-0 px-2 font-medium text-brand-600 hover:text-brand-700"
                                >
                                    Zwróć
                                </button>
                            )}
                            {refundingId === payment.id && <RefundForm reservationId={reservationId} payment={payment} onClose={() => setRefundingId(null)} />}
                        </li>
                    ))}
                </ul>
            )}

            {adding ? (
                <form onSubmit={submit} className="mt-2 flex flex-col gap-3">
                    <FormField label="Kwota (zł)" id={`${fieldId}-amount`} error={errors.amount}>
                        <input
                            id={`${fieldId}-amount`}
                            type="number"
                            inputMode="decimal"
                            required
                            min={0.01}
                            max={remaining}
                            step="0.01"
                            value={data.amount}
                            onChange={(event) => setData('amount', event.target.value)}
                            className={inputClass}
                        />
                    </FormField>
                    <FormField label="Od kogo" id={`${fieldId}-payer`} error={errors.payer}>
                        <input
                            id={`${fieldId}-payer`}
                            required
                            maxLength={150}
                            value={data.payer}
                            onChange={(event) => setData('payer', event.target.value)}
                            className={inputClass}
                        />
                    </FormField>
                    <FormField label="Sposób wpłaty" id={`${fieldId}-method`} error={errors.method}>
                        <Select
                            id={`${fieldId}-method`}
                            value={data.method}
                            onChange={(event) => setData('method', event.target.value)}
                            options={methods.map((method) => ({ value: method, label: method }))}
                        />
                    </FormField>
                    <FormField label="Kiedy" id={`${fieldId}-date`} error={errors.paid_at}>
                        <input
                            id={`${fieldId}-date`}
                            type="datetime-local"
                            required
                            max={localNow()}
                            value={data.paid_at}
                            onChange={(event) => setData('paid_at', event.target.value)}
                            className={inputClass}
                        />
                    </FormField>
                    <FormError message={(errors as Record<string, string>).reservation} />
                    <div className="flex gap-2">
                        <button type="submit" disabled={processing} className={buttonClass('dark', 'sm', 'flex-1')}>
                            Zapisz
                        </button>
                        <button type="button" onClick={close} className={buttonClass('outline', 'sm', 'flex-1 border-gray-300')}>
                            Anuluj
                        </button>
                    </div>
                </form>
            ) : (
                canAdd &&
                remaining > 0 && (
                    <button type="button" onClick={open} className="mt-1 h-10 font-medium text-brand-600 hover:text-brand-700">
                        + Dodaj: {title.toLowerCase()}
                    </button>
                )
            )}
        </section>
    );
}
