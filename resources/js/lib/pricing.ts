export type PriceTier = { id?: number; min_days: number; max_days: number | null; daily_rate: string };

const distance = new Intl.NumberFormat('pl-PL');

/** Limit przebiegu, null to brak limitu. */
export const kmLimitLabel = (limit: number | null) =>
    limit === null ? 'Bez limitu km' : `Limit ${distance.format(limit)} km / dzień`;

/** Próg obowiązujący przy tej długości wynajmu. */
export const tierForDays = (prices: PriceTier[], days: number) =>
    prices.find((price) => price.min_days <= days && (price.max_days === null || price.max_days >= days));

/** 1 dzień, 2-3 dni, 4+ dni. */
export const tierLabel = ({ min_days: min, max_days: max }: Pick<PriceTier, 'min_days' | 'max_days'>) => {
    if (max === null) {
        return `${min}+ dni`;
    }

    if (min === max) {
        return min === 1 ? '1 dzień' : `${min} dni`;
    }

    return `${min}-${max} dni`;
};
