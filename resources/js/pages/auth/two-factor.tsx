import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { openCookieSettings } from '@/lib/cookie-consent';
import { login as loginPage } from '@/routes';
import login from '@/routes/login';

export default function TwoFactor({ email, minutes, trustDays }: { email: string; minutes: number; trustDays: number }) {
    const { flash, cookieConsent } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ code: '', trust: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(login.code.verify.url());
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
            <Head title="Kod logowania" />

            <div className="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="mb-2 text-xl font-semibold">Kod logowania</h1>
                <p className="mb-6 text-sm text-gray-600">
                    Wysłaliśmy kod na adres <span className="font-medium break-words">{email}</span>. Jest ważny przez {minutes} minut.
                </p>

                {flash && (
                    <p role="status" className="mb-4 text-sm text-emerald-700">
                        {flash}
                    </p>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label htmlFor="code" className="mb-1 block text-sm font-medium">
                            Kod
                        </label>
                        <input
                            id="code"
                            required
                            autoFocus
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={data.code}
                            onChange={(event) => setData('code', event.target.value)}
                            className="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-gray-500 focus:outline-none"
                        />
                        {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                    </div>

                    {cookieConsent?.functional ? (
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.trust}
                                onChange={(event) => setData('trust', event.target.checked)}
                                className="mt-0.5"
                            />
                            Dodaj to urządzenie do zaufanych na {trustDays} dni. Nie zaznaczaj na cudzym komputerze.
                        </label>
                    ) : (
                        <p className="text-sm text-gray-600">
                            Zaufane urządzenia wymagają cookies funkcjonalnych.{' '}
                            <button type="button" onClick={openCookieSettings} className="font-medium text-gray-900 underline">
                                Ustawienia cookies
                            </button>
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:opacity-50"
                    >
                        Potwierdź
                    </button>

                    <p className="flex justify-between gap-3 text-sm text-gray-600">
                        <button type="button" onClick={() => router.post(login.code.resend.url())} className="font-medium text-gray-900 underline">
                            Wyślij kod ponownie
                        </button>
                        <Link href={loginPage()} className="font-medium text-gray-900 underline">
                            Wróć do logowania
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    );
}
