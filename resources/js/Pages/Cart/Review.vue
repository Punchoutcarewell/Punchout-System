<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import QuantityStepper from '@/Components/QuantityStepper.vue';
import { useCartStore } from '@/Stores/cart';
import { formatMoney } from '@/types/money';
import { hasPack, pricePerLabel, totalPiecesLabel } from '@/utils/packSize';

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
        <h1 class="font-display text-3xl font-extrabold uppercase tracking-tight text-ink-900">Your Cart</h1>

        <div v-if="summary.lines.length === 0" class="mt-6 rounded-2xl border border-line bg-surface p-8 text-center">
            <p class="font-display text-lg font-semibold text-ink-900">Nothing in the cart yet</p>
            <Link href="/storefront" class="mt-3 inline-block font-display text-sm font-semibold text-brand-600">
                Browse the catalogue
            </Link>
        </div>

        <div v-else class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line bg-surface-2 font-display text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Unit price</th>
                        <th class="px-4 py-3">Quantity</th>
                        <th class="px-4 py-3">Line total</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in summary.lines" :key="line.sku" class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface-2">
                                    <img
                                        v-if="line.imagePath"
                                        :src="line.imagePath"
                                        :alt="line.description"
                                        class="h-full w-full object-contain p-1.5"
                                    />
                                    <span v-else class="font-data text-[10px] text-ink-500">No image</span>
                                </div>
                                <div>
                                    <p class="font-display font-semibold text-ink-900">{{ line.description }}</p>
                                    <p class="font-data text-xs text-ink-500">{{ line.sku }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-data">
                            {{ formatMoney(line.unitPrice) }}
                            <span v-if="hasPack(line.packSize)" class="block text-xs text-ink-500">{{ pricePerLabel(line.packSize) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <QuantityStepper
                                :model-value="line.quantity"
                                :min="1"
                                :disabled="cartStore.loading"
                                @update:model-value="(quantity) => onQuantityChange(line.sku, quantity)"
                            />
                            <p v-if="hasPack(line.packSize)" class="mt-1 font-data text-xs text-ink-500">
                                {{ totalPiecesLabel(line.quantity, line.packSize) }}
                            </p>
                        </td>
                        <td class="px-4 py-3 font-data font-semibold text-ink-900">{{ formatMoney(line.lineTotal) }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-full text-ink-500 transition hover:bg-warn/10 hover:text-warn disabled:opacity-40"
                                :disabled="cartStore.loading"
                                aria-label="Remove item"
                                @click="removeLine(line.sku)"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <path d="M4 6h12" stroke-linecap="round" />
                                    <path d="M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6" stroke-linecap="round" stroke-linejoin="round" />
                                    <path
                                        d="M5.5 6l.6 9.2a1.5 1.5 0 0 0 1.5 1.4h4.8a1.5 1.5 0 0 0 1.5-1.4l.6-9.2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path d="M8.3 9v4.5M11.7 9v4.5" stroke-linecap="round" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="flex items-center justify-between border-t border-line bg-surface-2 px-4 py-4">
                <span class="font-data text-sm text-ink-500">{{ summary.itemCount }} items</span>
                <span class="font-data text-xl font-bold text-ink-900">{{ formatMoney(summary.total) }}</span>
            </div>
        </div>

        <button
            v-if="summary.lines.length > 0"
            type="button"
            class="mt-6 w-full rounded-full bg-brand-600 px-5 py-3.5 font-display text-base font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
            :disabled="transferring"
            @click="transferToCoupa"
        >
            {{ transferring ? 'Transferring...' : 'Transfer cart to Coupa' }}
        </button>
    </StorefrontLayout>
</template>
