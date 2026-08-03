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
        <div class="grid grid-cols-1 gap-6 md:grid-cols-[220px_1fr]">
            <aside>
                <CategoryTree :categories="categories" :active-category-id="categoryId" />
            </aside>

            <div>
                <form class="mb-6 flex gap-2" @submit.prevent="submitSearch">
                    <input
                        v-model="searchTerm"
                        type="search"
                        placeholder="Search products by name or SKU"
                        class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                    />
                    <button
                        type="submit"
                        class="rounded-md bg-ink-900 px-4 py-2 font-display text-sm font-semibold text-white"
                    >
                        Search
                    </button>
                </form>

                <div v-if="products.data.length === 0" class="rounded-lg border border-line bg-surface p-8 text-center">
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

                <nav v-if="products.last_page > 1" class="mt-6 flex flex-wrap gap-2">
                    <Link
                        v-for="link in products.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="[
                            'rounded-md px-3 py-1.5 font-data text-sm',
                            link.active ? 'bg-brand-600 text-white' : 'bg-surface text-ink-700',
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
