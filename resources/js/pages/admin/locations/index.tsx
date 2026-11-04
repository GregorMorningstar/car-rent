import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import { EmptyRow, Table, Td, Th } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type AdminLocation = {
    id: number;
    name: string;
    street: string;
    building_number: string | null;
    postal_code: string;
    city: string;
    latitude: number | null;
    longitude: number | null;
    details: string | null;
    is_pickup: boolean;
    is_dropoff: boolean;
    is_active: boolean;
    cars_count: number;
    available_cars_count: number;
};

const emptyForm = {
    name: '',
    street: '',
    building_number: '',
    postal_code: '',
    city: '',
    latitude: '',
    longitude: '',
    details: '',
    is_pickup: true,
    is_dropoff: true,
};

type TextField = 'name' | 'street' | 'building_number' | 'postal_code' | 'city' | 'latitude' | 'longitude';

const toForm = (location: AdminLocation) => ({
    name: location.name,
    street: location.street,
    building_number: location.building_number ?? '',
    postal_code: location.postal_code,
    city: location.city,
    latitude: location.latitude === null ? '' : String(location.latitude),
    longitude: location.longitude === null ? '' : String(location.longitude),
    details: location.details ?? '',
    is_pickup: location.is_pickup,
    is_dropoff: location.is_dropoff,
});

const locationAddress = (location: AdminLocation) =>
    [location.street, location.building_number].filter(Boolean).join(' ');

const locationType = (location: AdminLocation) =>
    [location.is_pickup && 'Odbiór', location.is_dropoff && 'Dostawa'].filter(Boolean).join(', ');

