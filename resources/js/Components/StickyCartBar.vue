<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import { useCartStore } from '@/Stores/cart';
import { formatMoney } from '@/types/money';

const cartStore = useCartStore();
const { summary } = storeToRefs(cartStore);
</script>

<template>
    <div class="sticky bottom-0 z-10 border-t border-line bg-surface/95 shadow-[0_-4px_16px_rgba(17,24,39,0.06)] backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3">
            <span class="font-data text-sm text-ink-500">
                {{ summary.itemCount }} {{ summary.itemCount === 1 ? 'item' : 'items' }}
            </span>
            <span class="font-data text-lg font-bold text-ink-900">{{ formatMoney(summary.total) }}</span>
            <Link
                href="/storefront/cart"
                class="ml-auto rounded-full bg-brand-600 px-5 py-2 font-display text-sm font-semibold text-white transition hover:bg-brand-700"
            >
                Review cart
            </Link>
        </div>
    </div>
</template>
