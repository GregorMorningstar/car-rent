import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';
import admin from '@/routes/admin';

function ConfirmForm() {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(admin.security.confirm().url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mt-4 flex max-w-xs flex-col gap-3">
            <FormField label="Kod z aplikacji" id="code" error={errors.code}>
                <input
                    id="code"
                    required
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={6}
                    value={data.code}
                    onChange={(event) => setData('code', event.target.value)}
                    className={inputClass}
                />
            </FormField>
            <button type="submit" disabled={processing} className={`${buttonClass('dark')} self-start`}>
                Włącz
            </button>
        </form>
    );
}

function DisableForm() {
    const { data, setData, delete: destroy, processing, errors, reset } = useForm({ password: '', code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        destroy(admin.security.disable().url, { preserveScroll: true, onFinish: () => reset() });
    };

    return (
        <form onSubmit={submit} className="mt-4 flex max-w-xs flex-col gap-3">
            <FormField label="Hasło" id="password" error={errors.password}>
                <input
                    id="password"
                    type="password"
                    required
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(event) => setData('password', event.target.value)}
                    className={inputClass}
                />
            </FormField>
            <FormField label="Kod z aplikacji lub kod zapasowy" id="disable_code" error={errors.code}>
                <input
                    id="disable_code"
                    required
                    autoComplete="one-time-code"
                    maxLength={20}
                    value={data.code}
                    onChange={(event) => setData('code', event.target.value)}
                    className={inputClass}
                />
            </FormField>
            <button type="submit" disabled={processing} className={`${buttonClass('outline', 'md', 'border-gray-300')} self-start`}>
                Wyłącz logowanie dwuetapowe
            </button>
        </form>
    );
}

export default function AdminSecurity({
    enabled,
    secret,
    otpauth,
    recoveryCodes,
}: {
    enabled: boolean;
    secret: string | null;
    otpauth: string | null;
    recoveryCodes: string[] | null;
}) {
    const { flash } = usePage().props;

    return (
        <>
            <Head title="Bezpieczeństwo" />
            <PageHeader title="Bezpieczeństwo" description="Logowanie dwuetapowe Twojego konta." />

            <section className="rounded-2xl border border-gray-200 bg-white p-5 text-sm sm:p-6">
                <h2 className="font-semibold text-gray-900">Logowanie dwuetapowe</h2>

                {flash && (
                    <p role="status" className="mt-2 text-emerald-700">
                        {flash}
                    </p>
                )}

                {recoveryCodes && (
                    <div role="status" className="mt-4 rounded-xl bg-amber-50 p-4 text-amber-900">
                        <p className="font-medium">Zapisz kody zapasowe. Zobaczysz je tylko teraz, a każdy działa raz.</p>
                        <ul className="mt-2 grid gap-1 font-mono sm:grid-cols-2">
                            {recoveryCodes.map((code) => (
                                <li key={code}>{code}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {enabled && (
                    <>
                        <p className="mt-2 text-gray-600">Włączone. Po haśle logowanie wymaga kodu z aplikacji.</p>
                        <DisableForm />
                    </>
                )}

                {!enabled && !secret && (
                    <>
                        <p className="mt-2 text-gray-600">Wyłączone. Włącz je, żeby przejęcie hasła nie dawało dostępu do panelu.</p>
                        <button
                            type="button"
                            onClick={() => router.post(admin.security.start().url, {}, { preserveScroll: true })}
                            className={`${buttonClass('dark')} mt-4`}
                        >
                            Włącz logowanie dwuetapowe
                        </button>
                    </>
                )}

                {!enabled && secret && (
                    <>
                        <p className="mt-2 text-gray-600">
                            Dodaj konto w aplikacji uwierzytelniającej, wpisując poniższy klucz, a potem podaj wygenerowany kod.
                        </p>
                        <p className="mt-3 font-mono text-base break-all text-gray-900">{secret}</p>
                        {otpauth && (
                            <a href={otpauth} className="mt-2 inline-block font-medium text-brand-600 hover:text-brand-700">
                                Otwórz w aplikacji na tym urządzeniu
                            </a>
                        )}
                        <ConfirmForm />
                    </>
                )}
            </section>
        </>
    );
}

AdminSecurity.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
