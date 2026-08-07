<script setup lang="ts">
import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
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
const siteLogoUrl = computed(() => page.props.siteLogoUrl);
const cartStore = useCartStore();
const { error: cartError } = storeToRefs(cartStore);

watch(
    () => page.props.cart,
    (cart) => cartStore.hydrate(cart),
    { immediate: true },
);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white">
        <header class="border-b border-line bg-surface/90 px-4 py-4 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
                <img v-if="siteLogoUrl" :src="siteLogoUrl" alt="Carewell Group" class="h-8 w-auto" />
                <span v-else class="font-display text-lg font-extrabold uppercase tracking-tight text-ink-900">
                    Carewell<span class="text-brand-600">.</span>
                </span>
                <SessionRail v-if="session" :session="session" />
            </div>
        </header>

        <div v-if="cartError" class="mx-auto mt-4 w-full max-w-6xl px-4">
            <p class="flex items-center justify-between rounded-xl border border-warn/30 bg-warn/10 px-4 py-3 text-sm text-warn">
                <span>{{ cartError }}</span>
                <button type="button" class="font-display font-semibold" @click="cartStore.dismissError()">
                    Dismiss
                </button>
            </p>
        </div>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <slot />
        </main>

        <StickyCartBar v-if="showCartBar && session" />
    </div>
</template>
