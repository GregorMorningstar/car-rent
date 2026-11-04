import { Form, Head, Link } from '@inertiajs/react';
import type { InputHTMLAttributes } from 'react';
import { login } from '@/routes';
import registerRoutes from '@/routes/register';

type FieldProps = InputHTMLAttributes<HTMLInputElement> & {
    name: string;
    label: string;
    error?: string;
};

function Field({ name, label, error, className, ...props }: FieldProps) {
    return (
        <div className={className}>
            <label htmlFor={name} className="mb-1 block text-sm font-medium">
                {label}
            </label>
            <input
                id={name}
                name={name}
                className="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-gray-500 focus:outline-none"
                {...props}
            />
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

export default function Register() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
            <Head title="Rejestracja" />

            <div className="w-full max-w-lg rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="mb-6 text-xl font-semibold">Załóż konto</h1>

                <Form
                    {...registerRoutes.store.form()}
                    resetOnSuccess={['password', 'password_confirmation']}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <fieldset className="space-y-4">
                                <legend className="mb-2 text-sm font-semibold text-gray-500 uppercase">
                                    Dane konta
                                </legend>
                                <Field
                                    name="name"
                                    label="Imię i nazwisko"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    error={errors.name}
                                />
                                <Field
                                    name="email"
                                    type="email"
                                    label="E-mail"
                                    required
                                    autoComplete="email"
                                    error={errors.email}
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        name="password"
                                        type="password"
                                        label="Hasło"
                                        required
                                        autoComplete="new-password"
                                        error={errors.password}
                                    />
                                    <Field
                                        name="password_confirmation"
                                        type="password"
                                        label="Powtórz hasło"
                                        required
                                        autoComplete="new-password"
                                        error={errors.password_confirmation}
                                    />
                                </div>
                            </fieldset>

                            <fieldset className="space-y-4">
                                <legend className="mb-2 text-sm font-semibold text-gray-500 uppercase">
                                    Kontakt i adres
                                </legend>
                                <Field
                                    name="phone"
                                    type="tel"
                                    label="Telefon"
                                    placeholder="+48 600 100 200"
                                    required
                                    autoComplete="tel"
                                    error={errors.phone}
                                />
                                <Field
                                    name="street"
                                    label="Ulica"
                                    required
                                    autoComplete="address-line1"
                                    error={errors.street}
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        name="building_number"
                                        label="Nr budynku"
                                        required
                                        error={errors.building_number}
                                    />
                                    <Field
                                        name="apartment_number"
                                        label="Nr lokalu (opcjonalnie)"
                                        error={errors.apartment_number}
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <Field
                                        name="postal_code"
                                        label="Kod pocztowy"
                                        placeholder="00-000"
                                        required
                                        autoComplete="postal-code"
                                        error={errors.postal_code}
                                    />
                                    <Field
                                        name="city"
                                        label="Miejscowość"
                                        required
                                        autoComplete="address-level2"
                                        error={errors.city}
                                        className="sm:col-span-2"
                                    />
                                </div>
                            </fieldset>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:opacity-50"
                            >
                                {processing
                                    ? 'Rejestracja...'
                                    : 'Zarejestruj się'}
                            </button>

                            <p className="text-center text-sm text-gray-600">
                                Masz już konto?{' '}
                                <Link
                                    href={login()}
                                    className="font-medium text-gray-900 underline"
                                >
                                    Zaloguj się
                                </Link>
                            </p>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
