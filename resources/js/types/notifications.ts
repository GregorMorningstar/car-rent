/** Nowe zamówienie w chmurce - dane z ReservationRepository::latestUnseen. */
export type NewOrder = {
    id: number;
    client: string | null;
    car: string;
    from: string;
    to: string;
    total_price: string;
    created_at: string | null;
};

/** Rezerwacja usunięta przez klienta - dane z ReservationRepository::latestRemoved. */
export type RemovedOrder = {
    id: number;
    client: string | null;
    car: string;
    from: string;
    to: string;
    was_confirmed: boolean;
    removed_at: string | null;
};

export type Notifications = {
    messages: number;
    reservations: {
        count: number;
        items: NewOrder[];
        removed: { count: number; items: RemovedOrder[] };
    } | null;
};
