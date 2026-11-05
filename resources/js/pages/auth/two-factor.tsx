import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import login from '@/routes/login';

export default function TwoFactor() {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(login.code.verify.url());
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
            <Head title="Kod logowania" />

            <div className="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="mb-2 text-xl font-semibold">Kod logowania</h1>
                <p className="mb-6 text-sm text-gray-600">Wpisz kod z aplikacji uwierzytelniającej albo kod zapasowy.</p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label htmlFor="code" className="mb-1 block text-sm font-medium">
                            Kod
                        </label>
                        <input
                            id="code"
                            required
                            autoFocus
                            autoComplete="one-time-code"
                            maxLength={20}
                            value={data.code}
                            onChange={(event) => setData('code', event.target.value)}
                            className="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-gray-500 focus:outline-none"
                        />
                        {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                    </div>

                    <button type="submit" disabled={processing} className="w-full rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:opacity-50">
                        Potwierdź
                    </button>

                    <p className="text-center text-sm text-gray-600">
                        <Link href={login.url()} className="font-medium text-gray-900 underline">
                            Wróć do logowania
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    );
}
