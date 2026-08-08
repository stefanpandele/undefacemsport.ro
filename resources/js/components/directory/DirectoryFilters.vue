<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AutocompleteField from '@/components/sports/AutocompleteField.vue';
import type { AutocompleteOption } from '@/components/sports/AutocompleteField.vue';

/**
 * The filter bar the three directory lists share.
 *
 * County and the list's own axis — sport, or specialty — change what the server
 * returns, so they go back to it. The text box only narrows what is already on
 * screen, which is why it stays a plain v-model.
 */
const props = defineProps<{
    query: string;
    /** Where the filters submit — a Wayfinder url, not a route name. */
    url: string;
    counties: string[];
    options: { value: string; label: string; icon: string }[];
    county: string | null;
    option: string | null;
    optionParam: string;
    optionPlaceholder: string;
    optionLabel: string;
    searchPlaceholder: string;
}>();

const emit = defineEmits<{ 'update:query': [value: string] }>();

const countyOptions = computed<AutocompleteOption[]>(() =>
    props.counties.map((name) => ({ value: name, label: name })),
);

const listOptions = computed<AutocompleteOption[]>(() =>
    props.options.map((option) => ({
        value: option.value,
        label: `${option.icon} ${option.label}`.trim(),
    })),
);

function apply(changed: Record<string, string | null>) {
    const params: Record<string, string> = {};
    const next = {
        judet: props.county,
        [props.optionParam]: props.option,
        ...changed,
    };

    for (const [key, value] of Object.entries(next)) {
        if (value) {
            params[key] = value;
        }
    }

    router.get(props.url, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <div class="mx-auto mt-7 flex max-w-[760px] flex-col gap-2.5 sm:flex-row">
        <AutocompleteField
            class="sm:w-[30%]"
            :model-value="county"
            :options="countyOptions"
            placeholder="Toată țara"
            field-label="Județ"
            clear-label="Toată țara"
            @update:model-value="(value) => apply({ judet: value })"
        />
        <AutocompleteField
            class="sm:w-[30%]"
            :model-value="option"
            :options="listOptions"
            :placeholder="optionPlaceholder"
            :field-label="optionLabel"
            :clear-label="optionPlaceholder"
            @update:model-value="
                (value) => apply({ [optionParam]: value })
            "
        />
        <input
            :value="query"
            type="search"
            :placeholder="searchPlaceholder"
            class="flex-1 rounded-[14px] border border-line bg-white px-4.5 py-3.5 text-[15px] shadow-[0_20px_40px_-30px_rgba(11,20,16,0.35)] outline-none focus:border-grass"
            @input="
                emit(
                    'update:query',
                    ($event.target as HTMLInputElement).value,
                )
            "
        />
    </div>
</template>
