import { cn } from '@/lib/utils';
import EquipmentIcon from './equipment-icon';
import type { IconSvg } from './equipment-icon';

/** Wyposażenie samochodu: ikona i nazwa. */
export default function EquipmentList({
    equipment,
    className,
}: {
    equipment: { id: number; name: string; icon?: string | null; icon_svg?: IconSvg | null }[];
    className?: string;
}) {
    if (equipment.length === 0) {
        return null;
    }

    return (
        <ul aria-label="Wyposażenie" className={cn('flex flex-wrap gap-x-3 gap-y-1.5 text-sm text-gray-600', className)}>
            {equipment.map((item) => (
                <li key={item.id} className="flex items-center gap-1.5">
                    <EquipmentIcon name={item.icon} svg={item.icon_svg} className="size-4" />
                    {item.name}
                </li>
            ))}
        </ul>
    );
}
