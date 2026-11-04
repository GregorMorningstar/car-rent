import { cn } from '@/lib/utils';

export default function StatusBadge({
    label,
    className,
}: {
    label: string;
    className: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                className,
            )}
        >
            {label}
        </span>
    );
}
