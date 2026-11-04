import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { allReservations } from '@/components/admin/data/reservations';
import ReservationsBrowser from '@/components/admin/reservations/reservations-browser';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';

export default function AdminReservations() {
    const current = allReservations().filter(
        (reservation) => reservation.state === 'pending' || reservation.state === 'confirmed',
    );

    return (
        <>
            <Head title="Aktualne rezerwacje" />
            <PageHeader
                title="Aktualne rezerwacje"
                description="Rezerwacje oczekujące na decyzję i potwierdzone, które jeszcze się nie zakończyły."
            />
            <ReservationsBrowser reservations={current} states={['pending', 'confirmed']} />
        </>
    );
}

AdminReservations.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
