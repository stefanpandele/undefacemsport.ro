<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicTopBar from '@/components/sports/PublicTopBar.vue';
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

// County combobox. A native <datalist> would do the job, but its dropdown is
// drawn by the browser and cannot be made to match anything around it.
const countyQuery = ref(props.filters.county ?? '');
const countyOpen = ref(false);
const countyBox = ref<HTMLElement | null>(null);

const matchingCounties = computed(() => {
    const needle = normalize(countyQuery.value.trim());

    return needle
        ? props.counties.filter((c) => normalize(c).includes(needle))
        : props.counties;
});

/** The county changes the counts, so it has to come back from the server. */
function applyCounty(value: string | null) {
    countyQuery.value = value ?? '';
    countyOpen.value = false;

    router.get(sportRoutes.index.url(), value ? { judet: value } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function handleOutside(event: MouseEvent): void {
    if (countyBox.value && !countyBox.value.contains(event.target as Node)) {
        countyOpen.value = false;
        // Half-typed text that matched nothing would otherwise linger and look
        // like a filter that is on.
        countyQuery.value = props.filters.county ?? '';
    }
}

onMounted(() => document.addEventListener('click', handleOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleOutside));

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
        <PublicTopBar />

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
                    <div ref="countyBox" class="relative sm:w-[46%]">
                        <input
                            v-model="countyQuery"
                            type="text"
                            placeholder="Toată țara"
                            aria-label="Județ"
                            autocomplete="off"
                            class="w-full rounded-[14px] border bg-white py-3.5 pr-10 pl-4.5 text-left text-[15px] shadow-[0_20px_40px_-30px_rgba(11,20,16,0.35)] outline-none"
                            :class="
                                filters.county
                                    ? 'border-grass font-semibold'
                                    : 'border-line'
                            "
                            @focus="countyOpen = true"
                            @input="countyOpen = true"
                        />
                        <button
                            v-if="filters.county"
                            type="button"
                            aria-label="Renunță la județ"
                            class="absolute top-1/2 right-3.5 flex h-6 w-6 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-[#eaf6ef] text-[12px] text-grass-deep hover:bg-[#d8ebe0]"
                            @click="applyCounty(null)"
                        >
                            ✕
                        </button>
                        <span
                            v-else
                            class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-[11px] text-sage"
                        >
                            ▾
                        </span>

                        <div
                            v-if="countyOpen"
                            class="absolute top-full right-0 left-0 z-20 mt-1.5 max-h-[260px] overflow-y-auto rounded-[14px] border border-line bg-white py-1.5 text-left shadow-[0_24px_44px_-20px_rgba(11,20,16,0.35)]"
                        >
                            <button
                                type="button"
                                class="block w-full cursor-pointer px-4 py-2 text-left text-[14px] text-sage hover:bg-[#f2f5ef]"
                                @click="applyCounty(null)"
                            >
                                Toată țara
                            </button>
                            <button
                                v-for="name in matchingCounties"
                                :key="name"
                                type="button"
                                class="block w-full cursor-pointer px-4 py-2 text-left text-[14px] hover:bg-[#eaf6ef]"
                                :class="
                                    filters.county === name
                                        ? 'font-semibold text-grass-deep'
                                        : ''
                                "
                                @click="applyCounty(name)"
                            >
                                {{ name }}
                            </button>
                            <div
                                v-if="!matchingCounties.length"
                                class="px-4 py-2 text-[13.5px] text-sage"
                            >
                                Niciun județ care să semene.
                            </div>
                        </div>
                    </div>
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
