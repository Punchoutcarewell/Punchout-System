<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import QuantityStepper from '@/Components/QuantityStepper.vue';
import { useCartStore } from '@/Stores/cart';
import { formatMoney } from '@/types/money';
import { hasPack, pricePerLabel, totalPiecesLabel } from '@/utils/packSize';
import type { Money } from '@/types/money';
import type { ProductDetail } from '@/types/catalog';

const props = defineProps<{
    product: ProductDetail;
    contractPrice: Money;
}>();

const cartStore = useCartStore();
// Always 1: for a packed product this means 1 pack, not 1 piece, see
// ProductCard.vue's own note on the same default.
const quantity = ref(1);
const adding = ref(false);
const added = ref(false);

const piecesLabel = computed(() => totalPiecesLabel(quantity.value, props.product.packSize));

async function addToCart(): Promise<void> {
    adding.value = true;
    added.value = false;

    try {
        await cartStore.addItem(props.product.sku, quantity.value);
        added.value = true;
    } finally {
        adding.value = false;
    }
}
</script>

<template>
    <StorefrontLayout>
        <Link href="/storefront" class="font-data text-sm text-ink-500 transition hover:text-ink-900">&larr; Back to catalogue</Link>

        <div class="mt-4 grid grid-cols-1 gap-10 md:grid-cols-2">
            <div class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-surface-2">
                <img
                    v-if="product.imagePath"
                    :src="product.imagePath"
                    :alt="product.name"
                    class="h-full w-full object-contain p-8"
                />
                <span v-else class="font-data text-sm text-ink-500">No image available</span>
            </div>

            <div>
                <span v-if="product.categoryName" class="font-data text-[11px] font-semibold uppercase tracking-wide text-brand-600">
                    {{ product.categoryName }}
                </span>
                <h1 class="font-display text-2xl font-extrabold text-ink-900 sm:text-3xl">{{ product.name }}</h1>
                <p class="mt-1 font-data text-sm text-ink-500">
                    SKU {{ product.sku }} &middot; UNSPSC {{ product.unspscCode }}
                </p>

                <p class="mt-5 font-data text-3xl font-bold text-ink-900">
                    {{ formatMoney(contractPrice) }}
                    <span v-if="hasPack(product.packSize)" class="text-base font-normal text-ink-500">{{ pricePerLabel(product.packSize) }}</span>
                </p>
                <p
                    v-if="contractPrice.amount !== product.listPrice.amount"
                    class="mt-1 font-data text-sm text-ink-500 line-through"
                >
                    {{ formatMoney(product.listPrice) }}
                </p>

                <p class="mt-4 text-sm text-ink-700">{{ product.description }}</p>
                <p v-if="product.longDescription" class="mt-2 text-sm text-ink-700">{{ product.longDescription }}</p>

                <dl class="mt-5 grid grid-cols-2 gap-y-2 rounded-xl border border-line bg-surface p-4 font-data text-sm">
                    <dt class="text-ink-500">Unit of measure</dt>
                    <dd class="text-ink-900">{{ product.unitOfMeasure }}</dd>
                    <template v-if="hasPack(product.packSize)">
                        <dt class="text-ink-500">Pack size</dt>
                        <dd class="text-ink-900">{{ product.packSize }}</dd>
                    </template>
                    <dt class="text-ink-500">Lead time</dt>
                    <dd class="text-ink-900">{{ product.leadTimeDays }} days</dd>
                    <dt v-if="product.manufacturerName" class="text-ink-500">Manufacturer</dt>
                    <dd v-if="product.manufacturerName" class="text-ink-900">{{ product.manufacturerName }}</dd>
                    <dt v-if="product.manufacturerPartId" class="text-ink-500">Manufacturer part</dt>
                    <dd v-if="product.manufacturerPartId" class="text-ink-900">{{ product.manufacturerPartId }}</dd>
                </dl>

                <div class="mt-6 flex items-center gap-3">
                    <QuantityStepper v-model="quantity" :min="1" />
                    <button
                        type="button"
                        class="rounded-full bg-brand-600 px-6 py-2.5 font-display text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
                        :disabled="adding"
                        @click="addToCart"
                    >
                        {{ adding ? 'Adding...' : 'Add to cart' }}
                    </button>
                    <span v-if="added" class="font-data text-sm text-ok">Added</span>
                </div>
                <p v-if="piecesLabel" class="mt-2 font-data text-xs text-ink-500">{{ piecesLabel }}</p>
            </div>
        </div>
    </StorefrontLayout>
</template>
