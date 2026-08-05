<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import CategoryTree from '@/Components/CategoryTree.vue';
import type { Paginated } from '@/types/pagination';
import type { CategorySummary, ProductSummary } from '@/types/catalog';

const props = defineProps<{
    products: Paginated<ProductSummary>;
    categories: CategorySummary[];
    query: string | null;
    categoryId: number | null;
}>();

const searchTerm = ref(props.query ?? '');

function submitSearch(): void {
    router.get(
        '/storefront',
        {
            q: searchTerm.value || undefined,
            category: props.categoryId ?? undefined,
        },
        { preserveState: true },
    );
}
</script>

<template>
    <StorefrontLayout>
        <div class="mb-8 text-center sm:text-left">
            <h1 class="font-display text-3xl font-extrabold uppercase tracking-tight text-ink-900 sm:text-4xl">
                Product Catalogue
            </h1>
            <p class="mt-2 text-sm text-ink-500">Browse the Carewell Group contract catalogue and add items to your cart.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-[240px_1fr]">
            <aside class="self-start rounded-2xl border border-line bg-surface p-4">
                <p class="mb-2 font-data text-[11px] font-semibold uppercase tracking-wide text-ink-500">Categories</p>
                <CategoryTree :categories="categories" :active-category-id="categoryId" />
            </aside>

            <div>
                <form class="mb-6 flex gap-2" @submit.prevent="submitSearch">
                    <div class="relative w-full">
                        <svg
                            class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500"
                            viewBox="0 0 20 20"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <circle cx="9" cy="9" r="6" />
                            <path d="m17 17-4-4" stroke-linecap="round" />
                        </svg>
                        <input
                            v-model="searchTerm"
                            type="search"
                            placeholder="Search products by name or SKU"
                            class="w-full rounded-full border border-line bg-surface py-2.5 pl-10 pr-4 text-sm focus:border-brand-600 focus:outline-none"
                        />
                    </div>
                    <button
                        type="submit"
                        class="shrink-0 rounded-full bg-ink-900 px-5 py-2.5 font-display text-sm font-semibold text-white transition hover:bg-black"
                    >
                        Search
                    </button>
                </form>

                <div v-if="products.data.length === 0" class="rounded-2xl border border-line bg-surface p-8 text-center">
                    <p class="font-display text-lg font-semibold text-ink-900">
                        No results<template v-if="query"> for "{{ query }}"</template>
                    </p>
                    <p class="mt-2 text-sm text-ink-500">
                        Try browsing a category from the sidebar, or search a UNSPSC classification code.
                    </p>
                    <Link href="/storefront" class="mt-4 inline-block font-display text-sm font-semibold text-brand-600">
                        Browse the full catalogue
                    </Link>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <ProductCard v-for="product in products.data" :key="product.id" :product="product" />
                </div>

                <nav v-if="products.last_page > 1" class="mt-8 flex flex-wrap justify-center gap-2">
                    <Link
                        v-for="link in products.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="[
                            'rounded-full px-3.5 py-1.5 font-data text-sm transition',
                            link.active ? 'bg-brand-600 text-white' : 'bg-surface text-ink-700 hover:bg-surface-2',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                        preserve-state
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </StorefrontLayout>
</template>
