import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { Client } from '@/components/admin/data/clients';
import ReservationsTable from '@/components/admin/reservations/reservations-table';
import type { AdminReservation } from '@/components/admin/reservations/types';
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

export default function AdminClientShow({ client, reservations, failed }: { client: Client; reservations: AdminReservation[]; failed: boolean }) {
    const spent = reservations
        .filter((reservation) => reservation.state === 'completed' || reservation.state === 'confirmed')
        .reduce((sum, reservation) => sum + Number(reservation.total_price), 0);
    const profile = client.profile;

    return (
        <>
            <Head title={client.name} />

            <PageHeader
                title={client.name}
                description={client.created_at ? `Klient od ${formatDate(client.created_at)}` : undefined}
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
                {failed ? (
                    <p role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Nie udało się wczytać rezerwacji klienta.
                    </p>
                ) : (
                    <ReservationsTable reservations={reservations} hideClient />
                )}
            </section>
        </>
    );
}

AdminClientShow.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
