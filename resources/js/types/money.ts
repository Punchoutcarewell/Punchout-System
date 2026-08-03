export interface Money {
    amount: string;
    currency: string;
}

export function formatMoney(money: Money): string {
    return new Intl.NumberFormat('en-AU', {
        style: 'currency',
        currency: money.currency,
    }).format(Number(money.amount));
}
