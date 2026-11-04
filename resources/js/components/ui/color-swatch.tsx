import { cn } from '@/lib/utils';

/** Wzór koloru: kółko z obwódką, żeby jasne kolory były widoczne na białym tle. */
export default function ColorSwatch({ hex, className }: { hex?: string | null; className?: string }) {
    if (!hex) {
        return null;
    }

    return (
        <span
            aria-hidden="true"
            style={{ backgroundColor: hex }}
            className={cn('inline-block size-4 shrink-0 rounded-full ring-1 ring-black/15', className)}
        />
    );
}
