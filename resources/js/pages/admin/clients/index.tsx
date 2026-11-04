import { Head } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import ClientsTable from '@/components/admin/clients/clients-table';
import { clients } from '@/components/admin/data/clients';
import { reservations } from '@/components/admin/data/reservations';
import PageHeader from '@/components/ui/page-header';
import Pagination from '@/components/ui/pagination';
import SearchInput from '@/components/ui/search-input';
import { usePagination } from '@/hooks/use-pagination';
import AdminLayout from '@/layouts/admin-layout';

const rows = clients.map((client) => ({
    ...client,
    reservationsCount: reservations.filter((reservation) => reservation.user_id === client.id).length,
}));

export default function AdminClients() {
    const [query, setQuery] = useState('');
    const normalized = query.trim().toLowerCase();

    const filtered = rows.filter(
        (client) =>
            !normalized ||
            [client.name, client.email, client.profile?.phone ?? '', client.profile?.city ?? ''].some((value) =>
                value.toLowerCase().includes(normalized),
            ),
    );
    const pagination = usePagination(filtered, 10);

    return (
        <>
            <Head title="Dane klientów" />
            <PageHeader title="Dane klientów" description="Klienci wypożyczalni i ich dane kontaktowe." />

            <SearchInput
                value={query}
                onChange={(value) => {
                    setQuery(value);
                    pagination.setPage(1);
                }}
                placeholder="Szukaj: imię, e-mail, telefon, miejscowość…"
                className="mb-4"
            />

            <ClientsTable clients={pagination.items} />
            <Pagination {...pagination} onChange={pagination.setPage} />
        </>
    );
}

AdminClients.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
