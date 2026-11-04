import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, InputHTMLAttributes, ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import UserLayout from '@/layouts/user-layout';

function Card({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    const [notice, setNotice] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setNotice(true);
    };

    return (
        <form
            onSubmit={submit}
            className="rounded-2xl border border-gray-200 bg-white"
        >
            <div className="border-b border-gray-100 p-5 sm:p-6">
                <h2 className="font-semibold text-gray-900">{title}</h2>
                <p className="mt-1 text-sm text-gray-500">{description}</p>
            </div>

            <div className="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">{children}</div>

            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-end sm:p-6">
                {notice && (
                    <p role="status" className="text-sm text-gray-500 sm:mr-auto">
                        Podgląd – zapisywanie zostanie podłączone do backendu.
                    </p>
                )}
                <button type="submit" className={buttonClass('dark', 'md', 'w-full sm:w-auto')}>
                    Zapisz zmiany
                </button>
            </div>
        </form>
    );
}

function Field({
    label,
    id,
    wide,
    ...props
}: { label: string; id: string; wide?: boolean } & InputHTMLAttributes<HTMLInputElement>) {
    return (
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-gray-700">
                {label}
            </label>
            <input id={id} name={id} className={inputClass} {...props} />
        </div>
    );
}

export default function UserProfile() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Mój profil" />

            <PageHeader
                title="Mój profil"
                description="Dane kontaktowe i dane kierowcy używane przy rezerwacjach."
            />

            <div className="flex flex-col gap-6">
                <Card
                    title="Dane osobowe"
                    description="Te dane pojawią się na umowie wynajmu i fakturze."
                >
                    <Field label="Imię i nazwisko" id="name" defaultValue={auth.user?.name} autoComplete="name" wide />
                    <Field label="Adres e-mail" id="email" type="email" defaultValue={auth.user?.email} autoComplete="email" />
                    <Field label="Telefon" id="phone" type="tel" placeholder="+48 000 000 000" autoComplete="tel" inputMode="tel" />
                    <Field label="Numer prawa jazdy" id="license" placeholder="np. 01234/26/1863" />
                    <Field label="Data urodzenia" id="birthdate" type="date" autoComplete="bday" />
                </Card>

                <Card
                    title="Zmiana hasła"
                    description="Użyj co najmniej 8 znaków."
                >
                    <Field label="Obecne hasło" id="current_password" type="password" autoComplete="current-password" wide />
                    <Field label="Nowe hasło" id="password" type="password" autoComplete="new-password" minLength={8} />
                    <Field label="Powtórz nowe hasło" id="password_confirmation" type="password" autoComplete="new-password" minLength={8} />
                </Card>
            </div>
        </>
    );
}

UserProfile.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
