<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import QuantityStepper from '@/Components/QuantityStepper.vue';
import { useCartStore } from '@/Stores/cart';
import { formatMoney } from '@/types/money';

const cartStore = useCartStore();
const { summary } = storeToRefs(cartStore);
const transferring = ref(false);

function onQuantityChange(sku: string, quantity: number): void {
    cartStore.updateQuantity(sku, quantity);
}

function removeLine(sku: string): void {
    cartStore.removeItem(sku);
}

function transferToCoupa(): void {
    transferring.value = true;
    router.post('/storefront/transfer', {}, { onFinish: () => (transferring.value = false) });
}
</script>

<template>
    <StorefrontLayout :show-cart-bar="false">
        <h1 class="font-display text-2xl font-bold text-ink-900">Your cart</h1>

        <div v-if="summary.lines.length === 0" class="mt-6 rounded-lg border border-line bg-surface p-8 text-center">
            <p class="font-display text-lg font-semibold text-ink-900">Nothing in the cart yet</p>
            <Link href="/storefront" class="mt-3 inline-block font-display text-sm font-semibold text-brand-600">
                Browse the catalogue
            </Link>
        </div>

        <div v-else class="mt-6 overflow-hidden rounded-lg border border-line bg-surface">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line bg-surface-2 font-display text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-4 py-2">Item</th>
                        <th class="px-4 py-2">Unit price</th>
                        <th class="px-4 py-2">Quantity</th>
                        <th class="px-4 py-2">Line total</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in summary.lines" :key="line.sku" class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <p class="font-display font-semibold text-ink-900">{{ line.description }}</p>
                            <p class="font-data text-xs text-ink-500">{{ line.sku }}</p>
                        </td>
                        <td class="px-4 py-3 font-data">{{ formatMoney(line.unitPrice) }}</td>
                        <td class="px-4 py-3">
                            <QuantityStepper
                                :model-value="line.quantity"
                                :min="1"
                                :disabled="cartStore.loading"
                                @update:model-value="(quantity) => onQuantityChange(line.sku, quantity)"
                            />
                        </td>
                        <td class="px-4 py-3 font-data font-semibold">{{ formatMoney(line.lineTotal) }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="font-data text-xs text-warn"
                                :disabled="cartStore.loading"
                                @click="removeLine(line.sku)"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="flex items-center justify-between border-t border-line bg-surface-2 px-4 py-4">
                <span class="font-data text-sm text-ink-500">{{ summary.itemCount }} items</span>
                <span class="font-data text-xl font-semibold text-ink-900">{{ formatMoney(summary.total) }}</span>
            </div>
        </div>

        <button
            v-if="summary.lines.length > 0"
            type="button"
            class="mt-6 w-full rounded-md bg-brand-600 px-5 py-3 font-display text-base font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
            :disabled="transferring"
            @click="transferToCoupa"
        >
            {{ transferring ? 'Transferring...' : 'Transfer cart to Coupa' }}
        </button>
    </StorefrontLayout>
</template>
