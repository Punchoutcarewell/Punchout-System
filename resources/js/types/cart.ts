import type { Money } from './money';

export interface CartLine {
    sku: string;
    description: string;
    imagePath: string | null;
    quantity: number;
    unitPrice: Money;
    lineTotal: Money;
    /** Null means this line is not sold in packs: quantity is a plain unit count. */
    packSize: number | null;
    unitOfMeasure: string | null;
}

export interface CartSummary {
    lines: CartLine[];
    total: Money;
    itemCount: number;
}
