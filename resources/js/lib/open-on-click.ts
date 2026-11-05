import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';

/**
 * Kliknięcie w kafelek lub wiersz otwiera szczegóły. Przyciski, linki i pola działają po swojemu,
 * a zaznaczanie tekstu niczego nie otwiera.
 */
export const openOnClick = (url: string) => (event: MouseEvent<HTMLElement>) => {
    const target = event.target as HTMLElement;

    if (target.closest('a, button, input, select, textarea, label, form') || window.getSelection()?.toString()) {
        return;
    }

    router.visit(url);
};
