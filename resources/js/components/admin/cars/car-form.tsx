import { Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ChangeEvent, FormEvent, InputHTMLAttributes, ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import ColorSwatch from '@/components/ui/color-swatch';
import EquipmentIcon from '@/components/ui/equipment-icon';
import type { IconSvg } from '@/components/ui/equipment-icon';
import { FormField, Select } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import type { PriceTier } from '@/lib/pricing';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import { carStatuses, fuelTypeLabels, toOptions, transmissionLabels, vehicleTypeLabels } from '../data/labels';

export type CarOption = { id: number; name: string; icon?: string | null; icon_svg?: IconSvg | null; hex?: string };

export type CarImage = { id: number; url: string };

/** Samochód w formularzu edycji - dane z CarService::findCarForEdit. */
export type EditableCar = {
    id: number;
    brand: string;
    model: string;
    vehicle_type: string;
    registration_number: string;
    vin: string;
    production_year: number;
    color: string | null;
    color_option: { hex: string } | null;
    fuel_type: string;
    transmission: string;
    doors: number;
    seats: number;
    mileage_km: number;
    prices: PriceTier[];
    km_limit_per_day: number | null;
    deposit_amount: string;
    operational_status: string;
    description: string | null;
    equipment: CarOption[];
    locations: CarOption[];
    images: CarImage[];
};

const MAX_IMAGES = 10;

// Pola jak w tabeli cars (App\Models\Car) oraz wyposażenie i zdjęcia.
const emptyCar = {
    brand: '',
    model: '',
    vehicle_type: 'passenger',
    registration_number: '',
    vin: '',
    production_year: '',
    color: '',
    fuel_type: 'petrol',
    transmission: 'manual',
    doors: '5',
    seats: '5',
    mileage_km: '',
    prices: [{ min_days: '1', max_days: '', daily_rate: '' }],
    km_limit_per_day: '',
    deposit_amount: '',
    operational_status: 'active',
    description: '',
    equipment: [] as number[],
    locations: [] as number[],
    images: [] as File[],
    removed_images: [] as number[],
};

type Data = typeof emptyCar;
type TextField = Exclude<keyof Data, 'prices' | 'equipment' | 'locations' | 'images' | 'removed_images'>;
type PriceField = keyof Data['prices'][number];

const toFormData = (car?: EditableCar): Data =>
    car
        ? {
              ...emptyCar,
              brand: car.brand,
              model: car.model,
              vehicle_type: car.vehicle_type,
              registration_number: car.registration_number,
              vin: car.vin,
              production_year: String(car.production_year),
              color: car.color ?? '',
              fuel_type: car.fuel_type,
              transmission: car.transmission,
              doors: String(car.doors),
              seats: String(car.seats),
              mileage_km: String(car.mileage_km),
              prices: car.prices.map((price) => ({
                  min_days: String(price.min_days),
                  max_days: price.max_days === null ? '' : String(price.max_days),
                  daily_rate: price.daily_rate,
              })),
              km_limit_per_day: car.km_limit_per_day === null ? '' : String(car.km_limit_per_day),
              deposit_amount: car.deposit_amount,
              operational_status: car.operational_status,
              description: car.description ?? '',
              equipment: car.equipment.map((item) => item.id),
              locations: car.locations.map((item) => item.id),
          }
        : emptyCar;

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <fieldset className="border-b border-gray-100 p-5 last:border-b-0 sm:p-6">
            <legend className="sr-only">{title}</legend>
            <h2 className="mb-4 font-semibold text-gray-900" aria-hidden="true">
                {title}
            </h2>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{children}</div>
        </fieldset>
    );
}

