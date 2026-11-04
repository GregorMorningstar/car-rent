import { daysBetween } from '@/lib/format';

// Przykładowe dane panelu użytkownika - docelowo przyjdą z backendu jako propsy.

export type RentalStatus = 'active' | 'upcoming' | 'completed' | 'cancelled';

export type Rental = {
    number: string;
    car: string;
    pickup: string;
    dropoff: string;
    from: string;
    to: string;
    total: number;
    status: RentalStatus;
};

export const rentalStatuses: Record<RentalStatus, { label: string; className: string }> = {
    active: { label: 'W trakcie', className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
    upcoming: { label: 'Nadchodzący', className: 'bg-blue-50 text-blue-700 ring-blue-600/20' },
    completed: { label: 'Zakończony', className: 'bg-gray-100 text-gray-700 ring-gray-500/20' },
    cancelled: { label: 'Anulowany', className: 'bg-brand-50 text-brand-700 ring-brand-600/20' },
};

export const rentals: Rental[] = [
    {
        number: 'WYN/2026/0142',
        car: 'Lamborghini Huracán',
        pickup: 'Miś Krosno Popiełuszki',
        dropoff: 'Miś Krosno Popiełuszki',
        from: '2026-11-02',
        to: '2026-11-06',
        total: 1500,
        status: 'active',
    },
    {
        number: 'WYN/2026/0151',
        car: 'Tata Safari',
        pickup: 'Myjnia Czysta Polska Podkarpacka Krosno',
        dropoff: 'Miś Krosno Popiełuszki',
        from: '2026-11-20',
        to: '2026-11-23',
        total: 200,
        status: 'upcoming',
    },
    {
        number: 'WYN/2026/0118',
        car: 'Lamborghini Aventador',
        pickup: 'Miś Krosno Popiełuszki',
        dropoff: 'Miś Krosno Popiełuszki',
        from: '2026-09-12',
        to: '2026-09-15',
        total: 80,
        status: 'completed',
    },
    {
        number: 'WYN/2026/0097',
        car: 'Lamborghini Gallardo',
        pickup: 'Myjnia Czysta Polska Podkarpacka Krosno',
        dropoff: 'Myjnia Czysta Polska Podkarpacka Krosno',
        from: '2026-07-03',
        to: '2026-07-10',
        total: 80,
        status: 'completed',
    },
    {
        number: 'WYN/2026/0071',
        car: 'Lamborghini Titan',
        pickup: 'Miś Krosno Popiełuszki',
        dropoff: 'Myjnia Czysta Polska Podkarpacka Krosno',
        from: '2026-05-18',
        to: '2026-05-19',
        total: 20,
        status: 'cancelled',
    },
    {
        number: 'WYN/2026/0034',
        car: 'Lamborghini Hyperion',
        pickup: 'Miś Krosno Popiełuszki',
        dropoff: 'Miś Krosno Popiełuszki',
        from: '2026-03-01',
        to: '2026-03-05',
        total: 100,
        status: 'completed',
    },
];

export type PaymentStatus = 'paid' | 'pending' | 'refunded';

export type Payment = {
    invoice: string;
    rental: string;
    date: string;
    method: string;
    amount: number;
    status: PaymentStatus;
};

export const paymentStatuses: Record<PaymentStatus, { label: string; className: string }> = {
    paid: { label: 'Opłacona', className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
    pending: { label: 'Oczekuje', className: 'bg-amber-50 text-amber-700 ring-amber-600/20' },
    refunded: { label: 'Zwrócona', className: 'bg-gray-100 text-gray-700 ring-gray-500/20' },
};

export const payments: Payment[] = [
    { invoice: 'FV/11/2026/031', rental: 'WYN/2026/0151', date: '2026-11-01', method: 'BLIK', amount: 200, status: 'pending' },
    { invoice: 'FV/10/2026/087', rental: 'WYN/2026/0142', date: '2026-10-28', method: 'Karta', amount: 1500, status: 'paid' },
    { invoice: 'FV/09/2026/044', rental: 'WYN/2026/0118', date: '2026-09-10', method: 'Przelew', amount: 80, status: 'paid' },
    { invoice: 'FV/07/2026/012', rental: 'WYN/2026/0097', date: '2026-07-01', method: 'BLIK', amount: 80, status: 'paid' },
    { invoice: 'FV/05/2026/058', rental: 'WYN/2026/0071', date: '2026-05-15', method: 'Karta', amount: 20, status: 'refunded' },
    { invoice: 'FV/02/2026/093', rental: 'WYN/2026/0034', date: '2026-02-27', method: 'Przelew', amount: 100, status: 'paid' },
];

export { formatDate, formatMoney } from '@/lib/format';

/** Liczba dni wynajmu (dzień odbioru i zwrotu liczone razem jako doby). */
export const rentalDays = (rental: Rental) => daysBetween(rental.from, rental.to);
