import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import ChatThread from '@/components/chat/chat-thread';
import type { ChatMessage } from '@/components/chat/chat-thread';
import { buttonClass } from '@/components/ui/button';
import { Select } from '@/components/ui/form-field';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type Conversation = {
    id: number;
    client: string;
    preview: string | null;
    unread: number;
    last_message_at: string | null;
};

type Client = { id: number; name: string; email: string };

type ActiveConversation = { id: number; client: string; email: string };

/** Administracja może zacząć rozmowę z klientem, który jeszcze nie napisał. */
function NewConversation({ clients }: { clients: Client[] }) {
    const { errors } = usePage().props;
    const [clientId, setClientId] = useState('');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(admin.messages.open().url, { client_id: clientId }, { onSuccess: () => setClientId('') });
    };

    return (
        <form onSubmit={submit} className="border-b border-gray-100 p-3">
            <div className="flex gap-2">
                <div className="min-w-0 flex-1">
                    <Select
                        aria-label="Napisz do klienta"
                        required
                        value={clientId}
                        onChange={(event) => setClientId(event.target.value)}
                        placeholder="Napisz do klienta…"
                        options={clients.map((client) => ({ value: client.id, label: `${client.name} (${client.email})` }))}
                    />
                </div>
                <button type="submit" disabled={clientId === ''} className={buttonClass('dark', 'md', 'px-4')}>
                    Otwórz
                </button>
            </div>
            {errors.client_id && <p className="mt-1 text-xs text-red-600">{errors.client_id}</p>}
        </form>
    );
}

export default function AdminMessages({
    conversations,
    clients,
    active,
    messages,
    failed,
}: {
    conversations: Conversation[];
    clients: Client[];
    active: ActiveConversation | null;
    messages: ChatMessage[];
    failed: boolean;
}) {
    return (
        <>
            <Head title="Wiadomości" />
            <PageHeader title="Wiadomości" description="Rozmowy z klientami." />

            <div className="grid items-start gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
                <nav aria-label="Rozmowy" className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                    <NewConversation clients={clients} />

                    {conversations.length === 0 && <p className="p-5 text-sm text-gray-500">Brak rozmów.</p>}

                    <ul className="divide-y divide-gray-100">
                        {conversations.map((conversation) => (
                            <li key={conversation.id}>
                                <Link
                                    href={admin.messages.index({ query: { conversation: conversation.id } })}
                                    aria-current={active?.id === conversation.id ? 'true' : undefined}
                                    className={cn('block p-4 hover:bg-gray-50', active?.id === conversation.id && 'bg-gray-100')}
                                >
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="truncate font-medium text-gray-900">{conversation.client}</span>
                                        {conversation.unread > 0 && (
                                            <span className="rounded-full bg-brand-600 px-2 text-xs font-semibold text-white tabular-nums">
                                                {conversation.unread}
                                            </span>
                                        )}
                                    </span>
                                    {conversation.preview && (
                                        <span className="mt-0.5 block truncate text-sm text-gray-500">{conversation.preview}</span>
                                    )}
                                    {conversation.last_message_at && (
                                        <span className="mt-0.5 block text-xs text-gray-400">
                                            {formatDateTime(conversation.last_message_at)}
                                        </span>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                {active ? (
                    <section aria-label={`Rozmowa: ${active.client}`} className="min-w-0">
                        <p className="mb-2 text-sm text-gray-600">
                            <span className="font-semibold text-gray-900">{active.client}</span> · {active.email}
                        </p>
                        <ChatThread
                            key={active.id}
                            messages={messages}
                            failed={failed}
                            sendUrl={admin.messages.store(active.id).url}
                            emptyText="Brak wiadomości."
                        />
                    </section>
                ) : (
                    <p className="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center text-gray-500">
                        {failed ? 'Nie udało się wczytać rozmów.' : 'Wybierz rozmowę z listy.'}
                    </p>
                )}
            </div>
        </>
    );
}

AdminMessages.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
