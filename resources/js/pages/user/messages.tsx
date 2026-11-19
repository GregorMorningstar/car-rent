import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ChatThread from '@/components/chat/chat-thread';
import type { ChatMessage } from '@/components/chat/chat-thread';
import PageHeader from '@/components/ui/page-header';
import UserLayout from '@/layouts/user-layout';
import userRoutes from '@/routes/user';

export default function UserMessages({ messages, failed, forwarded }: { messages: ChatMessage[]; failed: boolean; forwarded: boolean }) {
    return (
        <>
            <Head title="Wiadomości" />
            <PageHeader title="Wiadomości" description="Rozmowa z obsługą wypożyczalni." />
            {forwarded && (
                <p className="mb-4 rounded-2xl bg-gray-50 p-4 text-sm text-gray-700">
                    Twoje wiadomości i imię trafiają do obsługi także przez Facebook Messenger, czyli do firmy Meta. Nie wysyłaj tu danych, których nie chcesz tam przekazać.
                </p>
            )}
            <ChatThread
                messages={messages}
                failed={failed}
                sendUrl={userRoutes.messages.store().url}
                emptyText="Napisz do nas, jeśli masz pytanie o rezerwację lub samochód."
            />
        </>
    );
}

UserMessages.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
