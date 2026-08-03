<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    reason: string;
}>();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-brand-050 px-4 text-center">
        <p class="font-display text-2xl font-bold text-ink-900">We could not transfer your cart</p>
        <p class="mt-3 max-w-md text-sm text-ink-700">{{ reason }}</p>
        <Link
            href="/storefront/cart"
            class="mt-6 rounded-md bg-brand-600 px-5 py-2 font-display text-sm font-semibold text-white transition hover:bg-brand-700"
        >
            Back to cart
        </Link>
    </div>
</template>
