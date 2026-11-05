import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';

type Options = {
    pickup_min_days: number;
    pickup_max_days: number;
    rental_min_days: number;
    rental_max_days: number;
    pickup_time_from: string;
    pickup_time_to: string;
    return_time_from: string;
    return_time_to: string;
    saturday_enabled: boolean;
    saturday_time_from: string;
    saturday_time_to: string;
    availability_mode: 'after_time' | 'next_day';
    buffer_minutes: number;
};

export default function AdminOptions({ options, holidays }: { options: Options; holidays: Record<string, string> }) {
    const { flash } = usePage().props;
    const { data, setData, put, processing, errors } = useForm(options);

    const number = (
        key: 'pickup_min_days' | 'pickup_max_days' | 'rental_min_days' | 'rental_max_days' | 'buffer_minutes',
        label: string,
        hint: string,
    ) => (
        <FormField label={label} id={key} error={errors[key]} hint={hint}>
            <input
                id={key}
                type="number"
                inputMode="numeric"
                required
                min={0}
                value={data[key]}
                onChange={(event) => setData(key, Number(event.target.value))}
                className={inputClass}
            />
        </FormField>
    );

    const time = (
        key: 'pickup_time_from' | 'pickup_time_to' | 'return_time_from' | 'return_time_to' | 'saturday_time_from' | 'saturday_time_to',
        label: string,
        disabled = false,
    ) => (
        <FormField label={label} id={key} error={errors[key]}>
            <input
                id={key}
                type="time"
                required={!disabled}
                disabled={disabled}
                value={data[key]}
                onChange={(event) => setData(key, event.target.value)}
                className={`${inputClass} disabled:bg-gray-50 disabled:text-gray-400`}
            />
        </FormField>
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(admin.options.update().url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Opcje" />
            <PageHeader title="Opcje" description="Limity terminów, które klient może wybrać przy rezerwacji." />

            <form onSubmit={submit} className="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                <h2 className="mb-4 font-semibold text-gray-900">Data odbioru</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    {number('pickup_min_days', 'Najwcześniej za (dni)', '0 to odbiór jeszcze dziś.')}
                    {number('pickup_max_days', 'Najpóźniej za (dni)', 'Jak daleko w przód można rezerwować.')}
                </div>

                <h2 className="mt-6 mb-4 font-semibold text-gray-900">Data zwrotu</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    {number('rental_min_days', 'Najkrótszy wynajem (dni)', 'Licząc od daty odbioru.')}
                    {number('rental_max_days', 'Najdłuższy wynajem (dni)', 'Licząc od daty odbioru.')}
                </div>

                <h2 className="mt-6 mb-4 font-semibold text-gray-900">Godziny odbioru i zwrotu</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    {time('pickup_time_from', 'Odbiór od')}
                    {time('pickup_time_to', 'Odbiór do')}
                    {time('return_time_from', 'Zwrot od')}
                    {time('return_time_to', 'Zwrot do')}
                </div>

                <fieldset className="mt-6">
                    <legend className="mb-2 font-semibold text-gray-900">Dostępność auta po zwrocie</legend>
                    <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-700">
                        <input
                            type="radio"
                            name="availability_mode"
                            checked={data.availability_mode === 'after_time'}
                            onChange={() => setData('availability_mode', 'after_time')}
                            className="size-5 accent-brand-600"
                        />
                        Po określonym czasie
                    </label>
                    <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-700">
                        <input
                            type="radio"
                            name="availability_mode"
                            checked={data.availability_mode === 'next_day'}
                            onChange={() => setData('availability_mode', 'next_day')}
                            className="size-5 accent-brand-600"
                        />
                        Dopiero następnego dnia
                    </label>
                    {errors.availability_mode && <p className="mt-1 text-xs text-red-600">{errors.availability_mode}</p>}
                </fieldset>
                <div className="mt-2 grid gap-4 sm:grid-cols-2">
                    {data.availability_mode === 'after_time' ? (
                        number(
                            'buffer_minutes',
                            'Przerwa po zwrocie (minuty)',
                            'Zapas na spóźnienie i przygotowanie auta. Zwrot o 10:00 i przerwa 60 minut: następny odbiór od 11:00.',
                        )
                    ) : (
                        <p className="text-sm text-gray-500 sm:col-span-2">
                            Zwrot w środę, o dowolnej godzinie: następny odbiór tego auta najwcześniej w czwartek.
                        </p>
                    )}
                </div>

                <h2 className="mt-6 mb-2 font-semibold text-gray-900">Soboty</h2>
                <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-medium text-gray-700">
                    <input
                        type="checkbox"
                        checked={data.saturday_enabled}
                        onChange={(event) => setData('saturday_enabled', event.target.checked)}
                        className="size-5 accent-brand-600"
                    />
                    Można odbierać i zwracać auta w soboty
                </label>
                <div className="mt-2 grid gap-4 sm:grid-cols-2">
                    {time('saturday_time_from', 'Sobota od', !data.saturday_enabled)}
                    {time('saturday_time_to', 'Sobota do', !data.saturday_enabled)}
                </div>

                <h2 className="mt-6 mb-1 font-semibold text-gray-900">Święta</h2>
                <p className="mb-2 text-sm text-gray-600">W święta państwowe nie można odebrać ani zwrócić auta. Najbliższe:</p>
                <ul className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                    {Object.entries(holidays).map(([date, name]) => (
                        <li key={date} className="flex justify-between gap-3">
                            <span className="text-gray-900">{name}</span>
                            <span className="whitespace-nowrap text-gray-500">{formatDate(date)}</span>
                        </li>
                    ))}
                </ul>

                {flash && (
                    <p role="status" className="mt-4 text-sm text-emerald-700">
                        {flash}
                    </p>
                )}

                <div className="mt-5 flex justify-end">
                    <button type="submit" disabled={processing} className={buttonClass('dark')}>
                        Zapisz
                    </button>
                </div>
            </form>
        </>
    );
}

AdminOptions.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
