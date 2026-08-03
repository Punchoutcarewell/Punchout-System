import type { CartSummary } from './cart';
import type { PunchoutSessionRail } from './punchout';

export interface SharedPageProps {
    punchoutSession: PunchoutSessionRail | null;
    cart: CartSummary | null;
    [key: string]: unknown;
}
