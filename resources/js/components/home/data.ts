// Dane statyczne strony startowej - docelowo zostaną zastąpione danymi z backendu.

export type Slide = {
    title: string;
    text: string;
    background: string;
};

export const slides: Slide[] = [
    {
        title: 'Zarezerwuj auto szybko i wygodnie',
        text: 'Wybierz miejsce odbioru, termin i zarezerwuj samochód online. Sprawdź dostępne auta i dopasuj wynajem do swoich planów.',
        background: 'from-gray-900 via-gray-800 to-brand-900',
    },
    {
        title: 'Komfortowy wynajem samochodów w Krośnie',
        text: 'Sprawdzone auta, przejrzyste warunki wynajmu i ruszasz w drogę bez zbędnych formalności.',
        background: 'from-brand-900 via-gray-900 to-gray-800',
    },
    {
        title: 'Twoja podróż zaczyna się tutaj',
        text: 'Szybka rezerwacja, sprawdzone auta i pełne wsparcie na każdym etapie wynajmu.',
        background: 'from-gray-800 via-brand-950 to-gray-900',
    },
];

export type Review = {
    author: string;
    text: string;
};

export const reviews: Review[] = [
    {
        author: 'Tadeusz Matoga',
        text: 'W ostatnich dniach korzystałem z usług firmy Miś na trasie Gorlice – Warszawa i Warszawa – Gorlice. Jestem pod wrażeniem: super kierowcy, przyjemna jazda. Pozdrawiam!',
    },
    {
        author: 'Michał Niezgoda',
        text: 'Podróż odbyła się bardzo przyjemnie i bezpiecznie. Kierowca podchodził bardzo profesjonalnie do pasażerów.',
    },
    {
        author: 'Zbigniew G',
        text: 'Polecam. Żadnego problemu z zabraniem z innego miejsca, super kontakt.',
    },
];

export const helpPhone = '+48 000 000 000';

// Opcje filtrów wyszukiwarki ("Więcej opcji")
export const doorOptions = [
    { value: '2', label: '2 drzwi' },
    { value: '3', label: '3 drzwi' },
    { value: '4', label: '4 drzwi' },
    { value: '5', label: '5 drzwi' },
];

// Wartości zgodne z enumem App\Enums\VehicleType
export const vehicleTypes = [
    { value: 'passenger', label: 'Osobowe' },
    { value: 'van', label: 'Dostawcze' },
    { value: 'bus', label: 'Autobusy' },
    { value: 'tow_truck', label: 'Lawety' },
];

// Wartości zgodne z enumem App\Enums\FuelType
export const fuelTypes = [
    { value: 'petrol', label: 'Benzyna' },
    { value: 'diesel', label: 'Diesel' },
    { value: 'hybrid', label: 'Hybryda' },
    { value: 'electric', label: 'Elektryczny' },
];
