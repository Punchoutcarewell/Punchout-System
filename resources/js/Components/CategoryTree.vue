<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { CategorySummary } from '@/types/catalog';

interface CategoryNode extends CategorySummary {
    children: CategoryNode[];
}

const props = defineProps<{
    categories: CategorySummary[];
    activeCategoryId: number | null;
}>();

const tree = computed<CategoryNode[]>(() => {
    const nodes = new Map<number, CategoryNode>();

    for (const category of props.categories) {
        nodes.set(category.id, { ...category, children: [] });
    }

    const roots: CategoryNode[] = [];

    for (const node of nodes.values()) {
        if (node.parentId !== null && nodes.has(node.parentId)) {
            nodes.get(node.parentId)!.children.push(node);
        } else {
            roots.push(node);
        }
    }

    return roots;
});

function categoryHref(categoryId: number): string {
    return `/storefront?category=${categoryId}`;
}
</script>

<template>
    <nav aria-label="Categories">
        <ul class="space-y-1">
            <li v-for="node in tree" :key="node.id">
                <Link
                    :href="categoryHref(node.id)"
                    class="block rounded-md px-2 py-1 font-display text-sm transition"
                    :class="activeCategoryId === node.id ? 'bg-brand-100 text-brand-700' : 'text-ink-700 hover:bg-surface-2'"
                >
                    {{ node.name }}
                </Link>
                <ul v-if="node.children.length" class="ml-3 mt-1 space-y-1 border-l border-line pl-3">
                    <li v-for="child in node.children" :key="child.id">
                        <Link
                            :href="categoryHref(child.id)"
                            class="block rounded-md px-2 py-1 text-sm transition"
                            :class="activeCategoryId === child.id ? 'bg-brand-100 text-brand-700' : 'text-ink-500 hover:bg-surface-2'"
                        >
                            {{ child.name }}
                        </Link>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
</template>
