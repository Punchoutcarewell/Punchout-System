<script setup lang="ts">
import { computed, toRef } from 'vue';
import { useSessionCountdown } from '@/Composables/useSessionCountdown';
import type { PunchoutSessionRail } from '@/types/punchout';

const props = defineProps<{
    session: PunchoutSessionRail;
}>();

const expiresAt = computed(() => props.session.expiresAt);
const { label, isExpiringSoon } = useSessionCountdown(toRef(expiresAt));
</script>

<template>
    <div class="flex items-center gap-3 rounded-lg border border-line bg-surface px-4 py-2 text-sm">
        <div class="flex flex-col leading-tight">
            <span class="font-display font-semibold text-ink-900">{{ session.buyerName }}</span>
            <span v-if="session.businessUnit" class="text-ink-500">{{ session.businessUnit }}</span>
        </div>
        <div
            v-if="label"
            class="ml-auto rounded-full px-3 py-1 font-data text-xs"
            :class="isExpiringSoon ? 'bg-warn/10 text-warn' : 'bg-surface-2 text-ink-500'"
        >
            Session ends in {{ label }}
        </div>
    </div>
</template>
