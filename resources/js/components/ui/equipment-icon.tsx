import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faBaby,
    faCarBurst,
    faChair,
    faChargingStation,
    faFire,
    faGaugeHigh,
    faKey,
    faLightbulb,
    faLocationDot,
    faLock,
    faMusic,
    faPhone,
    faRoad,
    faShieldHalved,
    faSnowflake,
    faSquareParking,
    faSuitcaseRolling,
    faSun,
    faTowerBroadcast,
    faTrailer,
    faVideo,
    faWifi,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { cn } from '@/lib/utils';

// Wbudowane ikony wyposażenia - klucze zgodne z enumem App\Enums\EquipmentIcon.
export const equipmentIcons: Record<string, { icon: IconDefinition; label: string }> = {
    'snowflake': { icon: faSnowflake, label: 'Klimatyzacja' },
    'location-dot': { icon: faLocationDot, label: 'Nawigacja' },
    'tower-broadcast': { icon: faTowerBroadcast, label: 'Łączność' },
    'music': { icon: faMusic, label: 'Audio' },
    'shield-halved': { icon: faShieldHalved, label: 'Bezpieczeństwo' },
    'car-burst': { icon: faCarBurst, label: 'Poduszki powietrzne' },
    'video': { icon: faVideo, label: 'Kamera' },
    'square-parking': { icon: faSquareParking, label: 'Parkowanie' },
    'fire': { icon: faFire, label: 'Ogrzewanie' },
    'gauge-high': { icon: faGaugeHigh, label: 'Tempomat' },
    'trailer': { icon: faTrailer, label: 'Hak i przyczepa' },
    'chair': { icon: faChair, label: 'Fotele' },
    'baby': { icon: faBaby, label: 'Fotelik dziecięcy' },
    'key': { icon: faKey, label: 'Kluczyk' },
    'charging-station': { icon: faChargingStation, label: 'Ładowanie' },
    'sun': { icon: faSun, label: 'Szyberdach' },
    'suitcase-rolling': { icon: faSuitcaseRolling, label: 'Bagażnik' },
    'wifi': { icon: faWifi, label: 'Wi-Fi' },
    'phone': { icon: faPhone, label: 'Telefon' },
    'lightbulb': { icon: faLightbulb, label: 'Oświetlenie' },
    'lock': { icon: faLock, label: 'Zamek' },
    'road': { icon: faRoad, label: 'Asystent jazdy' },
};

/** Kształt ikony spoza zestawu wbudowanego - zapisany w bazie przy dodaniu wyposażenia. */
export type IconSvg = { viewBox: string; path: string };

/** Ikona wyposażenia z bazy; bez ikony nie wyświetla nic. */
export default function EquipmentIcon({
    name,
    svg,
    className,
}: {
    name?: string | null;
    svg?: IconSvg | null;
    className?: string;
}) {
    if (svg) {
        return (
            <svg
                viewBox={svg.viewBox}
                fill="currentColor"
                aria-hidden="true"
                className={cn('h-[1em] w-[1.25em] text-gray-500', className)}
            >
                <path d={svg.path} />
            </svg>
        );
    }

    const icon = name ? equipmentIcons[name] : undefined;

    if (!icon) {
        return null;
    }

    return <FontAwesomeIcon icon={icon.icon} className={cn('text-gray-500', className)} />;
}
