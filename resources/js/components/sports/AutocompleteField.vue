<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export type AutocompleteOption = {
    value: string;
    label: string;
};

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        options: AutocompleteOption[];
        placeholder: string;
        /** The row that clears the field, e.g. "Toate sporturile". */
        clearLabel: string;
        /** Names the field for screen readers and the clear button. */
        fieldLabel: string;
        /**
         * `inset` sits inside another card (the hero search bar), `standalone`
         * carries its own border and shadow.
         */
        variant?: 'inset' | 'standalone';
    }>(),
    { variant: 'standalone' },
);

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>();

const root = ref<HTMLElement | null>(null);
const input = ref<HTMLInputElement | null>(null);
const open = ref(false);
const typed = ref('');

/** Diacritics-insensitive, so "brasov" finds "Brașov". */
function normalize(value: string): string {
    return value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

const selectedLabel = computed(
    () => props.options.find((o) => o.value === props.modelValue)?.label ?? '',
);

const matching = computed(() => {
    const needle = normalize(typed.value.trim());

    return needle
        ? props.options.filter((o) => normalize(o.label).includes(needle))
        : props.options;
});

function pick(value: string | null): void {
    open.value = false;
    typed.value =
        value === null
            ? ''
            : (props.options.find((o) => o.value === value)?.label ?? '');
    emit('update:modelValue', value);
}

/** Half-typed text that matched nothing must not linger as a fake selection. */
function handleOutside(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
        typed.value = selectedLabel.value;
    }
}

watch(selectedLabel, (label) => (typed.value = label), { immediate: true });

onMounted(() => document.addEventListener('click', handleOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleOutside));

defineExpose({
    focus: () => {
        input.value?.focus();
        open.value = true;
    },
});
</script>

<template>
    <div ref="root" class="relative">
        <input
            ref="input"
            v-model="typed"
            type="text"
            autocomplete="off"
            :placeholder="placeholder"
            :aria-label="fieldLabel"
            :aria-expanded="open"
            role="combobox"
            class="w-full border text-left outline-none"
            :class="[
                variant === 'inset'
                    ? 'rounded-[10px] bg-[#f7f8f6] py-3 pr-9 pl-3.5 text-[14.5px]'
                    : 'rounded-[14px] bg-white py-3.5 pr-10 pl-4.5 text-[15px] shadow-[0_20px_40px_-30px_rgba(11,20,16,0.35)]',
                modelValue
                    ? 'border-grass font-semibold'
                    : variant === 'inset'
                      ? 'border-transparent'
                      : 'border-line',
            ]"
            @focus="open = true"
            @input="open = true"
        />
        <button
            v-if="modelValue"
            type="button"
            :aria-label="`Renunță la ${fieldLabel.toLowerCase()}`"
            class="absolute top-1/2 right-2.5 flex h-6 w-6 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-[#eaf6ef] text-[12px] text-grass-deep hover:bg-[#d8ebe0]"
            @click="pick(null)"
        >
            ✕
        </button>
        <span
            v-else
            class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-[11px] text-sage"
        >
            ▾
        </span>

        <div
            v-if="open"
            class="absolute top-full right-0 left-0 z-30 mt-1.5 max-h-[260px] overflow-y-auto rounded-[14px] border border-line bg-white py-1.5 text-left shadow-[0_24px_44px_-20px_rgba(11,20,16,0.35)]"
        >
            <button
                type="button"
                class="block w-full cursor-pointer px-4 py-2 text-left text-[14px] text-sage hover:bg-[#f2f5ef]"
                @click="pick(null)"
            >
                {{ clearLabel }}
            </button>
            <button
                v-for="option in matching"
                :key="option.value"
                type="button"
                class="block w-full cursor-pointer px-4 py-2 text-left text-[14px] hover:bg-[#eaf6ef]"
                :class="
                    modelValue === option.value
                        ? 'font-semibold text-grass-deep'
                        : ''
                "
                @click="pick(option.value)"
            >
                {{ option.label }}
            </button>
            <div
                v-if="!matching.length"
                class="px-4 py-2 text-[13.5px] text-sage"
            >
                Nimic care să semene.
            </div>
        </div>
    </div>
</template>
