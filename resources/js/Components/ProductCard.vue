<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import QuantityStepper from '@/Components/QuantityStepper.vue';
import { useCartStore } from '@/Stores/cart';
import { formatMoney } from '@/types/money';
import { hasPack, packQuantityLabel, pricePerLabel } from '@/utils/packSize';
import type { ProductSummary } from '@/types/catalog';

const props = defineProps<{
    product: ProductSummary;
}>();

const cartStore = useCartStore();
// Always 1: for a packed product this means 1 pack, not 1 piece, price
// and packSize together already say what that pack contains. Defaulting
// this to packSize used to make "quantity" look like a piece count.
const quantity = ref(1);
const adding = ref(false);

async function addToCart(): Promise<void> {
    adding.value = true;

    try {
        await cartStore.addItem(props.product.sku, quantity.value);
    } finally {
        adding.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col rounded-lg border border-line bg-surface p-4">
        <Link :href="`/storefront/products/${product.sku}`" class="block">
            <div class="mb-3 flex aspect-square items-center justify-center overflow-hidden rounded-md bg-surface-2">
                <img
                    v-if="product.imagePath"
                    :src="product.imagePath"
                    :alt="product.name"
                    class="h-full w-full object-contain"
                />
                <span v-else class="font-data text-xs text-ink-500">No image</span>
            </div>
            <span v-if="product.categoryName" class="font-data text-xs uppercase tracking-wide text-ink-500">
                {{ product.categoryName }}
            </span>
            <h3 class="font-display text-sm font-semibold text-ink-900">{{ product.name }}</h3>
        </Link>
        <p class="mt-1 font-data text-xs text-ink-500">
            {{ product.sku }} &middot; {{ packQuantityLabel(product.unitOfMeasure, product.packSize) }}
        </p>
        <p class="mt-2 font-data text-lg font-semibold text-ink-900">
            {{ formatMoney(product.listPrice) }}
            <span v-if="hasPack(product.packSize)" class="text-xs font-normal text-ink-500">{{ pricePerLabel(product.packSize) }}</span>
        </p>
        <div class="mt-3 flex items-center gap-2">
            <QuantityStepper v-model="quantity" :min="1" />
            <button
                type="button"
                class="flex-1 rounded-md bg-brand-600 px-3 py-1.5 font-display text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
                :disabled="adding"
                @click="addToCart"
            >
                {{ adding ? 'Adding...' : 'Add to cart' }}
            </button>
        </div>
    </div>
</template>
