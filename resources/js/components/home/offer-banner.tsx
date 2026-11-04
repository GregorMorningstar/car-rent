import { Link } from '@inertiajs/react';
import { buttonClass } from '@/components/ui/button';
import { cars, contact } from '@/routes';

export default function OfferBanner() {
    return (
        <section className="gutter">
            <div className="relative isolate overflow-hidden rounded-2xl bg-gray-900 px-5 py-10 text-white sm:rounded-3xl sm:px-12 sm:py-14 lg:py-20">
                <div
                    aria-hidden="true"
                    className="absolute -top-24 -right-24 -z-10 size-64 rounded-full bg-brand-600/30 blur-3xl sm:size-96"
                />
                <div className="max-w-2xl">
                    <p className="text-xs font-semibold tracking-widest text-brand-500 uppercase sm:text-sm">
                        Nasza oferta
                    </p>
                    <h2 className="mt-3 text-2xl leading-tight font-bold text-balance uppercase sm:text-4xl lg:text-5xl">
                        Komfortowy wynajem samochodów w Krośnie
                    </h2>
                    <p className="mt-4 text-pretty text-gray-300">
                        Wybierz sprawdzone auto, skorzystaj z przejrzystych
                        warunków wynajmu i ruszaj w drogę bez zbędnych
                        formalności.
                    </p>
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link href={cars()} className={buttonClass('primary', 'lg')}>
                            Zobacz auta
                        </Link>
                        <Link
                            href={contact()}
                            className={buttonClass(
                                'outline',
                                'lg',
                                'border-white/30 text-white hover:border-white hover:bg-white hover:text-gray-900',
                            )}
                        >
                            Skontaktuj się
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}