export default function AdminLocations({ locations }: { locations: AdminLocation[] }) {
    const [editing, setEditing] = useState<AdminLocation>();
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm(emptyForm);

    const text = (key: TextField) => ({
        id: `location-${key}`,
        value: data[key],
        onChange: (event: { target: { value: string } }) => setData(key, event.target.value),
        className: inputClass,
    });

    const finish = () => {
        setEditing(undefined);
        reset();
        clearErrors();
    };

    const startEditing = (location: AdminLocation) => {
        setEditing(location);
        setData(toForm(location));
        clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: finish };

        if (editing) {
            put(admin.locations.update(editing.id).url, options);
        } else {
            post(admin.locations.store().url, options);
        }
    };

    return (
        <>
            <Head title="Miejsca odbioru i dostawy" />
            <PageHeader
                title="Miejsca odbioru i dostawy"
                description="Ukryte miejsce znika z wyszukiwarki i z formularza auta, ale zostaje przy przypisanych samochodach."
            />

            <form onSubmit={submit} className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                <h2 className="mb-4 font-semibold text-gray-900">
                    {editing ? `Edycja: ${editing.name}` : 'Nowe miejsce'}
                </h2>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <FormField label="Nazwa" id="location-name" error={errors.name}>
                        <input {...text('name')} required maxLength={150} />
                    </FormField>
                    <FormField label="Ulica" id="location-street" error={errors.street}>
                        <input {...text('street')} required maxLength={150} />
                    </FormField>
                    <FormField label="Numer budynku" id="location-building_number" error={errors.building_number}>
                        <input {...text('building_number')} maxLength={10} />
                    </FormField>
                    <FormField label="Kod pocztowy" id="location-postal_code" error={errors.postal_code}>
                        <input {...text('postal_code')} required maxLength={6} placeholder="38-400" />
                    </FormField>
                    <FormField label="Miejscowość" id="location-city" error={errors.city}>
                        <input {...text('city')} required maxLength={100} />
                    </FormField>
                    <FormField
                        label="Szerokość geograficzna"
                        id="location-latitude"
                        error={errors.latitude}
                        hint="Bez współrzędnych miejsca nie będzie na mapie."
                    >
                        <input {...text('latitude')} type="number" step="any" min={-90} max={90} placeholder="49.6886" />
                    </FormField>
                    <FormField
                        label="Długość geograficzna"
                        id="location-longitude"
                        error={errors.longitude}
                        hint="Skopiujesz je, klikając punkt w Google Maps."
                    >
                        <input {...text('longitude')} type="number" step="any" min={-180} max={180} placeholder="21.7706" />
                    </FormField>
                    <fieldset>
                        <legend className="mb-1.5 text-sm font-medium text-gray-700">Rodzaj miejsca</legend>
                        <div className="flex gap-5">
                            <label className="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={data.is_pickup}
                                    onChange={(event) => setData('is_pickup', event.target.checked)}
                                    className="size-5 accent-brand-600"
                                />
                                Odbiór
                            </label>
                            <label className="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={data.is_dropoff}
                                    onChange={(event) => setData('is_dropoff', event.target.checked)}
                                    className="size-5 accent-brand-600"
                                />
                                Dostawa
                            </label>
                        </div>
                        {(errors.is_dropoff ?? errors.is_pickup) && (
                            <p className="mt-1 text-xs text-red-600">{errors.is_dropoff ?? errors.is_pickup}</p>
                        )}
                    </fieldset>
                    <FormField label="Szczegóły" id="location-details" error={errors.details} className="sm:col-span-2 xl:col-span-3">
                        <textarea
                            id="location-details"
                            rows={3}
                            maxLength={2000}
                            placeholder="np. wjazd od podwórza, godziny otwarcia"
                            value={data.details}
                            onChange={(event) => setData('details', event.target.value)}
                            className={`${inputClass} h-auto py-3`}
                        />
                    </FormField>
                </div>

                <div className="mt-4 flex justify-end gap-3">
                    {editing && (
                        <button type="button" onClick={finish} className={buttonClass('outline', 'md', 'border-gray-300')}>
                            Anuluj
                        </button>
                    )}
                    <button type="submit" disabled={processing} className={buttonClass('dark')}>
                        {editing ? 'Zapisz zmiany' : 'Dodaj miejsce'}
                    </button>
                </div>
            </form>

            <Table
                head={
                    <>
                        <Th>Miejsce</Th>
                        <Th>Adres</Th>
                        <Th>Rodzaj</Th>
                        <Th className="text-right">Dostępne dziś</Th>
                        <Th className="text-right">Wszystkie auta</Th>
                        <Th>
                            <span className="sr-only">Akcje</span>
                        </Th>
                    </>
                }
            >
                {locations.length === 0 && <EmptyRow colSpan={6}>Brak miejsc.</EmptyRow>}

                {locations.map((location) => (
                    <tr key={location.id} className={cn('hover:bg-gray-50', !location.is_active && 'text-gray-400')}>
                        <Td>
                            <p className={cn('font-medium', location.is_active ? 'text-gray-900' : 'line-through')}>{location.name}</p>
                            {location.details && <p className="max-w-xs text-xs text-gray-500">{location.details}</p>}
                        </Td>
                        <Td>
                            {locationAddress(location)}
                            <p className="text-xs text-gray-500">
                                {location.postal_code} {location.city}
                            </p>
                        </Td>
                        <Td className="whitespace-nowrap">{locationType(location)}</Td>
                        <Td className="text-right tabular-nums">{location.available_cars_count}</Td>
                        <Td className="text-right tabular-nums">{location.cars_count}</Td>
                        <Td className="text-right whitespace-nowrap">
                            <button
                                type="button"
                                onClick={() => startEditing(location)}
                                aria-label={`Edytuj: ${location.name}`}
                                className="h-10 px-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                            >
                                Edytuj
                            </button>
                            <button
                                type="button"
                                onClick={() => router.patch(admin.locations.toggle(location.id).url, {}, { preserveScroll: true })}
                                aria-label={`${location.is_active ? 'Ukryj' : 'Przywróć'}: ${location.name}`}
                                className="h-10 px-2 text-sm font-medium text-brand-600 hover:text-brand-700"
                            >
                                {location.is_active ? 'Ukryj' : 'Przywróć'}
                            </button>
                        </Td>
                    </tr>
                ))}
            </Table>
        </>
    );
}

AdminLocations.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
