const benefits = [
    {
        title: 'Szybka rezerwacja',
        text: 'Wybierz miejsce, termin i auto – rezerwację zrobisz online w kilka minut.',
        icon: 'M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
    },
    {
        title: 'Sprawdzone auta',
        text: 'Regularnie serwisowane samochody, gotowe do drogi w każdej chwili.',
        icon: 'M9 12.75 11.25 15 15 9.75M12 2.7l7.5 3v6c0 4.5-3.2 8.4-7.5 9.6-4.3-1.2-7.5-5.1-7.5-9.6v-6l7.5-3Z',
    },
    {
        title: 'Pełne wsparcie',
        text: 'Pomożemy na każdym etapie wynajmu – od rezerwacji po zwrot auta.',
        icon: 'M2.25 6.75c0 8.28 6.72 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.37c0-.52-.35-.97-.85-1.09l-4.42-1.1c-.44-.11-.9.05-1.17.41l-.97 1.29a1.13 1.13 0 0 1-1.21.38 12.04 12.04 0 0 1-7.14-7.14 1.13 1.13 0 0 1 .38-1.21l1.29-.97c.36-.27.52-.73.41-1.17l-1.1-4.42a1.13 1.13 0 0 0-1.09-.85H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z',
    },
];

export default function BenefitsSection() {
    return (
        <section className="gutter border-b border-gray-200 bg-gray-50 py-10 sm:py-14">
            <ul className="grid gap-6 sm:gap-8 md:grid-cols-3">
                {benefits.map((benefit) => (
                    <li key={benefit.title} className="flex gap-4">
                        <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-600/10 text-brand-600">
                            <svg
                                className="size-6"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={1.5}
                                aria-hidden="true"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d={benefit.icon}
                                />
                            </svg>
                        </span>
                        <div>
                            <h3 className="font-semibold text-gray-900">
                                {benefit.title}
                            </h3>
                            <p className="mt-1 text-sm text-pretty text-gray-600">
                                {benefit.text}
                            </p>
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
