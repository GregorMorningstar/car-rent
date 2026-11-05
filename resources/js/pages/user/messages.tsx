import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ChatThread from '@/components/chat/chat-thread';
import type { ChatMessage } from '@/components/chat/chat-thread';
import PageHeader from '@/components/ui/page-header';
import UserLayout from '@/layouts/user-layout';
import userRoutes from '@/routes/user';

export default function UserMessages({ messages, failed }: { messages: ChatMessage[]; failed: boolean }) {
    return (
        <>
            <Head title="Wiadomości" />
            <PageHeader title="Wiadomości" description="Rozmowa z obsługą wypożyczalni." />
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
