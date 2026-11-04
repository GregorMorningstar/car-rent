import type { ReactNode, SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';
import { inputClass } from './input';

export function FormField({
    label,
    id,
    hint,
    error,
    className,
    children,
}: {
    label: string;
    id: string;
    hint?: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <label htmlFor={id} className="text-sm font-medium text-gray-700">
                {label}
            </label>
            {children}
            {hint && !error && <p className="text-xs text-gray-500">{hint}</p>}
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    );
}

type Option = { value: string | number; label: string };

/** <select> ze strzałką w stylu pól formularza. */
export function Select({
    options,
    placeholder,
    className,
    ...props
}: { options: Option[]; placeholder?: string } & SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <div className="relative">
            <select className={cn(inputClass, 'truncate pr-10', className)} {...props}>
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <svg
                className="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-gray-400"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fillRule="evenodd"
                    d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z"
                    clipRule="evenodd"
                />
            </svg>
        </div>
    );
}
