import { defineStore } from 'pinia';
import axios, { isAxiosError } from 'axios';
import type { CartSummary } from '@/types/cart';

const emptyCart: CartSummary = {
    lines: [],
    total: { amount: '0.00', currency: 'AUD' },
    itemCount: 0,
};

function extractErrorMessage(error: unknown): string {
    if (isAxiosError(error) && typeof error.response?.data?.message === 'string') {
        return error.response.data.message;
    }

    return 'Something went wrong updating your cart. Please try again.';
}

export const useCartStore = defineStore('cart', {
    state: () => ({
        summary: emptyCart as CartSummary,
        loading: false,
        error: null as string | null,
    }),
    actions: {
        hydrate(summary: CartSummary | null): void {
            this.summary = summary ?? emptyCart;
        },

        dismissError(): void {
            this.error = null;
        },

        /**
         * Re-fetches the summary straight from the server. Used after a
         * failed mutation so the UI reflects what the cart actually holds,
         * not a stale optimistic guess: the failed request may still have
         * partially applied server-side, or another tab may have changed
         * it in between.
         */
        async refresh(): Promise<void> {
            const response = await axios.get<CartSummary>('/storefront/cart-api/');
            this.summary = response.data;
        },

        async addItem(sku: string, quantity: number): Promise<void> {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.post<CartSummary>('/storefront/cart-api/items', { sku, quantity });
                this.summary = response.data;
            } catch (error) {
                this.error = extractErrorMessage(error);
                await this.refresh().catch(() => undefined);
            } finally {
                this.loading = false;
            }
        },

        async updateQuantity(sku: string, quantity: number): Promise<void> {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.patch<CartSummary>(
                    `/storefront/cart-api/items/${encodeURIComponent(sku)}`,
                    { quantity },
                );
                this.summary = response.data;
            } catch (error) {
                this.error = extractErrorMessage(error);
                await this.refresh().catch(() => undefined);
            } finally {
                this.loading = false;
            }
        },

        async removeItem(sku: string): Promise<void> {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.delete<CartSummary>(
                    `/storefront/cart-api/items/${encodeURIComponent(sku)}`,
                );
                this.summary = response.data;
            } catch (error) {
                this.error = extractErrorMessage(error);
                await this.refresh().catch(() => undefined);
            } finally {
                this.loading = false;
            }
        },
    },
});
