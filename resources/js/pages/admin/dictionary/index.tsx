import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import ColorSwatch from '@/components/ui/color-swatch';
import EquipmentIcon, { equipmentIcons } from '@/components/ui/equipment-icon';
import type { IconSvg } from '@/components/ui/equipment-icon';
import { FormField } from '@/components/ui/form-field';
import { inputClass } from '@/components/ui/input';
import PageHeader from '@/components/ui/page-header';
import Pagination from '@/components/ui/pagination';
import SearchInput from '@/components/ui/search-input';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type DictionaryItem = {
    id: number;
    name: string;
    icon?: string | null;
    icon_svg?: IconSvg | null;
    hex?: string;
    is_active: boolean;
};

type Paginated = {
    data: DictionaryItem[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

/** Ikona z wyszukiwarki Font Awesome - dane z FontAwesomeService::search. */
type IconResult = { name: string; label: string; svg: IconSvg | null; preview_url: string | null };

type Filters = { equipment_search: string; colors_search: string };

const DEFAULT_HEX = '#9ca3af';
const MIN_ICON_QUERY = 2;

const iconButtonClass =
    'flex size-11 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition-colors hover:border-gray-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600';
const iconButtonSelectedClass = 'border-brand-600 bg-brand-600 text-white hover:border-brand-600';

function IconChoice({
    label,
    selected,
    onSelect,
    children,
}: {
    label: string;
    selected: boolean;
    onSelect: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            aria-pressed={selected}
            onClick={onSelect}
            className={cn(iconButtonClass, selected && iconButtonSelectedClass)}
        >
            {children}
        </button>
    );
}

/** Podgląd ikony z wyszukiwarki: kształt z API albo plik darmowej ikony. */
function IconResultPreview({ icon, selected = false }: { icon: IconResult; selected?: boolean }) {
    if (icon.svg) {
        return <EquipmentIcon svg={icon.svg} className="text-current" />;
    }

    return (
        <img
            src={icon.preview_url ?? undefined}
            alt=""
            width={18}
            height={18}
            className={cn('size-4.5', selected ? 'brightness-0 invert' : 'opacity-60')}
        />
    );
}

/** Wyszukiwanie ikon przez FontAwesomeService - wyniki dla krótkich zapytań są ukrywane. */
function useIconSearch(query: string) {
    const term = query.trim();
    const [results, setResults] = useState<IconResult[]>([]);
    const [status, setStatus] = useState<'idle' | 'loading' | 'error'>('idle');

    useEffect(() => {
        if (term.length < MIN_ICON_QUERY) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setStatus('loading');

            try {
                const response = await fetch(admin.dictionary.icons.url({ query: { q: term } }), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('Font Awesome');
                }

                const body: { icons: IconResult[] } = await response.json();
                setResults(body.icons);
                setStatus('idle');
            } catch {
                if (!controller.signal.aborted) {
                    setResults([]);
                    setStatus('error');
                }
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    return { results: term.length < MIN_ICON_QUERY ? [] : results, status, searched: term.length >= MIN_ICON_QUERY };
}

/** Przycisk z aktualną ikoną i małe okno wyboru: wbudowane ikony oraz wyszukiwarka Font Awesome. */
function IconPicker({
    id,
    value,
    onChange,
    error,
    current,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    current?: DictionaryItem;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [query, setQuery] = useState('');
    const [picked, setPicked] = useState<IconResult>();
    const { results, status, searched } = useIconSearch(query);

    const select = (name: string, icon?: IconResult) => {
        setPicked(icon);
        onChange(name);
        dialog.current?.close();
    };

    const isBuiltIn = value in equipmentIcons;

    return (
        <FormField label="Ikona" id={id} error={error}>
            <button
                id={id}
                type="button"
                title={value ? 'Zmień ikonę' : 'Wybierz ikonę'}
                aria-label={value ? `Zmień ikonę, wybrana: ${value}` : 'Wybierz ikonę'}
                onClick={() => dialog.current?.showModal()}
                className={iconButtonClass}
            >
                {picked && picked.name === value && <IconResultPreview icon={picked} />}
                {isBuiltIn && <EquipmentIcon name={value} />}
                {!isBuiltIn && picked?.name !== value && value === current?.icon && <EquipmentIcon svg={current?.icon_svg} />}
                {!value && <span aria-hidden="true">+</span>}
            </button>

            {/* Enter w polu szukania nie może wysłać formularza, w którym jest to okno. */}
            <dialog
                ref={dialog}
                aria-label="Wybierz ikonę"
                onClose={() => setQuery('')}
                onClick={(event) => event.target === dialog.current && dialog.current.close()}
                className="m-auto w-[min(26rem,calc(100vw-2rem))] rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-gray-950/60"
            >
                <div className="flex flex-col gap-3 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <h3 className="font-semibold text-gray-900">Wybierz ikonę</h3>
                        <button
                            type="button"
                            onClick={() => dialog.current?.close()}
                            aria-label="Zamknij"
                            className="-mr-2 flex size-10 items-center justify-center rounded-lg text-xl text-gray-500 hover:bg-gray-100"
                        >
                            ×
                        </button>
                    </div>

                    <div onKeyDown={(event) => event.key === 'Enter' && event.preventDefault()}>
                        <SearchInput value={query} onChange={setQuery} placeholder="Szukaj po angielsku, np. seat, wifi…" />
                    </div>

                    <div className="flex h-56 flex-wrap content-start gap-2 overflow-y-auto p-0.5">
                        {!searched && (
                            <>
                                <IconChoice label="Bez ikony" selected={value === ''} onSelect={() => select('')}>
                                    –
                                </IconChoice>
                                {Object.entries(equipmentIcons).map(([name, { label }]) => (
                                    <IconChoice key={name} label={label} selected={value === name} onSelect={() => select(name)}>
                                        <EquipmentIcon name={name} className="text-current" />
                                    </IconChoice>
                                ))}
                            </>
                        )}
                        {results.map((icon) => (
                            <IconChoice
                                key={icon.name}
                                label={icon.label}
                                selected={value === icon.name}
                                onSelect={() => select(icon.name, icon)}
                            >
                                <IconResultPreview icon={icon} selected={value === icon.name} />
                            </IconChoice>
                        ))}
                        {searched && status === 'loading' && results.length === 0 && (
                            <p className="text-sm text-gray-500">Szukam…</p>
                        )}
                        {searched && status === 'error' && (
                            <p className="text-sm text-red-600">Nie udało się wyszukać ikon. Spróbuj ponownie.</p>
                        )}
                        {searched && status === 'idle' && results.length === 0 && (
                            <p className="text-sm text-gray-500">Brak ikon dla tego hasła.</p>
                        )}
                    </div>
                </div>
            </dialog>
        </FormField>
    );
}

function HexField({
    id,
    value,
    onChange,
    error,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <FormField label="Wzór" id={id} error={error}>
            <input
                id={id}
                type="color"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-11 w-14 cursor-pointer rounded-lg border border-gray-300 bg-white p-1"
            />
        </FormField>
    );
}

/** Pozycja słownika: nazwa z akcjami albo formularz edycji. */
function DictionaryRow({
    item,
    formId,
    updateUrl,
    toggleUrl,
    withIcon,
    withHex,
}: {
    item: DictionaryItem;
    formId: string;
    updateUrl: (itemId: number) => string;
    toggleUrl: (itemId: number) => string;
    withIcon: boolean;
    withHex: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors, reset, clearErrors, setDefaults } = useForm({
        name: item.name,
        icon: item.icon ?? '',
        hex: item.hex ?? DEFAULT_HEX,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(updateUrl(item.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDefaults();
                setEditing(false);
            },
        });
    };

    const cancel = () => {
        reset();
        clearErrors();
        setEditing(false);
    };

    if (editing) {
        return (
            <li className="py-3">
                <form onSubmit={submit}>
                    <div className="flex items-start gap-3">
                        <FormField label="Nazwa" id={`${formId}-${item.id}`} error={errors.name} className="flex-1">
                            <input
                                id={`${formId}-${item.id}`}
                                required
                                maxLength={100}
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                className={inputClass}
                            />
                        </FormField>
                        {withHex && (
                            <HexField
                                id={`${formId}-${item.id}-hex`}
                                value={data.hex}
                                onChange={(hex) => setData('hex', hex)}
                                error={errors.hex}
                            />
                        )}
                        {withIcon && (
                            <IconPicker
                                id={`${formId}-${item.id}-icon`}
                                value={data.icon}
                                onChange={(icon) => setData('icon', icon)}
                                error={errors.icon}
                                current={item}
                            />
                        )}
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <button type="button" onClick={cancel} className={buttonClass('outline', 'sm', 'border-gray-300')}>
                            Anuluj
                        </button>
                        <button type="submit" disabled={processing} className={buttonClass('dark', 'sm')}>
                            Zapisz
                        </button>
                    </div>
                </form>
            </li>
        );
    }

    return (
        <li className="flex min-h-11 items-center justify-between gap-3 py-1.5">
            <span className={cn('flex items-center gap-2.5 text-sm text-gray-900', !item.is_active && 'text-gray-400 line-through')}>
                <ColorSwatch hex={item.hex} className="size-5" />
                <EquipmentIcon name={item.icon} svg={item.icon_svg} />
                {item.name}
            </span>
            <div className="flex shrink-0">
                <button
                    type="button"
                    onClick={() => setEditing(true)}
                    aria-label={`Edytuj: ${item.name}`}
                    className="h-10 px-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    Edytuj
                </button>
                <button
                    type="button"
                    onClick={() => router.patch(toggleUrl(item.id), {}, { preserveScroll: true })}
                    aria-label={`${item.is_active ? 'Ukryj' : 'Przywróć'}: ${item.name}`}
                    className="h-10 px-2 text-sm font-medium text-brand-600 hover:text-brand-700"
                >
                    {item.is_active ? 'Ukryj' : 'Przywróć'}
                </button>
            </div>
        </li>
    );
}

function DictionaryCard({
    id,
    title,
    label,
    placeholder,
    list,
    search,
    onSearch,
    onPage,
    storeUrl,
    updateUrl,
    toggleUrl,
    withIcon = false,
    withHex = false,
}: {
    id: string;
    title: string;
    label: string;
    placeholder: string;
    list: Paginated;
    search: string;
    onSearch: (value: string) => void;
    onPage: (page: number) => void;
    storeUrl: string;
    updateUrl: (itemId: number) => string;
    toggleUrl: (itemId: number) => string;
    withIcon?: boolean;
    withHex?: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', icon: '', hex: DEFAULT_HEX });
    const [query, setQuery] = useState(search);

    // Wysyła wyszukiwanie po chwili bez pisania.
    useEffect(() => {
        if (query === search) {
            return;
        }

        const timer = setTimeout(() => onSearch(query), 300);

        return () => clearTimeout(timer);
    }, [query, search, onSearch]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(storeUrl, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <section className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <h2 className="mb-4 font-semibold text-gray-900">{title}</h2>

            <form onSubmit={submit}>
                <div className="flex items-start gap-3">
                    <FormField label={label} id={id} error={errors.name} className="flex-1">
                        <input
                            id={id}
                            required
                            maxLength={100}
                            value={data.name}
                            placeholder={placeholder}
                            onChange={(event) => setData('name', event.target.value)}
                            className={inputClass}
                        />
                    </FormField>
                    {withHex && (
                        <HexField id={`${id}-hex`} value={data.hex} onChange={(hex) => setData('hex', hex)} error={errors.hex} />
                    )}
                    {withIcon && (
                        <IconPicker id={`${id}-icon`} value={data.icon} onChange={(icon) => setData('icon', icon)} error={errors.icon} />
                    )}
                    <button type="submit" disabled={processing} className={buttonClass('dark', 'md', 'mt-6.5')}>
                        Dodaj
                    </button>
                </div>
            </form>

            <SearchInput value={query} onChange={setQuery} placeholder={`Szukaj: ${title.toLowerCase()}…`} className="mt-5" />

            {list.data.length === 0 ? (
                <p className="mt-4 text-sm text-gray-500">{search ? 'Brak wyników.' : 'Lista jest pusta.'}</p>
            ) : (
                <ul className="mt-3 divide-y divide-gray-100">
                    {list.data.map((item) => (
                        <DictionaryRow
                            key={item.id}
                            item={item}
                            formId={id}
                            updateUrl={updateUrl}
                            toggleUrl={toggleUrl}
                            withIcon={withIcon}
                            withHex={withHex}
                        />
                    ))}
                </ul>
            )}

            <Pagination
                page={list.current_page}
                pages={list.last_page}
                from={list.from ?? 0}
                to={list.to ?? 0}
                total={list.total}
                onChange={onPage}
            />
        </section>
    );
}

export default function AdminDictionary({
    equipment,
    colors,
    filters,
}: {
    equipment: Paginated;
    colors: Paginated;
    filters: Filters;
}) {
    // Obie listy mają własne wyszukiwanie i stronę, zapisane w adresie.
    const visit = (changes: Partial<Filters & { equipment_page: number; colors_page: number }>) => {
        const query = {
            ...filters,
            equipment_page: equipment.current_page,
            colors_page: colors.current_page,
            ...changes,
        };

        router.get(
            admin.dictionary.index.url(),
            Object.fromEntries(Object.entries(query).filter(([, value]) => value !== '' && value !== 1)),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Wyposażenie i kolory" />
            <PageHeader
                title="Wyposażenie i kolory"
                description="Opcje do wyboru w formularzu samochodu. Ukryta pozycja znika z wyboru, ale zostaje przy dodanych autach."
            />

            <div className="grid items-start gap-6 lg:grid-cols-2">
                <DictionaryCard
                    id="equipment-name"
                    title="Wyposażenie"
                    label="Nowe wyposażenie"
                    placeholder="np. Klimatyzacja"
                    list={equipment}
                    search={filters.equipment_search}
                    onSearch={(value) => visit({ equipment_search: value, equipment_page: 1 })}
                    onPage={(page) => visit({ equipment_page: page })}
                    storeUrl={admin.dictionary.equipment.store().url}
                    updateUrl={(id) => admin.dictionary.equipment.update(id).url}
                    toggleUrl={(id) => admin.dictionary.equipment.toggle(id).url}
                    withIcon
                />
                <DictionaryCard
                    id="color-name"
                    title="Kolory"
                    label="Nowy kolor"
                    placeholder="np. Biały"
                    list={colors}
                    search={filters.colors_search}
                    onSearch={(value) => visit({ colors_search: value, colors_page: 1 })}
                    onPage={(page) => visit({ colors_page: page })}
                    storeUrl={admin.dictionary.colors.store().url}
                    updateUrl={(id) => admin.dictionary.colors.update(id).url}
                    toggleUrl={(id) => admin.dictionary.colors.toggle(id).url}
                    withHex
                />
            </div>
        </>
    );
}

AdminDictionary.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
