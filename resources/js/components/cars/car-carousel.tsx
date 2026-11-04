import { useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { cn } from '@/lib/utils';

const SWIPE_DISTANCE = 40;
const MAX_VISIBLE_OFFSET = 2;

/** Najkrótsza droga po kole, np. przy 5 zdjęciach ostatnie jest tuż przed pierwszym. */
function circularOffset(index: number, active: number, count: number) {
    const offset = (index - active + count) % count;

    return offset > count / 2 ? offset - count : offset;
}

function Arrow({ direction, onClick }: { direction: 'prev' | 'next'; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={direction === 'prev' ? 'Poprzednie zdjęcie' : 'Następne zdjęcie'}
            className={cn(
                'absolute top-1/2 z-20 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-900 shadow-lg ring-1 ring-black/10 transition-colors hover:bg-white',
                direction === 'prev' ? 'left-2 sm:left-4' : 'right-2 sm:right-4',
            )}
        >
            <svg className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d={direction === 'prev' ? 'm15 18-6-6 6-6' : 'm9 18 6-6-6-6'}
                />
            </svg>
        </button>
    );
}

/** Karuzela 3D: bieżące zdjęcie na środku, sąsiednie obrócone w perspektywie. */
export default function CarCarousel({ images, name }: { images: { id: number; url: string }[]; name: string }) {
    const [active, setActive] = useState(0);
    const swipeStart = useRef<number | null>(null);
    const swiped = useRef(false);
    const count = images.length;

    const go = (step: number) => setActive((current) => (current + step + count) % count);

    const onKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            go(-1);
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            go(1);
        }
    };

    const onPointerUp = (event: PointerEvent) => {
        if (swipeStart.current === null) {
            return;
        }

        const distance = event.clientX - swipeStart.current;
        swipeStart.current = null;

        if (Math.abs(distance) >= SWIPE_DISTANCE) {
            swiped.current = true;
            go(distance > 0 ? -1 : 1);
        }
    };

    if (count === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-2xl bg-gray-100 text-gray-500 sm:h-96">
                Brak zdjęć
            </div>
        );
    }

    return (
        <section aria-roledescription="karuzela" aria-label={`Zdjęcia: ${name}`}>
            <div
                tabIndex={count > 1 ? 0 : undefined}
                onKeyDown={onKeyDown}
                onPointerDown={(event) => {
                    swipeStart.current = event.clientX;
                    swiped.current = false;
                }}
                onPointerUp={onPointerUp}
                onPointerCancel={() => (swipeStart.current = null)}
                style={{ perspective: '1200px' }}
                className="relative h-56 touch-pan-y overflow-hidden rounded-2xl bg-linear-to-b from-gray-100 to-gray-200 select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:h-80 lg:h-[28rem]"
            >
                <div className="absolute inset-0" style={{ transformStyle: 'preserve-3d' }}>
                    {images.map((image, index) => {
                        const offset = circularOffset(index, active, count);
                        const distance = Math.abs(offset);
                        const visible = distance <= MAX_VISIBLE_OFFSET;

                        return (
                            <button
                                key={image.id}
                                type="button"
                                tabIndex={-1}
                                aria-hidden={offset !== 0}
                                aria-label={`Pokaż zdjęcie ${index + 1}`}
                                onClick={() => {
                                    // Po przeciągnięciu przeglądarka wysyła jeszcze kliknięcie, które cofnęłoby zdjęcie.
                                    if (!swiped.current) {
                                        setActive(index);
                                    }
                                }}
                                style={{
                                    transform: `translate(-50%, -50%) translateX(${offset * 42}%) translateZ(${distance * -220}px) rotateY(${offset * -38}deg)`,
                                    opacity: visible ? 1 - distance * 0.25 : 0,
                                    zIndex: 10 - distance,
                                    pointerEvents: visible ? 'auto' : 'none',
                                }}
                                className="absolute top-1/2 left-1/2 aspect-[392/250] h-[82%] max-w-[82%] cursor-pointer overflow-hidden rounded-xl bg-white shadow-2xl transition-[transform,opacity] duration-500 ease-out motion-reduce:transition-none"
                            >
                                <img
                                    src={image.url}
                                    alt={offset === 0 ? `${name} - zdjęcie ${index + 1} z ${count}` : ''}
                                    draggable={false}
                                    className="size-full object-cover"
                                />
                            </button>
                        );
                    })}
                </div>

                {count > 1 && (
                    <>
                        <Arrow direction="prev" onClick={() => go(-1)} />
                        <Arrow direction="next" onClick={() => go(1)} />
                    </>
                )}
            </div>

            {count > 1 && (
                <div className="mt-3 flex items-center justify-center gap-1">
                    {images.map((image, index) => (
                        <button
                            key={image.id}
                            type="button"
                            onClick={() => setActive(index)}
                            aria-label={`Zdjęcie ${index + 1}`}
                            aria-current={index === active}
                            className="flex size-6 items-center justify-center"
                        >
                            <span className={cn('size-2.5 rounded-full', index === active ? 'bg-brand-600' : 'bg-gray-300')} />
                        </button>
                    ))}
                </div>
            )}

            <p aria-live="polite" className="sr-only">
                Zdjęcie {active + 1} z {count}
            </p>
        </section>
    );
}
