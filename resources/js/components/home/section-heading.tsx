import { cn } from '@/lib/utils';

type Props = {
    eyebrow?: string;
    title: string;
    text?: string;
    align?: 'left' | 'center';
    className?: string;
};

export default function SectionHeading({
    eyebrow,
    title,
    text,
    align = 'left',
    className,
}: Props) {
    return (
        <div
            className={cn(
                'max-w-2xl',
                align === 'center' && 'mx-auto text-center',
                className,
            )}
        >
            {eyebrow && (
                <p className="text-xs font-semibold tracking-widest text-brand-600 uppercase sm:text-sm">
                    {eyebrow}
                </p>
            )}
            <h2 className="mt-2 text-2xl leading-tight font-bold text-balance text-gray-900 uppercase sm:text-3xl lg:text-4xl">
                {title}
            </h2>
            {text && (
                <p className="mt-3 text-base text-pretty text-gray-600">{text}</p>
            )}
        </div>
    );
}
