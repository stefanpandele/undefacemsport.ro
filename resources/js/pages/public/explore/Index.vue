<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PublicTopBar from '@/components/sports/PublicTopBar.vue';
import { sportGradient } from '@/lib/gradients';
import { explore } from '@/routes';
import locationRoutes from '@/routes/locations';

type ExploreLocation = {
    slug: string;
    name: string;
    city: string;
    lat: number | null;
    lng: number | null;
    live: boolean;
    clubCount: number;
    facilityCount: number;
    color: string | null;
    sports: { key: string; label: string }[];
};

type ExploreSport = {
    key: string;
    label: string;
    icon: string;
    color: string | null;
    locationCount: number;
    clubCount: number;
    ages: string[];
};

type ExploreFilters = {
    sport: string | null;
    facility: number | null;
    search: string | null;
};

const props = defineProps<{
    city: string | null;
    cities: string[];
    filters: ExploreFilters;
    locations: ExploreLocation[];
    sports: ExploreSport[];
    facilities: { id: number; name: string }[];
}>();

const view = ref<'location' | 'sport'>('location');
const search = ref(props.filters.search ?? '');

/**
 * Push the filters into the URL so the list, the map and the counters all come
 * back from the server in sync — and the page stays shareable.
 */
function applyFilters(
    changed: Partial<Record<string, string | number | null>>,
) {
    const next = {
        oras: props.city,
        sport: props.filters.sport,
        facilitate: props.filters.facility,
        cauta: props.filters.search,
        ...changed,
    };

    router.get(
        explore.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ cauta: value || null }), 350);
});

function filterBySport(key: string) {
    view.value = 'location';
    applyFilters({ sport: key });
}

const activeSport = computed(
    () => props.sports.find((s) => s.key === props.filters.sport) ?? null,
);

// Distances are only known once the visitor shares their position.
const myPosition = ref<{ lat: number; lng: number } | null>(null);
const locating = ref(false);

function locateMe() {
    if (!navigator.geolocation) {
        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            myPosition.value = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            };
            locating.value = false;
        },
        () => (locating.value = false),
        { timeout: 8000 },
    );
}

function distanceKm(loc: ExploreLocation): number | null {
    if (!myPosition.value || loc.lat === null || loc.lng === null) {
        return null;
    }

    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const dLat = toRad(loc.lat - myPosition.value.lat);
    const dLng = toRad(loc.lng - myPosition.value.lng);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(myPosition.value.lat)) *
            Math.cos(toRad(loc.lat)) *
            Math.sin(dLng / 2) ** 2;

    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function distanceLabel(loc: ExploreLocation): string | null {
    const km = distanceKm(loc);

    return km === null
        ? null
        : `${km < 10 ? km.toFixed(1) : Math.round(km)} km`;
}

const visibleLocations = computed(() => {
    if (!myPosition.value) {
        return props.locations;
    }

    return [...props.locations].sort(
        (a, b) => (distanceKm(a) ?? Infinity) - (distanceKm(b) ?? Infinity),
    );
});

// Map band: the pins keep the real geography, normalised into the band.
const mappable = computed(() =>
    props.locations.filter((l) => l.lat !== null && l.lng !== null),
);

const activePin = ref<string | null>(null);

const activePinLocation = computed(
    () => props.locations.find((l) => l.slug === activePin.value) ?? null,
);

function pinStyle(loc: ExploreLocation) {
    const lats = mappable.value.map((l) => l.lat as number);
    const lngs = mappable.value.map((l) => l.lng as number);
    const span = (values: number[]) =>
        Math.max(...values) - Math.min(...values);
    const place = (value: number, values: number[]) =>
        span(values) === 0
            ? 50
            : 10 + ((value - Math.min(...values)) / span(values)) * 80;

    return {
        top: `${100 - place(loc.lat as number, lats)}%`,
        left: `${place(loc.lng as number, lngs)}%`,
    };
}

function locationColor(loc: ExploreLocation): string {
    return sportGradient(loc.color);
}

