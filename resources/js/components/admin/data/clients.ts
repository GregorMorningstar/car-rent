// Przykładowi klienci - pola jak w tabelach users + user_profiles.

export type Client = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    profile: {
        phone: string;
        street: string;
        building_number: string;
        apartment_number: string | null;
        postal_code: string;
        city: string;
    } | null;
};

export const clients: Client[] = [
    {
        id: 2,
        name: 'Anna Kowalska',
        email: 'anna.kowalska@example.com',
        email_verified_at: '2026-02-11T09:12:00',
        created_at: '2026-02-11T09:05:00',
        profile: { phone: '+48 601 234 567', street: 'Lwowska', building_number: '14', apartment_number: '3', postal_code: '38-400', city: 'Krosno' },
    },
    {
        id: 3,
        name: 'Piotr Nowak',
        email: 'piotr.nowak@example.com',
        email_verified_at: '2026-03-02T18:40:00',
        created_at: '2026-03-02T18:31:00',
        profile: { phone: '+48 602 345 678', street: 'Grunwaldzka', building_number: '7', apartment_number: null, postal_code: '38-300', city: 'Gorlice' },
    },
    {
        id: 4,
        name: 'Katarzyna Wiśniewska',
        email: 'k.wisniewska@example.com',
        email_verified_at: '2026-04-19T12:00:00',
        created_at: '2026-04-19T11:52:00',
        profile: { phone: '+48 603 456 789', street: 'Piłsudskiego', building_number: '22', apartment_number: '15', postal_code: '35-001', city: 'Rzeszów' },
    },
    {
        id: 5,
        name: 'Tomasz Wójcik',
        email: 'tomasz.wojcik@example.com',
        email_verified_at: '2026-05-08T07:25:00',
        created_at: '2026-05-08T07:20:00',
        profile: { phone: '+48 604 567 890', street: 'Bieszczadzka', building_number: '3', apartment_number: null, postal_code: '38-500', city: 'Sanok' },
    },
    {
        id: 6,
        name: 'Magdalena Lewandowska',
        email: 'magda.lewandowska@example.com',
        email_verified_at: '2026-06-14T20:10:00',
        created_at: '2026-06-14T20:02:00',
        profile: { phone: '+48 605 678 901', street: 'Podkarpacka', building_number: '45', apartment_number: '8', postal_code: '38-400', city: 'Krosno' },
    },
    {
        id: 7,
        name: 'Michał Zieliński',
        email: 'michal.zielinski@example.com',
        email_verified_at: null,
        created_at: '2026-10-29T16:45:00',
        profile: null,
    },
    {
        id: 8,
        name: 'Joanna Szymańska',
        email: 'joanna.szymanska@example.com',
        email_verified_at: '2026-08-21T10:30:00',
        created_at: '2026-08-21T10:21:00',
        profile: { phone: '+48 607 890 123', street: 'Kościuszki', building_number: '9', apartment_number: '2', postal_code: '38-200', city: 'Jasło' },
    },
    {
        id: 9,
        name: 'Grzegorz Dąbrowski',
        email: 'g.dabrowski@example.com',
        email_verified_at: '2026-09-30T13:15:00',
        created_at: '2026-09-30T13:09:00',
        profile: { phone: '+48 608 901 234', street: 'Legionów', building_number: '31', apartment_number: null, postal_code: '38-400', city: 'Krosno' },
    },
];

export const findClient = (id: number) => clients.find((client) => client.id === id);

/** Adres w jednej linii, np. "Lwowska 14/3, 38-400 Krosno". */
export const formatAddress = (profile: NonNullable<Client['profile']>) =>
    `${profile.street} ${profile.building_number}${profile.apartment_number ? `/${profile.apartment_number}` : ''}, ${profile.postal_code} ${profile.city}`;
