import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import { buttonClass } from '@/components/ui/button';
import { inputClass } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Wiadomość z ChatService. */
export type ChatMessage = {
    id: number;
    body: string;
    mine: boolean;
    from_client: boolean;
    read: boolean;
    created_at: string | null;
};

const MAX_LENGTH = 2000;

/** Wątek rozmowy z polem odpowiedzi. Enter wysyła, Shift + Enter robi nową linię. */
export default function ChatThread({
    messages,
    sendUrl,
    failed,
    emptyText,
}: {
    messages: ChatMessage[];
    sendUrl: string;
    failed: boolean;
    emptyText: string;
}) {
    const list = useRef<HTMLDivElement>(null);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ body: '' });

    useEffect(() => {
        list.current?.scrollTo({ top: list.current.scrollHeight });
    }, [messages.length]);

    const send = () => {
        if (processing || data.body.trim() === '') {
            return;
        }

        post(sendUrl, { preserveScroll: true, onSuccess: () => reset() });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        send();
    };

    const onKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            send();
        }
    };

    return (
        <div className="flex h-[32rem] max-h-[75dvh] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <div ref={list} aria-live="polite" className="flex flex-1 flex-col gap-3 overflow-y-auto p-4 sm:p-5">
                {failed && (
                    <p role="alert" className="m-auto text-center text-sm text-red-600">
                        Nie udało się wczytać rozmowy. Spróbuj ponownie później.
                    </p>
                )}

                {!failed && messages.length === 0 && <p className="m-auto text-center text-sm text-gray-500">{emptyText}</p>}

                {messages.map((message) => (
                    <div key={message.id} className={cn('flex max-w-[85%] flex-col gap-1', message.mine ? 'self-end items-end' : 'self-start')}>
                        <p
                            className={cn(
                                'rounded-2xl px-4 py-2 text-sm wrap-anywhere whitespace-pre-line',
                                message.mine ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-900',
                            )}
                        >
                            {message.body}
                        </p>
                        {message.created_at && (
                            <p className="text-xs text-gray-500">
                                {formatDateTime(message.created_at)}
                                {message.mine && message.read && ' · przeczytano'}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            <form onSubmit={submit} className="flex items-end gap-2 border-t border-gray-100 p-3">
                <div className="flex-1">
                    <label htmlFor="chat-body" className="sr-only">
                        Twoja wiadomość
                    </label>
                    <textarea
                        id="chat-body"
                        rows={2}
                        maxLength={MAX_LENGTH}
                        value={data.body}
                        placeholder="Napisz wiadomość…"
                        onChange={(event) => {
                            setData('body', event.target.value);
                            clearErrors();
                        }}
                        onKeyDown={onKeyDown}
                        className={cn(inputClass, 'h-auto resize-none py-2.5')}
                    />
                    {errors.body && <p className="mt-1 text-xs text-red-600">{errors.body}</p>}
                </div>
                <button type="submit" disabled={processing || data.body.trim() === ''} className={buttonClass('dark', 'md')}>
                    Wyślij
                </button>
            </form>
        </div>
    );
}