/** Podgląd zdjęcia wybranego z dysku, jeszcze nie zapisanego. */
function NewImage({ file, onRemove }: { file: File; onRemove: () => void }) {
    const [url, setUrl] = useState<string>();

    useEffect(() => {
        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return (
        <li className="overflow-hidden rounded-lg border border-gray-200">
            <img src={url} alt={file.name} className="aspect-[392/250] w-full object-cover" />
            <button type="button" onClick={onRemove} className="h-10 w-full text-sm font-medium text-brand-600 hover:text-brand-700">
                Usuń
            </button>
        </li>
    );
}

export default function CarForm({
    car,
    equipment,
    colors,
    locations,
}: {
    car?: EditableCar;
    equipment: CarOption[];
    colors: CarOption[];
    locations: CarOption[];
}) {
    const form = useForm<Data>(toFormData(car));
    const { data, setData, post, processing, clearErrors } = form;
    // "save" - błąd zapisu z CarController::store (try/catch).
    const errors: Record<string, string | undefined> = form.errors;
    const firstError = (field: string) => Object.entries(errors).find(([key]) => key.startsWith(field))?.[1];
    const savedImages = car?.images ?? [];
    const imageCount = savedImages.length - data.removed_images.length + data.images.length;

    // Przy edycji auto zachowuje kolor i wyposażenie ukryte już w słowniku.
    const colorNames = colors.map((color) => color.name);
    const colorOptions = data.color && !colorNames.includes(data.color) ? [...colorNames, data.color] : colorNames;
    const colorHex =
        colors.find((color) => color.name === data.color)?.hex ??
        (data.color === car?.color ? car?.color_option?.hex : undefined);
    const equipmentOptions = [
        ...equipment,
        ...(car?.equipment ?? []).filter((item) => !equipment.some((option) => option.id === item.id)),
    ];

    /** Wiązanie wartości pola ze stanem (input i select). */
    const bind = (key: TextField) => ({
        id: key,
        name: key,
        value: data[key],
        onChange: (event: ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
            setData(key, event.target.value);
            clearErrors(key);
        },
    });

    const input = (key: TextField, props: InputHTMLAttributes<HTMLInputElement> = {}) => ({
        ...props,
        ...bind(key),
        className: inputClass,
    });

    const toggleEquipment = (id: number) => {
        setData(
            'equipment',
            data.equipment.includes(id) ? data.equipment.filter((item) => item !== id) : [...data.equipment, id],
        );
        clearErrors();
    };

    // Bez limitu to pusta wartość w bazie, dlatego wybór trzeba potwierdzić, a nie zostawić puste pole.
    const [unlimitedKm, setUnlimitedKm] = useState(car ? car.km_limit_per_day === null : false);

    const toggleUnlimitedKm = (unlimited: boolean) => {
        setUnlimitedKm(unlimited);
        setData('km_limit_per_day', '');
        clearErrors('km_limit_per_day');
    };

    const setPrice = (index: number, field: PriceField, value: string) => {
        setData(
            'prices',
            data.prices.map((price, position) => (position === index ? { ...price, [field]: value } : price)),
        );
        clearErrors();
    };

    /** Nowy próg zaczyna się dzień po poprzednim. Poprzedni bez limitu dostaje limit równy jego początkowi. */
    const addPrice = () => {
        const last = data.prices[data.prices.length - 1];
        const lastMax = last.max_days === '' ? last.min_days : last.max_days;

        setData('prices', [
            ...data.prices.slice(0, -1),
            { ...last, max_days: lastMax },
            { min_days: String(Number(lastMax) + 1), max_days: '', daily_rate: '' },
        ]);
        clearErrors();
    };

    const removePrice = (index: number) => {
        setData(
            'prices',
            data.prices.filter((_, position) => position !== index),
        );
        clearErrors();
    };

    // Przy edycji auto zachowuje miejsca ukryte już na liście.
    const locationOptions = [
        ...locations,
        ...(car?.locations ?? []).filter((item) => !locations.some((option) => option.id === item.id)),
    ];

    const toggleLocation = (id: number) => {
        setData(
            'locations',
            data.locations.includes(id) ? data.locations.filter((item) => item !== id) : [...data.locations, id],
        );
        clearErrors();
    };

    const addImages = (event: ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files ?? []).slice(0, MAX_IMAGES - imageCount);

        setData('images', [...data.images, ...files]);
        clearErrors();
        // Pozwala wybrać ten sam plik ponownie po usunięciu go z listy.
        event.target.value = '';
    };

    const removeNewImage = (index: number) => {
        setData(
            'images',
            data.images.filter((_, position) => position !== index),
        );
        clearErrors();
    };

    const toggleSavedImage = (id: number) => {
        setData(
            'removed_images',
            data.removed_images.includes(id)
                ? data.removed_images.filter((item) => item !== id)
                : [...data.removed_images, id],
        );
        clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(car ? admin.cars.update(car.id).url : admin.cars.store().url, { preserveScroll: true, forceFormData: true });
    };

    const year = new Date().getFullYear();

    return (
        <form onSubmit={submit} className="rounded-2xl border border-gray-200 bg-white">
            <Section title="Dane pojazdu">
                <FormField label="Marka" id="brand" error={errors.brand}>
                    <input {...input('brand', { required: true, placeholder: 'np. Toyota' })} />
                </FormField>
                <FormField label="Model" id="model" error={errors.model}>
                    <input {...input('model', { required: true, placeholder: 'np. Corolla' })} />
                </FormField>
                <FormField label="Rok produkcji" id="production_year" error={errors.production_year}>
                    <input {...input('production_year', { required: true, type: 'number', inputMode: 'numeric', min: 1990, max: year + 1 })} />
                </FormField>
                <FormField label="Numer rejestracyjny" id="registration_number" error={errors.registration_number}>
                    <input {...input('registration_number', { required: true, placeholder: 'RKR 12345', autoCapitalize: 'characters' })} />
                </FormField>
                <FormField label="VIN" id="vin" error={errors.vin} hint="17 znaków">
                    <input {...input('vin', { required: true, minLength: 17, maxLength: 17, autoCapitalize: 'characters' })} />
                </FormField>
                <FormField label="Kolor" id="color" error={errors.color}>
                    <div className="flex items-center gap-3">
                        <div className="min-w-0 flex-1">
                            <Select
                                {...bind('color')}
                                placeholder="Nie podano"
                                options={colorOptions.map((name) => ({ value: name, label: name }))}
                            />
                        </div>
                        <ColorSwatch hex={colorHex} className="size-7" />
                    </div>
                </FormField>
            </Section>

            <Section title="Parametry">
                <FormField label="Rodzaj pojazdu" id="vehicle_type" error={errors.vehicle_type}>
                    <Select {...bind('vehicle_type')} options={toOptions(vehicleTypeLabels)} />
                </FormField>
                <FormField label="Rodzaj paliwa" id="fuel_type" error={errors.fuel_type}>
                    <Select {...bind('fuel_type')} options={toOptions(fuelTypeLabels)} />
                </FormField>
                <FormField label="Skrzynia biegów" id="transmission" error={errors.transmission}>
                    <Select {...bind('transmission')} options={toOptions(transmissionLabels)} />
                </FormField>
                <FormField label="Przebieg (km)" id="mileage_km" error={errors.mileage_km}>
                    <input {...input('mileage_km', { required: true, type: 'number', inputMode: 'numeric', min: 0 })} />
                </FormField>
                <FormField label="Ilość drzwi" id="doors" error={errors.doors}>
                    <Select {...bind('doors')} options={[2, 3, 4, 5].map((value) => ({ value, label: String(value) }))} />
                </FormField>
                <FormField label="Liczba miejsc" id="seats" error={errors.seats}>
                    <input {...input('seats', { required: true, type: 'number', inputMode: 'numeric', min: 2, max: data.vehicle_type === 'bus' ? 60 : 9 })} />
                </FormField>
            </Section>

            <Section title="Wyposażenie">
                {equipmentOptions.length === 0 && (
                    <p className="text-sm text-gray-500 sm:col-span-2 xl:col-span-3">
                        Lista jest pusta.{' '}
                        <Link href={admin.dictionary.index()} className="font-medium text-brand-600 hover:text-brand-700">
                            Dodaj wyposażenie
                        </Link>
                    </p>
                )}
                {equipmentOptions.map((item) => (
                    <label key={item.id} className="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={data.equipment.includes(item.id)}
                            onChange={() => toggleEquipment(item.id)}
                            className="size-5 accent-brand-600"
                        />
                        <EquipmentIcon name={item.icon} svg={item.icon_svg} />
                        {item.name}
                    </label>
                ))}
                {firstError('equipment') && (
                    <p className="text-xs text-red-600 sm:col-span-2 xl:col-span-3">{firstError('equipment')}</p>
                )}
            </Section>

            <Section title="Aktualne miejsce">
                {locationOptions.length === 0 && (
                    <p className="text-sm text-gray-500 sm:col-span-2 xl:col-span-3">
                        Lista jest pusta.{' '}
                        <Link href={admin.locations.index()} className="font-medium text-brand-600 hover:text-brand-700">
                            Dodaj miejsce
                        </Link>
                    </p>
                )}
                {locationOptions.map((item) => (
                    <label key={item.id} className="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={data.locations.includes(item.id)}
                            onChange={() => toggleLocation(item.id)}
                            className="size-5 accent-brand-600"
                        />
                        {item.name}
                    </label>
                ))}
                {firstError('locations') && (
                    <p className="text-xs text-red-600 sm:col-span-2 xl:col-span-3">{firstError('locations')}</p>
                )}
            </Section>

            <Section title="Zdjęcia">
                <FormField
                    label="Dodaj zdjęcia"
                    id="images"
                    error={firstError('images') ?? firstError('removed_images')}
                    hint={`JPG, PNG lub WebP, do 5 MB. Maksymalnie ${MAX_IMAGES} zdjęć, pierwsze jest okładką.`}
                    className="sm:col-span-2 xl:col-span-3"
                >
                    <input
                        id="images"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        disabled={imageCount >= MAX_IMAGES}
                        onChange={addImages}
                        className="block min-h-11 w-full text-sm text-gray-700 file:mr-3 file:h-11 file:cursor-pointer file:rounded-lg file:border-0 file:bg-gray-900 file:px-4 file:font-semibold file:text-white disabled:opacity-50"
                    />
                </FormField>

                {imageCount + data.removed_images.length > 0 && (
                    <ul className="grid grid-cols-2 gap-3 sm:col-span-2 sm:grid-cols-3 xl:col-span-3 xl:grid-cols-5">
                        {savedImages.map((image) => {
                            const removed = data.removed_images.includes(image.id);

                            return (
                                <li key={image.id} className="overflow-hidden rounded-lg border border-gray-200">
                                    <img
                                        src={image.url}
                                        alt="Zdjęcie samochodu"
                                        className={cn('aspect-[392/250] w-full object-cover', removed && 'opacity-30')}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => toggleSavedImage(image.id)}
                                        className="h-10 w-full text-sm font-medium text-brand-600 hover:text-brand-700"
                                    >
                                        {removed ? 'Przywróć' : 'Usuń'}
                                    </button>
                                </li>
                            );
                        })}
                        {data.images.map((file, index) => (
                            <NewImage
                                key={`${file.name}-${file.size}-${file.lastModified}-${index}`}
                                file={file}
                                onRemove={() => removeNewImage(index)}
                            />
                        ))}
                    </ul>
                )}
            </Section>

            <Section title="Wynajem">
                <fieldset className="sm:col-span-2 xl:col-span-3">
                    <legend className="mb-1.5 text-sm font-medium text-gray-700">Ceny za dzień według długości wynajmu</legend>
                    <div className="flex flex-col gap-2">
                        {data.prices.map((price, index) => (
                            <div key={index} className="grid grid-cols-[1fr_1fr_1.5fr_auto] items-center gap-2 sm:gap-3">
                                <input
                                    required
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    aria-label={`Próg ${index + 1}: od ilu dni`}
                                    placeholder="Od dni"
                                    value={price.min_days}
                                    onChange={(event) => setPrice(index, 'min_days', event.target.value)}
                                    className={inputClass}
                                />
                                <input
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    aria-label={`Próg ${index + 1}: do ilu dni`}
                                    placeholder="bez limitu"
                                    value={price.max_days}
                                    onChange={(event) => setPrice(index, 'max_days', event.target.value)}
                                    className={inputClass}
                                />
                                <input
                                    required
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    step="0.01"
                                    aria-label={`Próg ${index + 1}: cena za dzień w złotych`}
                                    placeholder="Cena (zł)"
                                    value={price.daily_rate}
                                    onChange={(event) => setPrice(index, 'daily_rate', event.target.value)}
                                    className={inputClass}
                                />
                                <button
                                    type="button"
                                    onClick={() => removePrice(index)}
                                    disabled={data.prices.length === 1}
                                    aria-label={`Usuń próg ${index + 1}`}
                                    className="h-11 px-2 text-sm font-medium text-brand-600 hover:text-brand-700 disabled:opacity-40"
                                >
                                    Usuń
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-gray-500">Np. 1-1 po 500 zł, 2-3 po 450 zł, od 4 bez limitu po 400 zł.</p>
                        <button type="button" onClick={addPrice} className={buttonClass('outline', 'sm', 'border-gray-300')}>
                            + Dodaj próg
                        </button>
                    </div>
                    {firstError('prices') && <p className="mt-1.5 text-xs text-red-600">{firstError('prices')}</p>}
                </fieldset>
                <FormField label="Limit km na dzień" id="km_limit_per_day" error={errors.km_limit_per_day}>
                    <input
                        {...input('km_limit_per_day', {
                            required: !unlimitedKm,
                            disabled: unlimitedKm,
                            type: 'number',
                            inputMode: 'numeric',
                            min: 1,
                            max: 100000,
                            placeholder: unlimitedKm ? 'Bez limitu' : 'np. 300',
                        })}
                    />
                    <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={unlimitedKm}
                            onChange={(event) => toggleUnlimitedKm(event.target.checked)}
                            className="size-5 accent-brand-600"
                        />
                        Bez limitu kilometrów
                    </label>
                </FormField>
                <FormField label="Kaucja (zł)" id="deposit_amount" error={errors.deposit_amount}>
                    <input {...input('deposit_amount', { required: true, type: 'number', inputMode: 'decimal', min: 0, step: '0.01' })} />
                </FormField>
                <FormField label="Status" id="operational_status" error={errors.operational_status}>
                    <Select {...bind('operational_status')} options={toOptions(carStatuses)} />
                </FormField>
                <FormField label="Opis" id="description" error={errors.description} className="sm:col-span-2 xl:col-span-3">
                    <textarea
                        id="description"
                        rows={4}
                        value={data.description}
                        onChange={(event) => setData('description', event.target.value)}
                        className={`${inputClass} h-auto py-3`}
                    />
                </FormField>
            </Section>

            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-end sm:p-6">
                {Object.keys(errors).length > 0 && (
                    <p role="alert" className="text-sm text-red-600 sm:mr-auto">
                        {errors.save ?? 'Popraw zaznaczone pola formularza.'}
                    </p>
                )}
                <Link href={admin.cars.index()} className={buttonClass('outline', 'md', 'border-gray-300')}>
                    Anuluj
                </Link>
                <button type="submit" disabled={processing} className={buttonClass('dark', 'md', 'disabled:opacity-60')}>
                    {processing ? 'Zapisywanie…' : car ? 'Zapisz zmiany' : 'Dodaj samochód'}
                </button>
            </div>
        </form>
    );
}