/**
 * Carry the sport filter over, so the location page opens on the same sport.
 */
function locationHref(loc: ExploreLocation): string {
    return locationRoutes.show.url(
        loc.slug,
        props.filters.sport
            ? { query: { sport: props.filters.sport } }
            : undefined,
    );
}
</script>

<template>
    <Head :title="`Explorează ${city ?? 'România'} — Unde Facem Sport`" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <PublicTopBar />

        <!-- Search bar -->
        <div
            class="sticky top-[60px] z-[60] border-b border-line bg-white py-3.5"
        >
            <div class="mx-auto max-w-[1280px] px-5">
                <div class="flex flex-wrap gap-2">
                    <select
                        class="rounded-[10px] border border-line bg-[#f7f8f6] px-3.5 py-2.5 text-sm"
                        :value="filters.sport ?? ''"
                        @change="
                            applyFilters({
                                sport:
                                    ($event.target as HTMLSelectElement)
                                        .value || null,
                            })
                        "
                    >
                        <option value="">Toate sporturile</option>
                        <option v-for="s in sports" :key="s.key" :value="s.key">
                            {{ s.label }}
                        </option>
                    </select>
                    <select
                        class="rounded-[10px] border border-line bg-[#f7f8f6] px-3.5 py-2.5 text-sm"
                        :value="city ?? ''"
                        @change="
                            applyFilters({
                                oras: ($event.target as HTMLSelectElement)
                                    .value,
                            })
                        "
                    >
                        <option v-for="c in cities" :key="c" :value="c">
                            {{ c }}
                        </option>
                    </select>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Caută o locație…"
                        class="min-w-[160px] flex-1 rounded-[10px] border border-line bg-[#f7f8f6] px-3.5 py-2.5 text-sm"
                    />
                </div>
                <div
                    v-if="facilities.length"
                    class="flex gap-2 overflow-x-auto pt-2.5"
                >
                    <button
                        type="button"
                        class="rounded-full border-[1.5px] px-3.5 py-[7px] text-[12.5px] font-semibold whitespace-nowrap transition"
                        :class="
                            filters.facility
                                ? 'border-line bg-white'
                                : 'border-grass bg-[#eaf6ef] text-grass-deep'
                        "
                        @click="applyFilters({ facilitate: null })"
                    >
                        Toate
                    </button>
                    <button
                        v-for="facility in facilities"
                        :key="facility.id"
                        type="button"
                        class="rounded-full border-[1.5px] px-3.5 py-[7px] text-[12.5px] font-semibold whitespace-nowrap transition"
                        :class="
                            filters.facility === facility.id
                                ? 'border-grass bg-[#eaf6ef] text-grass-deep'
                                : 'border-line bg-white'
                        "
                        @click="applyFilters({ facilitate: facility.id })"
                    >
                        {{ facility.name }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Map band -->
        <div
            class="relative h-[220px] min-[900px]:h-[340px]"
            style="
                background:
                    linear-gradient(#eef2ea, #eef2ea),
                    repeating-linear-gradient(
                        0deg,
                        transparent 0 38px,
                        #dfe6da 38px 39px
                    ),
                    repeating-linear-gradient(
                        90deg,
                        transparent 0 38px,
                        #dfe6da 38px 39px
                    );
            "
        >
            <button
                v-for="loc in mappable"
                :key="loc.slug"
                type="button"
                :aria-label="loc.name"
                class="absolute rotate-[-45deg] rounded-[50%_50%_50%_0] shadow-[0_4px_10px_rgba(0,0,0,0.25)] transition-all"
                :class="
                    activePin === loc.slug
                        ? 'h-[30px] w-[30px] bg-clay'
                        : 'h-6 w-6 bg-grass-deep'
                "
                :style="pinStyle(loc)"
                @click="activePin = activePin === loc.slug ? null : loc.slug"
            />
            <div
                v-if="activePinLocation"
                class="absolute top-[38%] left-[34%] z-[2] w-[190px] rounded-xl border border-line bg-white px-3 py-2.5 shadow-[0_20px_40px_-20px_rgba(0,0,0,0.3)]"
            >
                <Link
                    :href="locationHref(activePinLocation)"
                    class="font-archivo text-[13.5px] font-extrabold hover:text-grass-deep"
                >
                    {{ activePinLocation.name }}
                </Link>
                <div class="mt-1 font-jetbrains text-[10.5px] text-grass-deep">
                    {{ activePinLocation.clubCount }}
                    {{ activePinLocation.clubCount === 1 ? 'CLUB' : 'CLUBURI' }}
                    <template v-if="activePinLocation.live">
                        · ACUM ACTIV</template
                    >
                </div>
            </div>
            <div
                v-if="!mappable.length"
                class="absolute inset-0 flex items-center justify-center text-[13.5px] text-sage"
            >
                Nicio locație cu coordonate pe hartă.
            </div>
            <button
                type="button"
                class="absolute right-3.5 bottom-3.5 flex items-center gap-1.5 rounded-[10px] border border-line bg-white px-3 py-2 text-[12.5px] font-semibold shadow-[0_8px_20px_-10px_rgba(0,0,0,0.3)] disabled:opacity-60"
                :disabled="locating"
                @click="locateMe"
            >
                📍
                {{
                    locating
                        ? 'Te caut…'
                        : myPosition
                          ? 'Sortat după distanță'
                          : 'Locația mea'
                }}
            </button>
        </div>

        <div class="mx-auto max-w-[1280px] px-5">
            <!-- Toggle -->
            <div class="flex justify-center pt-5.5 pb-1.5">
                <div
                    class="inline-flex rounded-full border-[1.5px] border-line bg-white p-1"
                >
                    <button
                        type="button"
                        class="rounded-full px-5 py-2.5 font-jetbrains text-[12.5px] font-bold tracking-[0.03em] transition"
                        :class="
                            view === 'location'
                                ? 'bg-grass text-white'
                                : 'text-sage'
                        "
                        @click="view = 'location'"
                    >
                        DUPĂ LOCAȚIE
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-5 py-2.5 font-jetbrains text-[12.5px] font-bold tracking-[0.03em] transition"
                        :class="
                            view === 'sport'
                                ? 'bg-grass text-white'
                                : 'text-sage'
                        "
                        @click="view = 'sport'"
                    >
                        DUPĂ SPORT
                    </button>
                </div>
            </div>

            <!-- Filter banner -->
            <div
                v-if="activeSport && view === 'location'"
                class="mt-4.5 flex items-center justify-center gap-2.5"
            >
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-ink py-2 pr-2 pl-4 text-[13px] font-semibold text-white"
                >
                    <span class="text-[15px]">{{ activeSport.icon }}</span>
                    <span
                        >Filtrat după: <b>{{ activeSport.label }}</b></span
                    >
                    <button
                        type="button"
                        class="flex h-[22px] w-[22px] items-center justify-center rounded-full bg-white/15 hover:bg-white/30"
                        @click="applyFilters({ sport: null })"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Locations view -->
            <div v-show="view === 'location'">
                <div class="my-5 flex items-center justify-between">
                    <h1 class="font-archivo text-[19px] font-extrabold">
                        Locații în {{ city ?? 'România' }}
                    </h1>
                    <span class="text-[13.5px] text-sage">
                        {{ locations.length }}
                        {{ locations.length === 1 ? 'rezultat' : 'rezultate' }}
                    </span>
                </div>
                <div
                    v-if="!locations.length"
                    class="pb-15 text-[14.5px] text-sage"
                >
                    Nicio locație pentru filtrele alese.
                </div>
                <div
                    class="grid grid-cols-1 gap-4 pb-15 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="loc in visibleLocations"
                        :key="loc.slug"
                        :href="locationHref(loc)"
                        class="overflow-hidden rounded-[18px] border border-line bg-white transition hover:-translate-y-1 hover:shadow-[0_24px_44px_-26px_rgba(11,20,16,0.45)]"
                    >
                        <div
                            class="relative flex h-[130px] items-end p-3"
                            :style="{ background: locationColor(loc) }"
                        >
                            <span
                                v-if="loc.live"
                                class="absolute top-2.5 left-2.5 flex items-center gap-1.5 rounded-md bg-grass-deep px-2 py-1 font-jetbrains text-[9.5px] font-bold text-white"
                            >
                                <span
                                    class="h-[5px] w-[5px] rounded-full bg-[#9CFFCB]"
                                />
                                ACUM ACTIV
                            </span>
                            <span
                                v-if="distanceLabel(loc)"
                                class="absolute top-2.5 right-2.5 rounded-md bg-white/95 px-2 py-1 font-jetbrains text-[10px] font-bold"
                            >
                                {{ distanceLabel(loc) }}
                            </span>
                            <div
                                class="absolute inset-0"
                                style="
                                    background: linear-gradient(
                                        to top,
                                        rgba(8, 16, 11, 0.78),
                                        transparent 62%
                                    );
                                "
                            />
                            <div class="relative text-white">
                                <div
                                    class="font-archivo text-[16.5px] font-extrabold"
                                >
                                    {{ loc.name }}
                                </div>
                                <div
                                    class="font-jetbrains text-[10.5px] font-semibold uppercase opacity-90"
                                >
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
                                        filters.sport === sport.key
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
                                <span
                                    ><b class="text-ink">{{ loc.clubCount }}</b>
                                    cluburi active</span
                                >
                                <span
                                    class="inline-flex items-center gap-1 font-jetbrains text-[10.5px] font-semibold"
                                >
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
                    <h1 class="font-archivo text-[19px] font-extrabold">
                        Sporturi în {{ city ?? 'România' }}
                    </h1>
                    <span class="text-[13.5px] text-sage">
                        {{ sports.length }}
                        {{ sports.length === 1 ? 'sport' : 'sporturi' }}
                    </span>
                </div>
                <div
                    v-if="!sports.length"
                    class="pb-15 text-[14.5px] text-sage"
                >
                    Niciun sport înregistrat aici încă.
                </div>
                <div
                    class="grid grid-cols-1 gap-4 pb-15 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <button
                        v-for="sport in sports"
                        :key="sport.key"
                        type="button"
                        class="overflow-hidden rounded-[18px] border border-line bg-white text-left transition hover:-translate-y-1 hover:shadow-[0_24px_44px_-26px_rgba(11,20,16,0.45)]"
                        @click="filterBySport(sport.key)"
                    >
                        <div
                            class="flex h-24 items-center gap-3 px-4.5 text-white"
                            :style="{ background: sportGradient(sport.color) }"
                        >
                            <span class="text-[30px]">{{ sport.icon }}</span>
                            <span
                                class="font-archivo text-[19px] font-extrabold"
                                >{{ sport.label }}</span
                            >
                        </div>
                        <div class="px-4 pt-3.5 pb-4">
                            <div class="mb-3 flex gap-4">
                                <div>
                                    <div
                                        class="font-jetbrains text-[17px] font-bold"
                                    >
                                        {{ sport.locationCount }}
                                    </div>
                                    <div class="text-[10.5px] text-sage">
                                        locații
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="font-jetbrains text-[17px] font-bold"
                                    >
                                        {{ sport.clubCount }}
                                    </div>
                                    <div class="text-[10.5px] text-sage">
                                        cluburi
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="sport.ages.length"
                                class="mb-3.5 flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="age in sport.ages"
                                    :key="age"
                                    class="rounded-[7px] border border-line bg-[#f2f5ef] px-2.5 py-1 text-[11px] font-semibold text-sage"
                                >
                                    {{ age }}
                                </span>
                            </div>
                            <span
                                class="flex items-center gap-1 text-[13px] font-semibold text-grass-deep"
                            >
                                Vezi locațiile →
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
