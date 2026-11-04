import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CarForm from '@/components/admin/cars/car-form';
import type { CarOption } from '@/components/admin/cars/car-form';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';

export default function AdminCarCreate({
    equipment,
    colors,
    locations,
}: {
    equipment: CarOption[];
    colors: CarOption[];
    locations: CarOption[];
}) {
    return (
        <>
            <Head title="Dodaj samochód" />
            <PageHeader title="Dodaj samochód" description="Nowy pojazd we flocie wypożyczalni." />
            <CarForm equipment={equipment} colors={colors} locations={locations} />
        </>
    );
}

AdminCarCreate.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
