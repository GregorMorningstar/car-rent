import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CarCard from '@/components/cars/car-card';
import type { PublicCar } from '@/components/cars/car-card';
import { buttonClass } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { home } from '@/routes';

export default function Reservation({ cars }: { cars: PublicCar[] }) {
    return (
        <>
            <Head title="Rezerwacja" />
            <div className="gutter border-b border-gray-200 bg-gray-50 py-12 sm:py-20">
                <h1 className="text-3xl font-bold text-balance text-gray-900 uppercase sm:text-5xl">Rezerwacja</h1>
            </div>

            <div className="gutter py-10 sm:py-14">
                {cars.length === 0 ? (
                    <div className="py-10 text-center">
                        <p className="mb-4 text-gray-600">Nie znaleźliśmy samochodów spełniających te kryteria.</p>
                        <Link href={home()} className={buttonClass('dark')}>
                            Zmień wyszukiwanie
                        </Link>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {cars.map((car) => (
                            <CarCard key={car.id} car={car} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Reservation.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
