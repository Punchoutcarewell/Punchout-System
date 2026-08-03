import { computed, onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

const WARNING_THRESHOLD_MS = 5 * 60 * 1000;

export function useSessionCountdown(expiresAt: Ref<string | null>) {
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        timer = setInterval(() => {
            now.value = Date.now();
        }, 1000);
    });

    onBeforeUnmount(() => {
        clearInterval(timer);
    });

    const remainingMs = computed(() => {
        if (!expiresAt.value) {
            return null;
        }

        return Math.max(0, new Date(expiresAt.value).getTime() - now.value);
    });

    const label = computed(() => {
        if (remainingMs.value === null) {
            return null;
        }

        const totalSeconds = Math.floor(remainingMs.value / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;

        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    });

    const isExpiringSoon = computed(() => remainingMs.value !== null && remainingMs.value <= WARNING_THRESHOLD_MS);
    const isExpired = computed(() => remainingMs.value !== null && remainingMs.value <= 0);

    return { label, isExpiringSoon, isExpired };
}
