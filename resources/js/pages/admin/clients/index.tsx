import { Head } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import ClientsTable from '@/components/admin/clients/clients-table';
import type { Client } from '@/components/admin/data/clients';
import PageHeader from '@/components/ui/page-header';
import Pagination from '@/components/ui/pagination';
import SearchInput from '@/components/ui/search-input';
import { usePagination } from '@/hooks/use-pagination';
import AdminLayout from '@/layouts/admin-layout';

export default function AdminClients({ clients, failed }: { clients: Client[]; failed: boolean }) {
    const [query, setQuery] = useState('');
    const normalized = query.trim().toLowerCase();

    const filtered = clients.filter(
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

            {failed ? (
                <p role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Nie udało się wczytać klientów. Spróbuj ponownie później.
                </p>
            ) : (
                <>
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
            )}
        </>
    );
}

AdminClients.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
