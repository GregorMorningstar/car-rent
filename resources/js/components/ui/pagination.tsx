import { cn } from '@/lib/utils';

type Props = {
    page: number;
    pages: number;
    from: number;
    to: number;
    total: number;
    onChange: (page: number) => void;
};

function Arrow({ direction }: { direction: 'prev' | 'next' }) {
    return (
        <svg className="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d={direction === 'prev' ? 'm15 18-6-6 6-6' : 'm9 18 6-6-6-6'}
            />
        </svg>
    );
}

const buttonClass =
    'flex size-10 items-center justify-center rounded-lg text-sm font-medium text-gray-700 ring-1 ring-gray-200 transition-colors hover:bg-gray-100 disabled:pointer-events-none disabled:opacity-40';

export default function Pagination({ page, pages, from, to, total, onChange }: Props) {
    if (total === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Stronicowanie"
            className="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row"
        >
            <p className="text-sm text-gray-500">
                {from}–{to} z {total}
            </p>

            {pages > 1 && (
                <div className="flex items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => onChange(page - 1)}
                        disabled={page === 1}
                        aria-label="Poprzednia strona"
                        className={buttonClass}
                    >
                        <Arrow direction="prev" />
                    </button>

                    {Array.from({ length: pages }, (_, index) => index + 1).map((number) => (
                        <button
                            key={number}
                            type="button"
                            onClick={() => onChange(number)}
                            aria-current={number === page ? 'page' : undefined}
                            className={cn(
                                buttonClass,
                                number === page && 'bg-gray-900 text-white ring-gray-900 hover:bg-gray-900',
                            )}
                        >
                            {number}
                        </button>
                    ))}

                    <button
                        type="button"
                        onClick={() => onChange(page + 1)}
                        disabled={page === pages}
                        aria-label="Następna strona"
                        className={buttonClass}
                    >
                        <Arrow direction="next" />
                    </button>
                </div>
            )}
        </nav>
    );
}
