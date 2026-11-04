import { formatMoney } from '@/lib/format';
import { tierForDays } from '@/lib/pricing';
import type { PriceTier } from '@/lib/pricing';

/** Cena "od X zł", a przy znanej długości wynajmu stawka za dobę i suma. */
export default function RentalPrice({
    fromRate,
    prices,
    days,
    size = 'text-xl',
}: {
    fromRate: string;
    prices: PriceTier[];
    days?: number;
    size?: string;
}) {
    const tier = days ? tierForDays(prices, days) : undefined;

    if (!tier || !days) {
        return (
            <p className="text-sm text-gray-500">
                od <span className={`${size} font-bold text-gray-900`}>{formatMoney(Number(fromRate))}</span> / dzień
            </p>
        );
    }

    const rate = Number(tier.daily_rate);

    return (
        <div className="text-sm text-gray-500">
            <p>
                <span className={`${size} font-bold text-gray-900`}>{formatMoney(rate)}</span> / dzień
            </p>
            <p>
                Razem za {days === 1 ? '1 dzień' : `${days} dni`}: {formatMoney(rate * days)}
            </p>
        </div>
    );
}
