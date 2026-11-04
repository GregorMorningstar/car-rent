import { cn } from '@/lib/utils';

const variants = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600',
    dark: 'bg-gray-900 text-white hover:bg-brand-600 focus-visible:outline-gray-900',
    outline:
        'border border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white focus-visible:outline-gray-900',
};

const sizes = {
    sm: 'h-10 px-4 text-sm',
    md: 'h-11 px-6 text-sm',
    lg: 'h-12 px-7 text-base',
};

/**
 * Klasy przycisku - do użycia na <button>, <a> i <Link>.
 */
export function buttonClass(
    variant: keyof typeof variants = 'primary',
    size: keyof typeof sizes = 'md',
    className?: string,
) {
    return cn(
        'inline-flex items-center justify-center gap-2 rounded-lg font-semibold whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-[0.98] disabled:opacity-50',
        variants[variant],
        sizes[size],
        className,
    );
}
