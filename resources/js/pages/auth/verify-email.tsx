import { Form, Head, Link, usePage } from '@inertiajs/react';
import { logout } from '@/routes';
import verification from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { auth } = usePage().props;

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
            <Head title="Potwierdź adres e-mail" />

            <div className="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="mb-4 text-xl font-semibold">
                    Potwierdź adres e-mail
                </h1>

                <p className="text-sm text-gray-600">
                    Wysłaliśmy link aktywacyjny na adres{' '}
                    <strong>{auth.user?.email}</strong>. Kliknij go, aby
                    potwierdzić konto.
                </p>

                {status === 'verification-link-sent' && (
                    <p className="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-700">
                        Nowy link został wysłany.
                    </p>
                )}

                <Form
                    {...verification.send.form()}
                    className="mt-6 flex items-center justify-between"
                >
                    {({ processing }) => (
                        <>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-800 disabled:opacity-50"
                            >
                                Wyślij link ponownie
                            </button>
                            <Link
                                href={logout()}
                                as="button"
                                className="text-sm text-gray-600 underline hover:text-gray-900"
                            >
                                Wyloguj
                            </Link>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
