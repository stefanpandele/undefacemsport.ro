<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SiteNav from '@/components/SiteNav.vue';
import AutocompleteField from '@/components/sports/AutocompleteField.vue';
import type { AutocompleteOption } from '@/components/sports/AutocompleteField.vue';
import { sportGradient } from '@/lib/gradients';
import { explore } from '@/routes';
import sportRoutes from '@/routes/sports';

type SportCard = {
    key: string;
    label: string;
    icon: string;
    color: string | null;
    locationCount: number;
    clubCount: number;
    cityCount: number;
};

const props = defineProps<{
    sports: SportCard[];
    counties: string[];
    filters: { county: string | null };
}>();

const query = ref('');

function normalize(value: string): string {
    return value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

const countyOptions = computed<AutocompleteOption[]>(() =>
    props.counties.map((name) => ({ value: name, label: name })),
);

/** The county changes the counts, so it has to come back from the server. */
function applyCounty(value: string | null) {
    router.get(sportRoutes.index.url(), value ? { judet: value } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const matching = computed(() => {
    const needle = normalize(query.value.trim());

    return needle
        ? props.sports.filter((s) => normalize(s.label).includes(needle))
        : props.sports;
});
</script>

<template>
    <Head title="Sporturi — Unde Facem Sport" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[1180px] px-5 pt-12 pb-20">
            <div class="text-center">
                <span
                    class="mb-3.5 block font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass-deep uppercase"
                >
                    {{ sports.length }}
                    {{ sports.length === 1 ? 'sport' : 'sporturi' }} pe
                    platformă
                </span>
                <h1
                    class="font-archivo text-[clamp(28px,5.5vw,44px)] leading-[1.05] font-extrabold tracking-[-0.02em]"
                >
                    Ce sport vrei să faci?
                </h1>
                <p class="mx-auto mt-3.5 max-w-[46ch] text-[16px] text-sage">
                    Alege sportul, apoi orașul — îți arătăm sălile și cluburile
                    care îl predau acolo.
                </p>

                <div
                    class="mx-auto mt-7 flex max-w-[620px] flex-col gap-2.5 sm:flex-row"
                >
                    <!-- County first: it changes what the numbers mean, while
                         the search below only narrows what is already shown. -->
                    <AutocompleteField
                        class="sm:w-[46%]"
                        :model-value="filters.county"
                        :options="countyOptions"
                        placeholder="Toată țara"
                        field-label="Județ"
                        clear-label="Toată țara"
                        @update:model-value="applyCounty"
                    />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Caută un sport…"
                        class="flex-1 rounded-[14px] border border-line bg-white px-4.5 py-3.5 text-[15px] shadow-[0_20px_40px_-30px_rgba(11,20,16,0.35)] outline-none focus:border-grass"
                    />
                </div>
            </div>

            <div
                v-if="!matching.length"
                class="py-14 text-center text-[14.5px] text-sage"
            >
                <template v-if="query">
                    Niciun sport care să semene cu „{{ query }}”{{
                        filters.county ? ` în județul ${filters.county}` : ''
                    }}.
                </template>
                <template v-else>
                    Niciun sport înregistrat încă în județul
                    {{ filters.county }}.
                </template>
            </div>
            <div
                v-else
                class="mt-10 grid grid-cols-2 gap-3.5 md:grid-cols-3 lg:grid-cols-4"
            >
                <Link
                    v-for="sport in matching"
                    :key="sport.key"
                    :href="explore.url({ query: { sport: sport.key } })"
                    class="group overflow-hidden rounded-[18px] border border-line bg-white text-left transition hover:-translate-y-1 hover:border-grass hover:shadow-[0_24px_44px_-26px_rgba(11,20,16,0.45)]"
                >
                    <div
                        class="flex h-[74px] items-center gap-2.5 px-4 text-white"
                        :style="{ background: sportGradient(sport.color) }"
                    >
                        <span class="text-[26px]">{{ sport.icon }}</span>
                        <span class="font-archivo text-[17px] font-extrabold">
                            {{ sport.label }}
                        </span>
                    </div>
                    <div class="px-4 pt-3 pb-3.5">
                        <div class="grid grid-cols-3 gap-1">
                            <div>
                                <div
                                    class="font-jetbrains text-[15px] leading-none font-bold"
                                >
                                    {{ sport.locationCount }}
                                </div>
                                <div
                                    class="mt-1 text-[9.5px] leading-tight text-sage"
                                >
                                    {{
                                        sport.locationCount === 1
                                            ? 'locație'
                                            : 'locații'
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="font-jetbrains text-[15px] leading-none font-bold"
                                >
                                    {{ sport.clubCount }}
                                </div>
                                <div
                                    class="mt-1 text-[9.5px] leading-tight text-sage"
                                >
                                    {{
                                        sport.clubCount === 1
                                            ? 'club'
                                            : 'cluburi'
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="font-jetbrains text-[15px] leading-none font-bold"
                                >
                                    {{ sport.cityCount }}
                                </div>
                                <div
                                    class="mt-1 text-[9.5px] leading-tight text-sage"
                                >
                                    {{
                                        sport.cityCount === 1 ? 'oraș' : 'orașe'
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
