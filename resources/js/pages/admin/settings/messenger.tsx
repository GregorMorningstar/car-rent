import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';
import admin from '@/routes/admin';

type Settings = { enabled: boolean; recipient_id: string; has_token: boolean };

export default function MessengerSettings({ settings }: { settings: Settings }) {
    const { flash, errors: pageErrors } = usePage().props;
    const { data, setData, put, processing, errors, reset } = useForm({
        enabled: settings.enabled,
        page_token: '',
        recipient_id: settings.recipient_id,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(admin.settings.messenger.update().url, { preserveScroll: true, onSuccess: () => reset('page_token') });
    };

    return (
        <>
            <Head title="Ustawienia Messengera" />
            <PageHeader
                title="Ustawienia Messengera"
                description="Wiadomości klientów z komunikatora będą przekazywane na Messengera."
            />

            <form onSubmit={submit} className="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                <div className="flex flex-col gap-4">
                    <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-medium text-gray-700">
                        <input
                            type="checkbox"
                            checked={data.enabled}
                            onChange={(event) => setData('enabled', event.target.checked)}
                            className="size-5 accent-brand-600"
                        />
                        Przekazuj wiadomości klientów na Messengera
                    </label>

                    <FormField
                        label="Token dostępu strony"
                        id="page_token"
                        error={errors.page_token}
                        hint={
                            settings.has_token
                                ? 'Token jest zapisany. Zostaw puste, żeby go nie zmieniać.'
                                : 'Page Access Token z aplikacji w Meta for Developers.'
                        }
                    >
                        <input
                            id="page_token"
                            type="password"
                            autoComplete="off"
                            value={data.page_token}
                            onChange={(event) => setData('page_token', event.target.value)}
                            className={inputClass}
                        />
                    </FormField>

                    <FormField
                        label="Identyfikator odbiorcy (PSID)"
                        id="recipient_id"
                        error={errors.recipient_id}
                        hint="Konto, które dostaje wiadomości. Musi wcześniej napisać do Twojej strony na Facebooku."
                    >
                        <input
                            id="recipient_id"
                            inputMode="numeric"
                            value={data.recipient_id}
                            onChange={(event) => setData('recipient_id', event.target.value)}
                            className={inputClass}
                        />
                    </FormField>
                </div>

                {flash && (
                    <p role="status" className="mt-4 text-sm text-emerald-700">
                        {flash}
                    </p>
                )}
                {pageErrors.test && (
                    <p role="alert" className="mt-4 text-sm text-red-600">
                        {pageErrors.test}
                    </p>
                )}

                <div className="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={() => router.post(admin.settings.messenger.test().url, {}, { preserveScroll: true })}
                        className={buttonClass('outline', 'md', 'border-gray-300')}
                    >
                        Wyślij test
                    </button>
                    <button type="submit" disabled={processing} className={buttonClass('dark')}>
                        Zapisz
                    </button>
                </div>
            </form>
        </>
    );
}

MessengerSettings.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
