import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type { MapLocation, UserPosition } from './location-picker';

const pinClass =
    'block w-max -translate-x-1/2 -translate-y-1/2 rounded-full px-3 py-1.5 text-xs font-semibold whitespace-nowrap shadow-lg ring-1 ring-black/10';
const pinIdleClass = 'bg-white text-gray-900';
const pinActiveClass = 'bg-brand-600 text-white';

const TILES_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const TILES_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

const DEFAULT_CENTER: L.LatLngTuple = [49.6886, 21.7706];
const DETAIL_ZOOM = 15;
const MAX_FIT_ZOOM = 14;

/** 1 miejsce, 2 miejsca, 5 miejsc, 22 miejsca. */
const placesLabel = (count: number) => {
    const units = count % 10;
    const tens = count % 100;

    if (count === 1) {
        return '1 miejsce';
    }

    return units >= 2 && units <= 4 && (tens < 12 || tens > 14) ? `${count} miejsca` : `${count} miejsc`;
};

const coordinates = (location: MapLocation): L.LatLngTuple => [Number(location.latitude), Number(location.longitude)];

/** Tekst trafia do elementu przez textContent, więc nazwy miejsc nie są traktowane jak HTML. */
function labelIcon(text: string, className: string) {
    const label = document.createElement('span');
    label.className = cn(pinClass, className);
    label.textContent = text;

    return { icon: L.divIcon({ html: label, className: '', iconSize: undefined }), label };
}

export default function LocationMap({
    locations,
    activeId,
    position,
    onActivate,
}: {
    locations: MapLocation[];
    activeId?: number;
    position?: UserPosition;
    onActivate: (id: number) => void;
}) {
    const container = useRef<HTMLDivElement>(null);
    const map = useRef<L.Map | null>(null);
    const pins = useRef(new Map<number, HTMLElement>());
    const [ready, setReady] = useState(false);

    useEffect(() => {
        if (!container.current) {
            return;
        }

        const instance = L.map(container.current).setView(DEFAULT_CENTER, 11);
        L.tileLayer(TILES_URL, { attribution: TILES_ATTRIBUTION, maxZoom: 19 }).addTo(instance);

        const cities = new WeakMap<L.Marker, string>();

        // Po oddaleniu bliskie miejsca łączą się w znacznik "Krosno 3 miejsca".
        const group = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 60,
            iconCreateFunction: (cluster) => {
                const markers = cluster.getAllChildMarkers();
                const names = new Set(markers.map((marker) => cities.get(marker)));
                const count = placesLabel(markers.length);

                return labelIcon(names.size === 1 ? `${[...names][0]} ${count}` : count, 'bg-gray-900 text-sm text-white').icon;
            },
        });

        locations.forEach((location) => {
            const { icon, label } = labelIcon(location.name, pinIdleClass);
            const marker = L.marker(coordinates(location), { icon, title: location.name, alt: location.name });

            pins.current.set(location.id, label);
            cities.set(marker, location.city);
            marker.on('click', () => onActivate(location.id));
            group.addLayer(marker);
        });

        instance.addLayer(group);

        if (locations.length > 0) {
            instance.fitBounds(L.latLngBounds(locations.map(coordinates)), { padding: [60, 60], maxZoom: MAX_FIT_ZOOM });
        }

        map.current = instance;
        setReady(true);

        return () => {
            instance.remove();
            map.current = null;
            pins.current.clear();
            setReady(false);
        };
    }, [locations, onActivate]);

    // Wybrane miejsce: podświetlenie i przybliżenie do dokładnego punktu.
    useEffect(() => {
        pins.current.forEach((pin, id) => {
            pin.className = cn(pinClass, id === activeId ? pinActiveClass : pinIdleClass);
        });

        const location = locations.find((item) => item.id === activeId);

        if (location && map.current) {
            map.current.setView(coordinates(location), Math.max(map.current.getZoom(), DETAIL_ZOOM));
        }
    }, [activeId, locations, ready]);

    // Pozycja klienta istnieje tylko po jego zgodzie.
    useEffect(() => {
        if (!position || !map.current) {
            return;
        }

        const instance = map.current;
        const marker = L.circleMarker(position, { radius: 8, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.9 })
            .bindTooltip('Twoja lokalizacja')
            .addTo(instance);

        instance.fitBounds(L.latLngBounds([...locations.map(coordinates), [position.lat, position.lng]]), {
            padding: [60, 60],
            maxZoom: MAX_FIT_ZOOM,
        });

        return () => {
            marker.remove();
        };
    }, [position, locations, ready]);

    return <div ref={container} className="size-full min-h-64" />;
}
