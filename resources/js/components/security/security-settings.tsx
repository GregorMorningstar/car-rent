import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { buttonClass } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import { formatDate, formatDateHour } from '@/lib/format';
import account from '@/routes/account';
import security from '@/routes/security';

export type TrustedDevice = { id: number; name: string; last_used_at: string; expires_at: string; current: boolean };

export type SecurityProps = {
    enabled: boolean;
    email: string;
    trustDays: number;
    devices: TrustedDevice[];
    codeSent: boolean;
    canDeleteAccount: boolean;
};

function EnableForm({ codeSent, email }: { codeSent: boolean; email: string }) {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const send = () => router.post(security.code().url, {}, { preserveScroll: true });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(security.enable().url, { preserveScroll: true });
    };

    if (!codeSent) {
        return (
            <div className="mt-4">
                <button type="button" onClick={send} className={buttonClass('dark')}>
                    Tak, dodaj zabezpieczenie
                </button>
                {errors.code && <p className="mt-2 text-red-600">{errors.code}</p>}
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="mt-4 flex max-w-xs flex-col gap-3">
            <p className="text-gray-600">
                Wysłaliśmy kod na adres <span className="font-medium">{email}</span>.
            </p>
            <FormField label="Kod z e-maila" id="code" error={errors.code}>
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
    const { data, setData, delete: destroy, processing, errors, reset } = useForm({ password: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        destroy(security.disable().url, { preserveScroll: true, onFinish: () => reset() });
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
            <button type="submit" disabled={processing} className={`${buttonClass('outline', 'md', 'border-gray-300')} self-start`}>
                Wyłącz zabezpieczenie
            </button>
        </form>
    );
}

function Devices({ devices, trustDays }: { devices: TrustedDevice[]; trustDays: number }) {
    const remove = (url: string) => router.delete(url, { preserveScroll: true });

    return (
        <section aria-label="Zaufane urządzenia" className="mt-4 rounded-2xl border border-gray-200 bg-white p-5 text-sm sm:p-6">
            <h2 className="font-semibold text-gray-900">Zaufane urządzenia</h2>
            <p className="mt-1 text-gray-600">
                Na tych urządzeniach logowanie nie wymaga kodu z e-maila przez {trustDays} dni. Urządzenie dodasz, zaznaczając tę opcję przy wpisywaniu kodu.
            </p>

            {devices.length === 0 ? (
                <p className="mt-3 text-gray-500">Brak zaufanych urządzeń.</p>
            ) : (
                <>
                    <ul className="mt-3 divide-y divide-gray-100">
                        {devices.map((device) => (
                            <li key={device.id} className="flex flex-wrap items-center justify-between gap-3 py-2">
                                <div className="min-w-0">
                                    <p className="font-medium text-gray-900">
                                        {device.name}
                                        {device.current && ' (to urządzenie)'}
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        Ostatnio: {formatDateHour(device.last_used_at)}, zaufane do {formatDate(device.expires_at)}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => remove(security.devices.destroy(device.id).url)}
                                    aria-label={`Usuń ${device.name}`}
                                    className="h-10 px-2 font-medium text-brand-600 hover:text-brand-700"
                                >
                                    Usuń
                                </button>
                            </li>
                        ))}
                    </ul>
                    <button type="button" onClick={() => remove(security.devices.clear().url)} className={`${buttonClass('outline', 'sm', 'border-gray-300')} mt-3`}>
                        Usuń wszystkie
                    </button>
                </>
            )}
        </section>
    );
}

function DeleteAccount() {
    const { data, setData, delete: destroy, processing, errors, reset } = useForm({ password: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (window.confirm('Usunąć konto? Tej operacji nie można cofnąć.')) {
            destroy(account.destroy().url, { preserveScroll: true, onError: () => reset() });
        }
    };

    return (
        <section aria-label="Usuwanie konta" className="mt-4 rounded-2xl border border-gray-200 bg-white p-5 text-sm sm:p-6">
            <h2 className="font-semibold text-gray-900">Usuń konto</h2>
            <p className="mt-1 text-gray-600">
                Usuniemy Twoje dane kontaktowe, wiadomości i dane kierowcy, a logowanie na to konto przestanie działać. Historia rezerwacji i wpłat zostanie bez powiązania z Tobą, bo jest potrzebna do rozliczeń.
            </p>
            <form onSubmit={submit} className="mt-4 flex max-w-xs flex-col gap-3">
                <FormField label="Hasło" id="delete_password" error={errors.password}>
                    <input
                        id="delete_password"
                        type="password"
                        required
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        className={inputClass}
                    />
                </FormField>
                <button type="submit" disabled={processing} className={`${buttonClass('outline', 'md', 'border-gray-300')} self-start`}>
                    Usuń konto
                </button>
            </form>
        </section>
    );
}

/** Ustawienia wspólne dla klienta i administratora. */
export default function SecuritySettings({ enabled, email, trustDays, devices, codeSent, canDeleteAccount }: SecurityProps) {
    const { flash } = usePage().props;

    return (
        <>
            <Head title="Bezpieczeństwo" />
            <PageHeader title="Bezpieczeństwo" description="Logowanie dwuetapowe i zaufane urządzenia." />

            <section aria-label="Logowanie dwuetapowe" className="rounded-2xl border border-gray-200 bg-white p-5 text-sm sm:p-6">
                <h2 className="font-semibold text-gray-900">Logowanie dwuetapowe</h2>

                {flash && (
                    <p role="status" className="mt-2 text-emerald-700">
                        {flash}
                    </p>
                )}

                {enabled ? (
                    <>
                        <p className="mt-2 text-gray-600">Włączone. Po wpisaniu hasła wysyłamy kod na adres {email}.</p>
                        <DisableForm />
                    </>
                ) : (
                    <>
                        <p className="mt-2 text-gray-600">
                            Czy chcesz dodać zabezpieczenie? Po wpisaniu hasła wyślemy kod na adres {email}, więc samo hasło nie wystarczy do wejścia na konto.
                        </p>
                        <EnableForm codeSent={codeSent} email={email} />
                    </>
                )}
            </section>

            {enabled && <Devices devices={devices} trustDays={trustDays} />}
            {canDeleteAccount && <DeleteAccount />}
        </>
    );
}
