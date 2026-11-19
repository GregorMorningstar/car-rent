import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/ui/page-header';
import { EmptyRow, Table, Td, Th } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateHour } from '@/lib/format';

type Entry = {
    id: number;
    user: string | null;
    action: string;
    subject: string | null;
    ip_address: string | null;
    succeeded: boolean;
    created_at: string;
};

export default function AdminAudit({ entries, failed }: { entries: Entry[]; failed: boolean }) {
    return (
        <>
            <Head title="Dziennik działań" />
            <PageHeader title="Dziennik działań" description="Ostatnie zmiany w panelu administratora, bez treści formularzy." />

            {failed ? (
                <p role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Nie udało się wczytać dziennika. Spróbuj ponownie później.
                </p>
            ) : (
                <Table
                    head={
                        <>
                            <Th>Kiedy</Th>
                            <Th>Kto</Th>
                            <Th>Działanie</Th>
                            <Th>Dotyczy</Th>
                            <Th>Wynik</Th>
                            <Th>IP</Th>
                        </>
                    }
                >
                    {entries.length === 0 && <EmptyRow colSpan={6}>Brak wpisów.</EmptyRow>}

                    {entries.map((entry) => (
                        <tr key={entry.id}>
                            <Td className="whitespace-nowrap">{formatDateHour(entry.created_at)}</Td>
                            <Td>{entry.user ?? '–'}</Td>
                            <Td className="font-mono text-xs">{entry.action}</Td>
                            <Td className="font-mono text-xs">{entry.subject ?? '–'}</Td>
                            <Td>{entry.succeeded ? 'Udane' : 'Nieudane'}</Td>
                            <Td className="whitespace-nowrap">{entry.ip_address ?? '–'}</Td>
                        </tr>
                    ))}
                </Table>
            )}
        </>
    );
}

AdminAudit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
