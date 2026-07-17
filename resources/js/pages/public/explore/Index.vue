<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PublicTopBar from '@/components/sports/PublicTopBar.vue';
import { gradientStyle } from '@/lib/gradients';

type ExploreLocation = {
    slug: string;
    name: string;
    city: string;
    distance: string;
    photo: string;
    live: boolean;
    clubCount: number;
    facilityCount: number;
    sports: { key: string; label: string }[];
};

type ExploreSport = {
    key: string;
    label: string;
    icon: string;
    head: string;
    locationCount: number;
    clubCount: number;
    ages: string[];
};

const props = defineProps<{
    city: string;
    locations: ExploreLocation[];
    sports: ExploreSport[];
}>();

const view = ref<'location' | 'sport'>('location');
const sportFilter = ref<string | null>(null);
const facilityChips = ['Toate', 'Nocturnă', 'Parcare', 'Copii', 'Bebeluși', 'Începători', 'Weekend'];
const activeChip = ref('Toate');
const pins = ['p1', 'p2', 'p3', 'p4', 'p5', 'p6'];
const pinPos: Record<string, { top: string; left: string }> = {
    p1: { top: '28%', left: '18%' },
    p2: { top: '48%', left: '44%' },
    p3: { top: '36%', left: '64%' },
    p4: { top: '66%', left: '28%' },
    p5: { top: '20%', left: '56%' },
    p6: { top: '58%', left: '76%' },
};

const filteredLocations = computed(() =>
    sportFilter.value
        ? props.locations.filter((l) => l.sports.some((s) => s.key === sportFilter.value))
        : props.locations,
);

const filterSportLabel = computed(
    () => props.sports.find((s) => s.key === sportFilter.value)?.label ?? '',
);
const filterSportIcon = computed(
    () => props.sports.find((s) => s.key === sportFilter.value)?.icon ?? '🏷️',
);

function filterBySport(key: string) {
    sportFilter.value = key;
    view.value = 'location';
}

function clearFilter() {
    sportFilter.value = null;
}
</script>

