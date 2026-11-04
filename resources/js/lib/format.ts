const dateFormat = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});
const dateTimeFormat = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});
const moneyFormat = new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
});

export const formatDate = (date: string) => dateFormat.format(new Date(date));
export const formatDateTime = (date: string) =>
    dateTimeFormat.format(new Date(date));
export const formatMoney = (amount: number) => moneyFormat.format(amount);

/** Liczba dób między datą odbioru a zwrotu (co najmniej 1). */
export const daysBetween = (from: string, to: string) =>
    Math.max(
        1,
        Math.round((new Date(to).getTime() - new Date(from).getTime()) / 86_400_000),
    );
