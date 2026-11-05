/** Konto klienta z ClientRepository. */
export type Client = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string | null;
    reservationsCount: number;
    profile: {
        phone: string;
        street: string;
        building_number: string;
        apartment_number: string | null;
        postal_code: string;
        city: string;
    } | null;
};
