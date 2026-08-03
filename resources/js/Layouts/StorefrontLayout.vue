<script setup lang="ts">
import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SessionRail from '@/Components/SessionRail.vue';
import StickyCartBar from '@/Components/StickyCartBar.vue';
import { useCartStore } from '@/Stores/cart';
import type { SharedPageProps } from '@/types/inertia';

withDefaults(
    defineProps<{
        showCartBar?: boolean;
    }>(),
    { showCartBar: true },
);

const page = usePage<SharedPageProps>();
const session = computed(() => page.props.punchoutSession);
const cartStore = useCartStore();

watch(
    () => page.props.cart,
    (cart) => cartStore.hydrate(cart),
    { immediate: true },
);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-brand-050">
        <header class="border-b border-line bg-surface px-4 py-3">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
                <span class="font-display text-lg font-bold text-ink-900">Carewell Group</span>
                <SessionRail v-if="session" :session="session" />
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
            <slot />
        </main>

        <StickyCartBar v-if="showCartBar && session" />
    </div>
</template>
