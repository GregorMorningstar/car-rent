import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CarForm from '@/components/admin/cars/car-form';
import type { CarOption, EditableCar } from '@/components/admin/cars/car-form';
import { carName } from '@/components/admin/data/cars';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';

export default function AdminCarEdit({
    car,
    equipment,
    colors,
    locations,
}: {
    car: EditableCar;
    equipment: CarOption[];
    colors: CarOption[];
    locations: CarOption[];
}) {
    return (
        <>
            <Head title={`Edycja: ${carName(car)}`} />
            <PageHeader title="Edytuj samochód" description={`${carName(car)} · ${car.registration_number}`} />
            <CarForm car={car} equipment={equipment} colors={colors} locations={locations} />
        </>
    );
}

AdminCarEdit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
