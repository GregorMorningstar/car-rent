import { Form, Head, Link, usePage } from '@inertiajs/react';
import { register } from '@/routes';
import login from '@/routes/login';
import password from '@/routes/password';

export default function Login() {
    const { flash } = usePage().props;

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
            <Head title="Logowanie" />

            <div className="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="mb-6 text-xl font-semibold">Zaloguj się</h1>

                {flash && (
                    <p role="status" className="mb-4 text-sm text-emerald-700">
                        {flash}
                    </p>
                )}

                <Form
                    {...login.store.form()}
                    resetOnSuccess={['password']}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor="email"
                                    className="mb-1 block text-sm font-medium"
                                >
                                    E-mail
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-gray-500 focus:outline-none"
                                />
                                {errors.email && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="password"
                                    className="mb-1 block text-sm font-medium"
                                >
                                    Hasło
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-gray-500 focus:outline-none"
                                />
                                {errors.password && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="remember" />
                                Zapamiętaj mnie
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:opacity-50"
                            >
                                {processing ? 'Logowanie...' : 'Zaloguj'}
                            </button>

                            <p className="text-center text-sm text-gray-600">
                                <Link href={password.request()} className="font-medium text-gray-900 underline">
                                    Nie pamiętasz hasła?
                                </Link>
                            </p>

                            <p className="text-center text-sm text-gray-600">
                                Nie masz konta?{' '}
                                <Link
                                    href={register()}
                                    className="font-medium text-gray-900 underline"
                                >
                                    Zarejestruj się
                                </Link>
                            </p>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
