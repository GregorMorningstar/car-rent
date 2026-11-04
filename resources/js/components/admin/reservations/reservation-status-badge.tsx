import StatusBadge from '@/components/ui/status-badge';
import type { ReservationState } from '../data/labels';
import { reservationStates } from '../data/labels';

export default function ReservationStatusBadge({ state }: { state: ReservationState }) {
    return <StatusBadge {...reservationStates[state]} />;
}
