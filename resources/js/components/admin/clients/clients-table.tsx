import { Link } from '@inertiajs/react';
import StatusBadge from '@/components/ui/status-badge';
import { EmptyRow, Table, Td, Th } from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { openOnClick } from '@/lib/open-on-click';
import admin from '@/routes/admin';
import type { Client } from '../data/clients';

export default function ClientsTable({ clients }: { clients: Client[] }) {
    return (
        <Table
            head={
                <>
                    <Th>Klient</Th>
                    <Th>Telefon</Th>
                    <Th>Miejscowość</Th>
                    <Th>Konto</Th>
                    <Th className="text-right">Rezerwacje</Th>
                    <Th>Od</Th>
                    <Th>
                        <span className="sr-only">Akcje</span>
                    </Th>
                </>
            }
        >
            {clients.length === 0 && <EmptyRow colSpan={7}>Brak klientów.</EmptyRow>}

            {clients.map((client) => (
                <tr key={client.id} onClick={openOnClick(admin.clients.show(client.id).url)} className="cursor-pointer hover:bg-gray-50">
                    <Td>
                        <Link
                            href={admin.clients.show(client.id)}
                            className="font-medium whitespace-nowrap text-gray-900 hover:text-brand-600"
                        >
                            {client.name}
                        </Link>
                        <p className="text-xs text-gray-500">{client.email}</p>
                    </Td>
                    <Td className="whitespace-nowrap">{client.profile?.phone ?? '–'}</Td>
                    <Td className="whitespace-nowrap">{client.profile?.city ?? '–'}</Td>
                    <Td>
                        {client.email_verified_at ? (
                            <StatusBadge label="Potwierdzony" className="bg-emerald-50 text-emerald-700 ring-emerald-600/20" />
                        ) : (
                            <StatusBadge label="Niepotwierdzony" className="bg-amber-50 text-amber-700 ring-amber-600/20" />
                        )}
                    </Td>
                    <Td className="text-right tabular-nums">{client.reservationsCount}</Td>
                    <Td className="whitespace-nowrap">{client.created_at ? formatDate(client.created_at) : '–'}</Td>
                    <Td className="text-right">
                        <Link
                            href={admin.clients.show(client.id)}
                            className="font-medium whitespace-nowrap text-brand-600 hover:text-brand-700"
                        >
                            Szczegóły
                        </Link>
                    </Td>
                </tr>
            ))}
        </Table>
    );
}
