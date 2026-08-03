<script setup lang="ts">
import { onMounted, ref } from 'vue';

const props = defineProps<{
    browserFormPostUrl: string;
    encodedCxml: string;
}>();

const formRef = ref<HTMLFormElement | null>(null);

onMounted(() => {
    formRef.value?.submit();
});
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-brand-050 px-4 text-center">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-brand-100 border-t-brand-600" />
        <p class="mt-6 font-display text-xl font-semibold text-ink-900">Returning to Coupa</p>
        <p class="mt-2 text-sm text-ink-500">Please wait while your cart is transferred back to your Coupa session.</p>

        <form ref="formRef" method="POST" :action="props.browserFormPostUrl" class="hidden">
            <input type="hidden" name="cxml-urlencoded" :value="props.encodedCxml" />
        </form>
    </div>
</template>
