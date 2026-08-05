<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    browserFormPostUrl: string;
    encodedCxml: string;
}>();

const formRef = ref<HTMLFormElement | null>(null);
const showManualContinue = ref(false);
let revealTimer: ReturnType<typeof setTimeout> | undefined;

function submit(): void {
    formRef.value?.submit();
}

onMounted(() => {
    submit();

    // If the auto-submit silently fails (blocked by an enterprise
    // browser policy, a network blip, a JS error elsewhere on the page),
    // the buyer would otherwise be looking at a spinner with no way
    // forward and, since this session is now in the Transferring grace
    // window rather than immediately Transferred, no way back into the
    // cart either. This is the escape hatch: a real, clickable way to
    // retry the same post, or the storefront-visible fallback if this
    // page reloads and hits the resumed-transfer path.
    revealTimer = setTimeout(() => {
        showManualContinue.value = true;
    }, 3000);
});

onBeforeUnmount(() => {
    clearTimeout(revealTimer);
});
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-white px-4 text-center">
        <div class="max-w-md rounded-2xl border border-line bg-surface p-8 shadow-sm">
            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-brand-100 border-t-brand-600" />
            <p class="mt-6 font-display text-xl font-bold text-ink-900">Returning to Coupa</p>
            <p class="mt-2 text-sm text-ink-500">Please wait while your cart is transferred back to your Coupa session.</p>

            <button
                v-if="showManualContinue"
                type="button"
                class="mt-6 rounded-full bg-brand-600 px-6 py-2.5 font-display text-sm font-semibold text-white transition hover:bg-brand-700"
                @click="submit"
            >
                Continue to Coupa
            </button>
            <p v-if="showManualContinue" class="mt-2 text-xs text-ink-500">
                This is taking longer than expected. Click above if nothing happens automatically.
            </p>
        </div>

        <form ref="formRef" method="POST" :action="props.browserFormPostUrl" class="hidden">
            <input type="hidden" name="cxml-urlencoded" :value="props.encodedCxml" />
        </form>
    </div>
</template>
