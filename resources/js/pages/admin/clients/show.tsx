import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { findClient } from '@/components/admin/data/clients';
import { allReservations } from '@/components/admin/data/reservations';
import ReservationsTable from '@/components/admin/reservations/reservations-table';
import { buttonClass } from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import { formatDate, formatMoney } from '@/lib/format';
import AdminLayout from '@/layouts/admin-layout';
import admin from '@/routes/admin';

function InfoCard({ title, rows }: { title: string; rows: [string, ReactNode][] }) {
    return (
        <section className="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 className="text-sm font-semibold text-gray-500">{title}</h2>
            <dl className="mt-3 flex flex-col gap-2 text-sm">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex items-baseline justify-between gap-4">
                        <dt className="shrink-0 text-gray-500">{label}</dt>
                        <dd className="min-w-0 text-right break-words text-gray-900">{value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export default function AdminClientShow({ id }: { id: number }) {
    const client = findClient(id);

    if (!client) {
        return (
            <>
                <Head title="Nie znaleziono klienta" />
                <PageHeader title="Nie znaleziono klienta" description={`Brak klienta o ID ${id}.`} />
                <Link href={admin.clients.index()} className={buttonClass('dark')}>
                    Wróć do listy
                </Link>
            </>
        );
    }

    const reservations = allReservations().filter((reservation) => reservation.user_id === client.id);
    const spent = reservations
        .filter((reservation) => reservation.state === 'completed' || reservation.state === 'confirmed')
        .reduce((sum, reservation) => sum + reservation.total_price, 0);
    const profile = client.profile;

    return (
        <>
            <Head title={client.name} />

            <PageHeader
                title={client.name}
                description={`Klient od ${formatDate(client.created_at)}`}
                action={
                    <Link href={admin.clients.index()} className={buttonClass('outline', 'md', 'border-gray-300')}>
                        ← Wszyscy klienci
                    </Link>
                }
            />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <InfoCard
                    title="Kontakt"
                    rows={[
                        ['E-mail', <a key="email" href={`mailto:${client.email}`} className="hover:text-brand-600">{client.email}</a>],
                        ['Telefon', profile ? <a key="tel" href={`tel:${profile.phone.replaceAll(' ', '')}`} className="hover:text-brand-600">{profile.phone}</a> : '–'],
                        ['Weryfikacja', client.email_verified_at ? 'Potwierdzony' : 'Niepotwierdzony'],
                    ]}
                />
                <InfoCard
                    title="Adres"
                    rows={
                        profile
                            ? [
                                  ['Ulica', `${profile.street} ${profile.building_number}${profile.apartment_number ? `/${profile.apartment_number}` : ''}`],
                                  ['Kod pocztowy', profile.postal_code],
                                  ['Miejscowość', profile.city],
                              ]
                            : [['Adres', 'Klient nie uzupełnił profilu']]
                    }
                />
                <InfoCard
                    title="Podsumowanie"
                    rows={[
                        ['Rezerwacje', reservations.length],
                        ['Zrealizowane', reservations.filter((reservation) => reservation.state === 'completed').length],
                        ['Wartość', formatMoney(spent)],
                    ]}
                />
            </div>

            <section className="mt-8">
                <h2 className="mb-4 text-lg font-semibold text-gray-900">Rezerwacje klienta</h2>
                <ReservationsTable reservations={reservations} hideClient />
            </section>
        </>
    );
}

AdminClientShow.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
