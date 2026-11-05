const dateFormat = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});
// Sama data to dzień kalendarzowy, a nie chwila w czasie - nie może przesuwać się ze strefą.
const calendarDateFormat = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});
const dateTimeFormat = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});
const dateHourFormat = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});
const moneyFormat = new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
});

const isCalendarDate = (date: string) => /^\d{4}-\d{2}-\d{2}$/.test(date);

/** Serwer wysyła czas w UTC, a Intl pokazuje go w strefie przeglądarki. */
export const formatDate = (date: string) =>
    (isCalendarDate(date) ? calendarDateFormat : dateFormat).format(new Date(date));
export const formatDateTime = (date: string) =>
    dateTimeFormat.format(new Date(date));

/** Dzisiejsza data w strefie przeglądarki, w formacie pola typu date. */
export const localToday = () => {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 10);
};
/** Pełna data z godziną - np. odbiór i zwrot auta. */
export const formatDateHour = (date: string) => dateHourFormat.format(new Date(date));
export const formatMoney = (amount: number) => moneyFormat.format(amount);

/** Liczba dób między datą odbioru a zwrotu (co najmniej 1). */
export const daysBetween = (from: string, to: string) =>
    Math.max(
        1,
        Math.round((new Date(to).getTime() - new Date(from).getTime()) / 86_400_000),
    );
