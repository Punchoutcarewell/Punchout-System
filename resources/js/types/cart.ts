import type { Money } from './money';

export interface CartLine {
    sku: string;
    description: string;
    quantity: number;
    unitPrice: Money;
    lineTotal: Money;
}

export interface CartSummary {
    lines: CartLine[];
    total: Money;
    itemCount: number;
}