<template>
    <Head :title="`Explorează ${city} — Unde Facem Sport`" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <PublicTopBar />

        <!-- Search bar -->
        <div class="sticky top-[60px] z-[60] border-b border-line bg-white py-3.5">
            <div class="mx-auto max-w-[1280px] px-5">
                <div class="flex flex-wrap gap-2">
                    <select class="rounded-[10px] border border-line bg-[#f7f8f6] px-3.5 py-2.5 text-sm">
                        <option value="">Sport</option>
                        <option v-for="s in sports" :key="s.key" :value="s.key">{{ s.label }}</option>
                    </select>
                    <select class="rounded-[10px] border border-line bg-[#f7f8f6] px-3.5 py-2.5 text-sm">
                        <option selected>{{ city }}</option>
                        <option>București</option>
                        <option>Brașov</option>
                    </select>
                    <input
                        type="text"
                        placeholder="Caută o locație…"
                        class="min-w-[160px] flex-1 rounded-[10px] border border-line bg-[#f7f8f6] px-3.5 py-2.5 text-sm"
                    />
                </div>
                <div class="flex gap-2 overflow-x-auto pt-2.5">
                    <button
                        v-for="chip in facilityChips"
                        :key="chip"
                        type="button"
                        class="rounded-full border-[1.5px] px-3.5 py-[7px] text-[12.5px] font-semibold whitespace-nowrap transition"
                        :class="
                            activeChip === chip
                                ? 'border-grass bg-[#eaf6ef] text-grass-deep'
                                : 'border-line bg-white'
                        "
                        @click="activeChip = chip"
                    >
                        {{ chip }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Map band -->
        <div
            class="relative h-[220px] min-[900px]:h-[340px]"
            style="background: linear-gradient(#eef2ea,#eef2ea), repeating-linear-gradient(0deg, transparent 0 38px, #dfe6da 38px 39px), repeating-linear-gradient(90deg, transparent 0 38px, #dfe6da 38px 39px)"
        >
            <div
                v-for="pin in pins"
                :key="pin"
                class="absolute rotate-[-45deg] rounded-[50%_50%_50%_0] shadow-[0_4px_10px_rgba(0,0,0,0.25)]"
                :class="pin === 'p2' ? 'h-[30px] w-[30px] bg-clay' : 'h-6 w-6 bg-grass-deep'"
                :style="pinPos[pin]"
            />
            <div
                class="absolute top-[40%] left-[34%] z-[2] w-[180px] rounded-xl border border-line bg-white px-3 py-2.5 shadow-[0_20px_40px_-20px_rgba(0,0,0,0.3)]"
            >
                <div class="font-archivo text-[13.5px] font-extrabold">Sala Polivalentă Cluj</div>
                <div class="mt-1 font-jetbrains text-[10.5px] text-grass-deep">LUN 18:00–20:00</div>
            </div>
            <div
                class="absolute right-3.5 bottom-3.5 flex items-center gap-1.5 rounded-[10px] border border-line bg-white px-3 py-2 text-[12.5px] font-semibold shadow-[0_8px_20px_-10px_rgba(0,0,0,0.3)]"
            >
                📍 Locația mea
            </div>
        </div>

        <div class="mx-auto max-w-[1280px] px-5">
            <!-- Toggle -->
            <div class="flex justify-center pt-5.5 pb-1.5">
                <div class="inline-flex rounded-full border-[1.5px] border-line bg-white p-1">
                    <button
                        type="button"
                        class="rounded-full px-5 py-2.5 font-jetbrains text-[12.5px] font-bold tracking-[0.03em] transition"
                        :class="view === 'location' ? 'bg-grass text-white' : 'text-sage'"
                        @click="view = 'location'"
                    >
                        DUPĂ LOCAȚIE
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-5 py-2.5 font-jetbrains text-[12.5px] font-bold tracking-[0.03em] transition"
                        :class="view === 'sport' ? 'bg-grass text-white' : 'text-sage'"
                        @click="view = 'sport'"
                    >
                        DUPĂ SPORT
                    </button>
                </div>
            </div>

            <!-- Filter banner -->
            <div v-if="sportFilter && view === 'location'" class="mt-4.5 flex items-center justify-center gap-2.5">
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-ink py-2 pr-2 pl-4 text-[13px] font-semibold text-white"
                >
                    <span class="text-[15px]">{{ filterSportIcon }}</span>
                    <span>Filtrat după: <b>{{ filterSportLabel }}</b></span>
                    <button
                        type="button"
                        class="flex h-[22px] w-[22px] items-center justify-center rounded-full bg-white/15 hover:bg-white/30"
                        @click="clearFilter"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Locations view -->
            <div v-show="view === 'location'">
                <div class="my-5 flex items-center justify-between">
                    <h1 class="font-archivo text-[19px] font-extrabold">Locații în {{ city }}</h1>
                    <span class="text-[13.5px] text-sage">
                        {{ filteredLocations.length }}
                        {{ filteredLocations.length === 1 ? 'rezultat' : 'rezultate' }}
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-4 pb-15 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="loc in filteredLocations"
                        :key="loc.slug"
                        :href="`/locatii/${loc.slug}`"
                        class="overflow-hidden rounded-[18px] border border-line bg-white transition hover:-translate-y-1 hover:shadow-[0_24px_44px_-26px_rgba(11,20,16,0.45)]"
                    >
                        <div
                            class="relative flex h-[130px] items-end p-3"
                            :style="{ background: gradientStyle(loc.photo) }"
                        >
                            <span
                                v-if="loc.live"
                                class="absolute top-2.5 left-2.5 flex items-center gap-1.5 rounded-md bg-grass-deep px-2 py-1 font-jetbrains text-[9.5px] font-bold text-white"
                            >
                                <span class="h-[5px] w-[5px] rounded-full bg-[#9CFFCB]" />
                                ACUM ACTIV
                            </span>
                            <span
                                class="absolute top-2.5 right-2.5 rounded-md bg-white/95 px-2 py-1 font-jetbrains text-[10px] font-bold"
                            >
                                {{ loc.distance }}
                            </span>
                            <div
                                class="absolute inset-0"
                                style="background: linear-gradient(to top, rgba(8,16,11,0.78), transparent 62%)"
                            />
                            <div class="relative text-white">
                                <div class="font-archivo text-[16.5px] font-extrabold">{{ loc.name }}</div>
                                <div class="font-jetbrains text-[10.5px] font-semibold opacity-90">
                                    📍 {{ loc.city }}
                                </div>
                            </div>
                        </div>
                        <div class="px-4 pt-3 pb-4">
                            <div class="mb-3 flex flex-wrap gap-1.5">
                                <span
                                    v-for="sport in loc.sports"
                                    :key="sport.key"
                                    class="rounded-[7px] px-2.5 py-[3px] text-[11.5px] font-semibold transition"
                                    :class="
                                        sportFilter === sport.key
                                            ? 'bg-clay text-white'
                                            : 'bg-[#eaf6ef] text-grass-deep'
                                    "
                                >
                                    {{ sport.label }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-line pt-2.5 text-xs text-sage"
                            >
                                <span><b class="text-ink">{{ loc.clubCount }}</b> cluburi active</span>
                                <span class="inline-flex items-center gap-1 font-jetbrains text-[10.5px] font-semibold">
                                    🛠 {{ loc.facilityCount }} facilități
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Sports view -->
            <div v-show="view === 'sport'">
                <div class="my-5 flex items-center justify-between">
                    <h1 class="font-archivo text-[19px] font-extrabold">Sporturi în {{ city }}</h1>
                    <span class="text-[13.5px] text-sage">{{ sports.length }} sporturi</span>
                </div>
                <div class="grid grid-cols-1 gap-4 pb-15 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="sport in sports"
                        :key="sport.key"
                        type="button"
                        class="overflow-hidden rounded-[18px] border border-line bg-white text-left transition hover:-translate-y-1 hover:shadow-[0_24px_44px_-26px_rgba(11,20,16,0.45)]"
                        @click="filterBySport(sport.key)"
                    >
                        <div
                            class="flex h-24 items-center gap-3 px-4.5 text-white"
                            :style="{ background: gradientStyle(sport.head) }"
                        >
                            <span class="text-[30px]">{{ sport.icon }}</span>
                            <span class="font-archivo text-[19px] font-extrabold">{{ sport.label }}</span>
                        </div>
                        <div class="px-4 pt-3.5 pb-4">
                            <div class="mb-3 flex gap-4">
                                <div>
                                    <div class="font-jetbrains text-[17px] font-bold">{{ sport.locationCount }}</div>
                                    <div class="text-[10.5px] text-sage">locații</div>
                                </div>
                                <div>
                                    <div class="font-jetbrains text-[17px] font-bold">{{ sport.clubCount }}</div>
                                    <div class="text-[10.5px] text-sage">cluburi</div>
                                </div>
                            </div>
                            <div class="mb-3.5 flex flex-wrap gap-1.5">
                                <span
                                    v-for="age in sport.ages"
                                    :key="age"
                                    class="rounded-[7px] border border-line bg-[#f2f5ef] px-2.5 py-1 text-[11px] font-semibold text-sage"
                                >
                                    {{ age }}
                                </span>
                            </div>
                            <span class="flex items-center gap-1 text-[13px] font-semibold text-grass-deep">
                                Vezi locațiile →
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
