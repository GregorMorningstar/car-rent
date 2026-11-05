import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { login } from '@/routes';
import password from '@/routes/password';

export default function ForgotPassword() {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(password.email.url());
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
            <Head title="Nie pamiętam hasła" />

            <div className="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="mb-2 text-xl font-semibold">Nie pamiętam hasła</h1>
                <p className="mb-6 text-sm text-gray-600">Wyślemy Ci link do ustawienia nowego hasła.</p>

                {flash && (
                    <p role="status" className="mb-4 text-sm text-emerald-700">
                        {flash}
                    </p>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label htmlFor="email" className="mb-1 block text-sm font-medium">
                            E-mail
                        </label>
                        <input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            autoComplete="email"
                            value={data.email}
                            onChange={(event) => setData('email', event.target.value)}
                            className="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-gray-500 focus:outline-none"
                        />
                        {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                    </div>

                    <button type="submit" disabled={processing} className="w-full rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:opacity-50">
                        Wyślij link
                    </button>

                    <p className="text-center text-sm text-gray-600">
                        <Link href={login()} className="font-medium text-gray-900 underline">
                            Wróć do logowania
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    );
}
