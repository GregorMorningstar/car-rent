import { Link } from '@inertiajs/react';
import CarCard from '@/components/cars/car-card';
import type { PublicCar } from '@/components/cars/car-card';
import SectionHeading from '@/components/home/section-heading';
import { buttonClass } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { cars as carsRoute } from '@/routes';

const MAX_COLUMNS = 4;

/**
 * Klasy siatki dla danej liczby kolumn (pełne nazwy, żeby Tailwind je wykrył).
 * 4 kolumny dopiero od xl - na lg karty byłyby za wąskie.
 */
const columnClasses: Record<number, string> = {
    1: 'sm:grid-cols-1',
    2: 'sm:grid-cols-2',
    3: 'sm:grid-cols-2 lg:grid-cols-3',
    4: 'sm:grid-cols-2 xl:grid-cols-4',
};

/** Najmniej rzędów przy max 4 w rzędzie, a potem równy podział aut na te rzędy (np. 6 → 3 + 3). */
function balancedColumns(count: number, max = MAX_COLUMNS) {
    const rows = Math.ceil(count / max);

    return Math.max(1, Math.ceil(count / Math.max(rows, 1)));
}

export default function CarGrid({ cars }: { cars: PublicCar[] }) {
    if (cars.length === 0) {
        return null;
    }

    const columns = balancedColumns(cars.length);

    return (
        <section className="py-14 sm:py-20">
            <SectionHeading
                className="gutter max-w-none"
                eyebrow="Twoja podróż zaczyna się tutaj"
                title="Wynajem auta w kilku prostych krokach"
                text="Sprawdź dostępne auta, wybierz termin i odbierz pojazd bez komplikacji."
            />

            {/* Telefon: pas przewijany palcem; od sm: siatka z równymi rzędami. */}
            <ul
                className={cn(
                    'gutter scrollbar-none mt-8 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto overscroll-x-contain pb-2 sm:mt-10 sm:grid sm:gap-6 sm:overflow-visible sm:pb-0',
                    columnClasses[columns],
                )}
            >
                {cars.map((car) => (
                    <li
                        key={car.id}
                        className="w-[82%] max-w-sm shrink-0 snap-start sm:w-auto sm:max-w-none"
                    >
                        <CarCard car={car} bookable />
                    </li>
                ))}
            </ul>

            <div className="gutter mt-8 flex justify-center sm:mt-10">
                <Link
                    href={carsRoute()}
                    className={buttonClass('outline', 'md', 'w-full sm:w-auto')}
                >
                    Zobacz wszystkie auta
                </Link>
            </div>
        </section>
    );
}
