import { defineStore } from 'pinia';
import axios from 'axios';
import type { CartSummary } from '@/types/cart';

const emptyCart: CartSummary = {
    lines: [],
    total: { amount: '0.00', currency: 'AUD' },
    itemCount: 0,
};

export const useCartStore = defineStore('cart', {
    state: () => ({
        summary: emptyCart as CartSummary,
        loading: false,
    }),
    actions: {
        hydrate(summary: CartSummary | null): void {
            this.summary = summary ?? emptyCart;
        },

        async addItem(sku: string, quantity: number): Promise<void> {
            this.loading = true;

            try {
                const response = await axios.post<CartSummary>('/storefront/cart-api/items', { sku, quantity });
                this.summary = response.data;
            } finally {
                this.loading = false;
            }
        },

        async updateQuantity(sku: string, quantity: number): Promise<void> {
            this.loading = true;

            try {
                const response = await axios.patch<CartSummary>(`/storefront/cart-api/items/${sku}`, { quantity });
                this.summary = response.data;
            } finally {
                this.loading = false;
            }
        },

        async removeItem(sku: string): Promise<void> {
            this.loading = true;

            try {
                const response = await axios.delete<CartSummary>(`/storefront/cart-api/items/${sku}`);
                this.summary = response.data;
            } finally {
                this.loading = false;
            }
        },
    },
});
