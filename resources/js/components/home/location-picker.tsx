import { Suspense, lazy, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { PublicLocation } from '@/components/cars/car-card';
import { buttonClass } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const LocationMap = lazy(() => import('./location-map'));

export type MapLocation = PublicLocation & {
    street: string;
    building_number: string | null;
    postal_code: string;
    city: string;
    latitude: number | null;
    longitude: number | null;
    details: string | null;
};

export type UserPosition = { lat: number; lng: number };

type Geolocation = 'idle' | 'asking' | 'locating' | 'ready' | 'denied' | 'unsupported';

const hasCoordinates = (location: MapLocation) => location.latitude !== null && location.longitude !== null;

const address = (location: MapLocation) =>
    `${[location.street, location.building_number].filter(Boolean).join(' ')}, ${location.postal_code} ${location.city}`;

/** Odległość w linii prostej (wzór haversine). */
function distanceInKm(from: UserPosition, location: MapLocation) {
    const radians = (degrees: number) => (degrees * Math.PI) / 180;
    const latitude = radians(Number(location.latitude) - from.lat);
    const longitude = radians(Number(location.longitude) - from.lng);
    const arc =
        Math.sin(latitude / 2) ** 2 +
        Math.cos(radians(from.lat)) * Math.cos(radians(Number(location.latitude))) * Math.sin(longitude / 2) ** 2;

    return 6371 * 2 * Math.asin(Math.sqrt(arc));
}

const formatDistance = (kilometers: number) =>
    `${kilometers.toLocaleString('pl-PL', { maximumFractionDigits: kilometers < 10 ? 1 : 0 })} km`;

/** Okno wyboru miejsca: mapa, lista oraz szukanie w pobliżu po zgodzie klienta. */
export default function LocationPicker({
    type,
    locations,
    selected,
    onSelect,
    onClose,
}: {
    type: 'pickup' | 'dropoff' | null;
    locations: MapLocation[];
    selected: string;
    onSelect: (id: string) => void;
    onClose: () => void;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [activeId, setActiveId] = useState<number>();
    // Mapa musi powstać po otwarciu okna, inaczej Leaflet liczy widok dla kontenera bez rozmiaru.
    const [visible, setVisible] = useState(false);
    const [geolocation, setGeolocation] = useState<Geolocation>('idle');
    const [position, setPosition] = useState<UserPosition>();

    // Otwiera okno tylko przy zmianie rodzaju miejsca - sam wybór nie może otworzyć go ponownie.
    const opened = useRef(selected);
    opened.current = selected;

    useEffect(() => {
        if (type && !dialog.current?.open) {
            setActiveId(opened.current ? Number(opened.current) : undefined);
            dialog.current?.showModal();
            setVisible(true);
        }
    }, [type]);

    // Odmowa zapisana w przeglądarce oraz brak obsługi wyłączają szukanie w pobliżu.
    useEffect(() => {
        if (!type || geolocation !== 'idle') {
            return;
        }

        if (!window.isSecureContext || !navigator.geolocation) {
            setGeolocation('unsupported');

            return;
        }

        navigator.permissions
            ?.query({ name: 'geolocation' })
            .then((status) => status.state === 'denied' && setGeolocation('denied'))
            .catch(() => undefined);
    }, [type, geolocation]);

    const activate = useCallback((id: number) => setActiveId(id), []);

    const close = () => dialog.current?.close();

    const choose = (location: MapLocation) => {
        onSelect(String(location.id));
        close();
    };

    const locate = () => {
        setGeolocation('locating');
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                setPosition({ lat: coords.latitude, lng: coords.longitude });
                setGeolocation('ready');
            },
            () => {
                setPosition(undefined);
                setGeolocation('denied');
            },
            { timeout: 10000, maximumAge: 300000 },
        );
    };

    // Stała referencja - nowa tablica przy każdym renderze tworzyłaby mapę od nowa.
    const mapped = useMemo(() => locations.filter(hasCoordinates), [locations]);
    const showMap = mapped.length > 0;

    const list = position
        ? [...locations].sort(
              (first, second) =>
                  (hasCoordinates(first) ? distanceInKm(position, first) : Infinity) -
                  (hasCoordinates(second) ? distanceInKm(position, second) : Infinity),
          )
        : locations;

    return (
        <dialog
            ref={dialog}
            aria-label={type === 'dropoff' ? 'Wybierz miejsce zwrotu' : 'Wybierz miejsce odbioru'}
            onClose={() => {
                setVisible(false);
                onClose();
            }}
            onClick={(event) => event.target === dialog.current && close()}
            className={cn(
                'm-auto max-h-[90dvh] overflow-hidden rounded-2xl bg-white p-0 text-gray-900 shadow-2xl backdrop:bg-gray-950/60',
                showMap ? 'w-[min(60rem,calc(100vw-2rem))]' : 'w-[min(28rem,calc(100vw-2rem))]',
            )}
        >
            {type && (
                <div className="flex max-h-[90dvh] flex-col">
                    <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                        <h2 className="font-semibold">{type === 'dropoff' ? 'Miejsca zwrotu' : 'Miejsca odbioru'}</h2>
                        <button
                            type="button"
                            onClick={close}
                            aria-label="Zamknij"
                            className="-mr-2 flex size-10 items-center justify-center rounded-lg text-xl text-gray-500 hover:bg-gray-100"
                        >
                            ×
                        </button>
                    </div>

                    <div className={cn('grid min-h-0 flex-1 overflow-y-auto', showMap && 'md:grid-cols-[minmax(0,1fr)_20rem]')}>
                        {showMap && (
                            <div className="h-64 md:h-[32rem]">
                                <Suspense fallback={<p className="p-4 text-sm text-gray-500">Ładowanie mapy…</p>}>
                                    {visible && (
                                        <LocationMap
                                            locations={mapped}
                                            activeId={mapped.some((item) => item.id === activeId) ? activeId : undefined}
                                            position={position}
                                            onActivate={activate}
                                        />
                                    )}
                                </Suspense>
                            </div>
                        )}

                        <div className="flex min-h-0 flex-col gap-3 p-4 md:overflow-y-auto">
                            {geolocation === 'idle' && (
                                <button
                                    type="button"
                                    onClick={() => setGeolocation('asking')}
                                    className={buttonClass('outline', 'sm', 'w-full border-gray-300')}
                                >
                                    Szukaj w pobliżu mnie
                                </button>
                            )}

                            {geolocation === 'asking' && (
                                <div role="alertdialog" aria-label="Zgoda na lokalizację" className="rounded-xl bg-gray-50 p-3 text-sm text-gray-700">
                                    <p>
                                        Czy zgadzasz się na udostępnienie swojej lokalizacji? Użyjemy jej tylko w tym oknie, żeby
                                        pokazać najbliższe miejsca. Nie zapisujemy jej i nie wysyłamy na nasz serwer.
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <button type="button" onClick={locate} className={buttonClass('dark', 'sm', 'flex-1')}>
                                            Zgadzam się
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setGeolocation('denied')}
                                            className={buttonClass('outline', 'sm', 'flex-1 border-gray-300')}
                                        >
                                            Nie zgadzam się
                                        </button>
                                    </div>
                                </div>
                            )}

                            {geolocation === 'locating' && <p className="text-sm text-gray-500">Sprawdzam lokalizację…</p>}
                            {geolocation === 'ready' && <p className="text-sm text-gray-500">Najbliższe miejsca są na górze listy.</p>}
                            {geolocation === 'denied' && (
                                <p className="text-sm text-gray-500">
                                    Bez zgody na lokalizację szukanie w pobliżu jest niedostępne. Wybierz miejsce z listy.
                                </p>
                            )}
                            {geolocation === 'unsupported' && (
                                <p className="text-sm text-gray-500">Ta przeglądarka nie pozwala na szukanie w pobliżu.</p>
                            )}

                            {list.length === 0 ? (
                                <p className="text-sm text-gray-500">Brak dostępnych miejsc.</p>
                            ) : (
                                <ul className="flex flex-col gap-2">
                                    {list.map((location) => (
                                        <li
                                            key={location.id}
                                            className={cn(
                                                'rounded-xl border p-3',
                                                location.id === activeId ? 'border-brand-600' : 'border-gray-200',
                                            )}
                                        >
                                            <button
                                                type="button"
                                                onClick={() => setActiveId(location.id)}
                                                className="w-full text-left"
                                            >
                                                <span className="block text-sm font-semibold">{location.name}</span>
                                                <span className="block text-xs text-gray-500">{address(location)}</span>
                                                {position && hasCoordinates(location) && (
                                                    <span className="block text-xs font-medium text-gray-700">
                                                        {formatDistance(distanceInKm(position, location))}
                                                    </span>
                                                )}
                                                {location.details && (
                                                    <span className="mt-1 block text-xs text-gray-500">{location.details}</span>
                                                )}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => choose(location)}
                                                aria-label={`Wybierz: ${location.name}`}
                                                className={buttonClass('dark', 'sm', 'mt-2 w-full')}
                                            >
                                                Wybierz
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </dialog>
    );
}
