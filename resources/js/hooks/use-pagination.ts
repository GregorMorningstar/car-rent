import { useState } from 'react';

/**
 * Stronicowanie listy po stronie frontu.
 * Strona jest przycinana do zakresu, więc po zawężeniu listy (np. filtrem) nie zostaje pusta.
 */
export function usePagination<T>(items: T[], perPage: number) {
    const [page, setPage] = useState(1);
    const pages = Math.max(1, Math.ceil(items.length / perPage));
    const current = Math.min(page, pages);

    return {
        page: current,
        pages,
        setPage,
        total: items.length,
        from: items.length === 0 ? 0 : (current - 1) * perPage + 1,
        to: Math.min(current * perPage, items.length),
        items: items.slice((current - 1) * perPage, current * perPage),
    };
}
