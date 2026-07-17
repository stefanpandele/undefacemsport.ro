<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    siteKey: string | null;
    enabled: boolean;
}>();

// The verification token is exposed via v-model so the parent form can submit
// it and clear it. On local/testing the widget is mocked and a placeholder
// token is emitted so the flow stays identical to production.
const token = defineModel<string>({ default: '' });

const containerRef = ref<HTMLElement | null>(null);
let widgetId: string | null = null;

const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

function loadScript(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (window.turnstile) {
            resolve();

            return;
        }

        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${SCRIPT_SRC}"]`,
        );

        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject());

            return;
        }

        const script = document.createElement('script');
        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject();
        document.head.appendChild(script);
    });
}

async function renderWidget() {
    if (!props.enabled) {
        token.value = 'local-mock-token';

        return;
    }

    if (!props.siteKey) {
        return;
    }

    try {
        await loadScript();

        if (!containerRef.value || !window.turnstile) {
            return;
        }

        widgetId = window.turnstile.render(containerRef.value, {
            sitekey: props.siteKey,
            callback: (value: string) => {
                token.value = value;
            },
            'expired-callback': () => {
                token.value = '';
            },
            'error-callback': () => {
                token.value = '';
            },
        });
    } catch {
        // Script failed to load — leave the token empty so submit stays locked.
    }
}

function reset() {
    token.value = '';

    if (!props.enabled) {
        token.value = 'local-mock-token';

        return;
    }

    if (widgetId && window.turnstile) {
        window.turnstile.reset(widgetId);
    }
}

defineExpose({ reset });

onMounted(renderWidget);

onBeforeUnmount(() => {
    if (widgetId && window.turnstile) {
        window.turnstile.remove(widgetId);
    }
});
</script>

<template>
    <div>
        <!-- Local/testing mock — never shown in dev or production. -->
        <div
            v-if="!enabled"
            class="flex items-center gap-2.5 rounded-[10px] border border-dashed border-line bg-[#f7f8f6] px-3.5 py-3 text-[13px] text-sage"
        >
            <span
                class="flex h-5 w-5 items-center justify-center rounded bg-grass text-[11px] text-white"
            >
                ✓
            </span>
            Turnstile mock (local) — verificat automat
        </div>

        <!-- Real Cloudflare Turnstile widget. -->
        <div v-else ref="containerRef" />
    </div>
</template>
