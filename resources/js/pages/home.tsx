import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import BenefitsSection from '@/components/home/benefits-section';
import CarGrid from '@/components/home/car-grid';
import { reviews, slides } from '@/components/home/data';
import HeroSlider from '@/components/home/hero-slider';
import OfferBanner from '@/components/home/offer-banner';
import ReviewsSection from '@/components/home/reviews-section';
import SearchForm from '@/components/home/search-form';
import type { PublicCar, PublicEquipment } from '@/components/cars/car-card';
import type { MapLocation } from '@/components/home/location-picker';
import SiteLayout from '@/layouts/site-layout';

export default function Home({
    cars,
    equipment,
    pickupLocations,
    dropoffLocations,
}: {
    cars: PublicCar[];
    equipment: PublicEquipment[];
    pickupLocations: MapLocation[];
    dropoffLocations: MapLocation[];
}) {
    return (
        <>
            <Head title="Wynajem samochodów" />
            <h1 className="sr-only">Wynajem samochodów w Krośnie</h1>

            <HeroSlider slides={slides}>
                <SearchForm
                    equipment={equipment}
                    pickupLocations={pickupLocations}
                    dropoffLocations={dropoffLocations}
                />
            </HeroSlider>
            <BenefitsSection />
            <CarGrid cars={cars} />
            <OfferBanner />
            <ReviewsSection reviews={reviews} />
        </>
    );
}

Home.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
