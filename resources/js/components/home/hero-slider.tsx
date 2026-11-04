import { useCallback, useEffect, useRef, useState } from 'react';
import type { ReactNode, TouchEvent } from 'react';
import { cn } from '@/lib/utils';
import type { Slide } from './data';

type Props = {
    slides: Slide[];
    interval?: number;
    children?: ReactNode;
};

/** Minimalna długość przesunięcia palcem (px), która zmienia slajd. */
const SWIPE_THRESHOLD = 50;

function ArrowButton({
    direction,
    onClick,
}: {
    direction: 'prev' | 'next';
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={direction === 'prev' ? 'Poprzedni slajd' : 'Następny slajd'}
            className={cn(
                'absolute top-40 z-10 hidden size-12 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/20 backdrop-blur transition-colors hover:bg-white/25 xl:flex',
                direction === 'prev' ? 'left-6' : 'right-6',
            )}
        >
            <svg
                className="size-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth={2}
                aria-hidden="true"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d={direction === 'prev' ? 'm15 18-6-6 6-6' : 'm9 18 6-6-6-6'}
                />
            </svg>
        </button>
    );
}

export default function HeroSlider({ slides, interval = 6000, children }: Props) {
    const [current, setCurrent] = useState(0);
    const [paused, setPaused] = useState(false);
    const touchStart = useRef<{ x: number; y: number } | null>(null);

    const goTo = useCallback(
        (index: number) =>
            setCurrent((index + slides.length) % slides.length),
        [slides.length],
    );

    useEffect(() => {
        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        if (paused || reducedMotion || slides.length < 2) {
            return;
        }

        const timer = window.setTimeout(() => goTo(current + 1), interval);

        return () => window.clearTimeout(timer);
    }, [current, paused, interval, goTo, slides.length]);

    const onTouchStart = (event: TouchEvent) => {
        const touch = event.touches[0]!;
        touchStart.current = { x: touch.clientX, y: touch.clientY };
        setPaused(true);
    };

    const onTouchEnd = (event: TouchEvent) => {
        const start = touchStart.current;
        const touch = event.changedTouches[0]!;
        touchStart.current = null;
        setPaused(false);

        if (!start) {
            return;
        }

        const deltaX = touch.clientX - start.x;
        const deltaY = touch.clientY - start.y;

        // Tylko wyraźnie poziomy ruch - pionowy to przewijanie strony.
        if (Math.abs(deltaX) > SWIPE_THRESHOLD && Math.abs(deltaX) > Math.abs(deltaY)) {
            goTo(current + (deltaX < 0 ? 1 : -1));
        }
    };

    return (
        <section
            aria-roledescription="karuzela"
            aria-label="Wyróżnione"
            className="relative isolate overflow-hidden bg-gray-900 text-white"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
        >
            {slides.map((slide, index) => (
                <div
                    key={slide.title}
                    aria-hidden="true"
                    className={cn(
                        'absolute inset-0 -z-10 bg-linear-to-br transition-opacity duration-700',
                        slide.background,
                        index === current ? 'opacity-100' : 'opacity-0',
                    )}
                />
            ))}

            <div
                aria-hidden="true"
                className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_85%_15%,rgb(255_255_255/0.08),transparent_50%)]"
            />

            <div className="gutter pt-12 pb-10 sm:pt-16 sm:pb-14 lg:pt-24 lg:pb-20">
                <div
                    className="grid touch-pan-y"
                    onTouchStart={onTouchStart}
                    onTouchEnd={onTouchEnd}
                >
                    {slides.map((slide, index) => (
                        <div
                            key={slide.title}
                            role="group"
                            aria-roledescription="slajd"
                            aria-label={`${index + 1} z ${slides.length}`}
                            aria-hidden={index !== current}
                            className={cn(
                                'col-start-1 row-start-1 max-w-3xl transition-all duration-700 motion-reduce:transition-none',
                                index === current
                                    ? 'translate-y-0 opacity-100'
                                    : 'pointer-events-none translate-y-4 opacity-0',
                            )}
                        >
                            <h2 className="text-3xl leading-tight font-bold tracking-tight text-balance uppercase sm:text-5xl lg:text-6xl">
                                {slide.title}
                            </h2>
                            <p className="mt-4 max-w-2xl text-base text-pretty text-gray-300 sm:mt-5 sm:text-lg">
                                {slide.text}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="mt-6 flex sm:mt-8">
                    {slides.map((slide, index) => (
                        <button
                            key={slide.title}
                            type="button"
                            onClick={() => goTo(index)}
                            aria-label={`Przejdź do slajdu ${index + 1}`}
                            aria-current={index === current}
                            className="group flex h-8 items-center px-1"
                        >
                            <span
                                className={cn(
                                    'h-1.5 rounded-full transition-all',
                                    index === current
                                        ? 'w-10 bg-brand-500'
                                        : 'w-5 bg-white/40 group-hover:bg-white/70',
                                )}
                            />
                        </button>
                    ))}
                </div>

                {children && <div className="mt-6 sm:mt-10">{children}</div>}
            </div>

            <ArrowButton direction="prev" onClick={() => goTo(current - 1)} />
            <ArrowButton direction="next" onClick={() => goTo(current + 1)} />
        </section>
    );
}
