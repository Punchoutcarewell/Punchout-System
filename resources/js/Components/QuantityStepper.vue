<script setup lang="ts">
const props = defineProps<{
    modelValue: number;
    min?: number;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

function decrement(): void {
    const floor = props.min ?? 1;

    if (props.modelValue > floor) {
        emit('update:modelValue', props.modelValue - 1);
    }
}

function increment(): void {
    emit('update:modelValue', props.modelValue + 1);
}

function onInput(event: Event): void {
    const value = Number((event.target as HTMLInputElement).value);
    const floor = props.min ?? 1;

    if (Number.isInteger(value) && value >= floor) {
        emit('update:modelValue', value);
    }
}
</script>

<template>
    <div class="inline-flex items-center rounded-full border border-line bg-surface p-1">
        <button
            type="button"
            class="flex h-7 w-7 items-center justify-center rounded-full text-ink-700 transition hover:bg-surface-2 disabled:opacity-30"
            :disabled="disabled || modelValue <= (min ?? 1)"
            aria-label="Decrease quantity"
            @click="decrement"
        >
            &minus;
        </button>
        <input
            class="w-10 bg-transparent text-center font-data text-sm text-ink-900"
            type="number"
            :min="min ?? 1"
            :value="modelValue"
            :disabled="disabled"
            @input="onInput"
        />
        <button
            type="button"
            class="flex h-7 w-7 items-center justify-center rounded-full text-ink-700 transition hover:bg-surface-2 disabled:opacity-30"
            :disabled="disabled"
            aria-label="Increase quantity"
            @click="increment"
        >
            +
        </button>
    </div>
</template>
