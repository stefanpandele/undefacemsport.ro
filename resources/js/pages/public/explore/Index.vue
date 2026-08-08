<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SiteNav from '@/components/SiteNav.vue';
import LocationsMap from '@/components/sports/LocationsMap.vue';
import { sportGradient } from '@/lib/gradients';
import { explore } from '@/routes';
import organizationApplication from '@/routes/organization-application';
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
    /**
     * Which of the three ways in this place offers — the visitor's real
     * question, and the axis every listing on the site groups by.
     */
    ways: { key: string; label: string }[];
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

type ExploreCity = {
    name: string;
    locationCount: number;
    clubCount: number;
    sportCount: number;
    color: string | null;
    lat: number | null;
    lng: number | null;
};

type ExploreFilters = {
    sport: string | null;
    facilities: number[];
    search: string | null;
    /** The way in the visitor narrowed to, if any. */
    way: string | null;
};

const props = defineProps<{
    city: string | null;
    cities: ExploreCity[];
    filters: ExploreFilters;
    locations: ExploreLocation[];
    sports: ExploreSport[];
    facilities: { id: number; name: string; icon: string | null }[];
}>();

const view = ref<'location' | 'sport'>('location');
const search = ref(props.filters.search ?? '');

// City picker: typing narrows the list, for the towns that are not among the
// first cards. Diacritics-insensitive, so "brasov" finds "Brașov".
const cityQuery = ref('');

function normalize(value: string): string {
    return value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

const matchingCities = computed(() => {
    const needle = normalize(cityQuery.value.trim());

    return needle
        ? props.cities.filter((c) => normalize(c.name).includes(needle))
        : props.cities;
});

function chooseCity(name: string) {
    // Carry the sport over, or arriving from the sports index would drop the
    // very thing the visitor came here for.
    const params: Record<string, string> = { oras: name };

    if (props.filters.sport) {
        params.sport = props.filters.sport;
    }

    router.get(explore.url(), params, { preserveScroll: false });
}

/**
 * Push the filters into the URL so the list, the map and the counters all come
 * back from the server in sync — and the page stays shareable.
 */
function applyFilters(
    changed: Partial<Record<string, string | number | number[] | null>>,
) {
    const next = {
        oras: props.city,
        sport: props.filters.sport,
        facilitati: props.filters.facilities,
        cauta: props.filters.search,
        ...changed,
    };

    router.get(
        explore.url(),
        Object.fromEntries(
            // An empty array is truthy, so it needs its own emptiness check or
            // a cleared amenity filter would stay in the URL forever.
            Object.entries(next).filter(([, v]) =>
                Array.isArray(v) ? v.length > 0 : v,
            ),
        ),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

/** Amenities are cumulative: each tick narrows the list further. */
function toggleFacility(id: number) {
    const selected = props.filters.facilities;

    applyFilters({
        facilitati: selected.includes(id)
            ? selected.filter((f) => f !== id)
            : [...selected, id],
    });
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

function haversineKm(
    aLat: number,
    aLng: number,
    bLat: number,
    bLng: number,
): number {
    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const dLat = toRad(bLat - aLat);
    const dLng = toRad(bLng - aLng);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * Math.sin(dLng / 2) ** 2;

    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function distanceKm(loc: ExploreLocation): number | null {
    if (!myPosition.value || loc.lat === null || loc.lng === null) {
        return null;
    }

    return haversineKm(
        myPosition.value.lat,
        myPosition.value.lng,
        loc.lat,
        loc.lng,
    );
}

// Optional shortcut on the picker: sharing a position only ever picks the city
// for you, never anything finer. Declining costs nothing — the cards are there.
const detecting = ref(false);
const detectFailed = ref(false);

function useMyCity() {
    if (!navigator.geolocation) {
        detectFailed.value = true;

        return;
    }

    detecting.value = true;
    detectFailed.value = false;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            detecting.value = false;

            const nearest = props.cities
                .filter((c) => c.lat !== null && c.lng !== null)
                .map((c) => ({
                    city: c,
                    km: haversineKm(
                        position.coords.latitude,
                        position.coords.longitude,
                        c.lat as number,
                        c.lng as number,
                    ),
                }))
                .sort((a, b) => a.km - b.km)[0];

            if (nearest) {
                chooseCity(nearest.city.name);
            } else {
                detectFailed.value = true;
            }
        },
        () => {
            detecting.value = false;
            detectFailed.value = true;
        },
        { timeout: 8000 },
    );
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

const mapKey = computed(() => usePage().props.maps?.key ?? '');

const mappable = computed(() =>
    props.locations.filter((l) => l.lat !== null && l.lng !== null),
);

/** Where to open the map before the pins have been fitted into view. */
const cityCenter = computed(() => {
    const current = props.cities.find((c) => c.name === props.city);

    return current?.lat !== null &&
        current?.lat !== undefined &&
        current.lng !== null
        ? { lat: current.lat, lng: current.lng as number }
        : null;
});

const activePin = ref<string | null>(null);

const activePinLocation = computed(
    () => props.locations.find((l) => l.slug === activePin.value) ?? null,
);

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
        <SiteNav />

        <!-- ===== No city yet: pick one. Nothing else on screen. ===== -->
        <template v-if="!city">
            <div class="mx-auto max-w-[900px] px-5 pt-12 pb-16 text-center">
                <span
                    class="mb-3.5 block font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass-deep uppercase"
                >
                    {{ cities.length }}
                    {{ cities.length === 1 ? 'oraș' : 'orașe' }}
                    {{ filters.sport ? 'cu acest sport' : 'pe hartă' }}
                </span>
                <h1
                    class="font-archivo text-[clamp(28px,5.5vw,44px)] leading-[1.05] font-extrabold tracking-[-0.02em]"
                >
                    {{
                        filters.sport
                            ? 'În ce oraș cauți?'
                            : 'În ce oraș faci sport?'
                    }}
                </h1>
                <p class="mx-auto mt-3.5 max-w-[46ch] text-[16px] text-sage">
                    Alege-ți orașul și vezi imediat sălile, bazinele și
                    terenurile din el, cu cluburile care țin antrenamente acolo.
                </p>

                <!-- Arrived from the sports index: say what is being filtered,
                     and leave a way to drop it without going back. -->
                <div v-if="filters.sport" class="mt-4 flex justify-center">
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-ink py-2 pr-2 pl-4 text-[13px] font-semibold text-white"
                    >
                        Cauți: <b>{{ filters.sport }}</b>
                        <Link
                            :href="explore.url()"
                            class="flex h-[22px] w-[22px] items-center justify-center rounded-full bg-white/15 hover:bg-white/30"
                        >
                            ✕
                        </Link>
                    </span>
                </div>

                <div class="mx-auto mt-7 max-w-[420px]">
                    <!-- The fastest route first: one tap and you are in your
                         own city. Everything below is the way out for anyone
                         who would rather not share a position. -->
                    <button
                        type="button"
                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2.5 rounded-full bg-clay px-6 py-3.5 text-[15px] font-semibold text-white shadow-[0_16px_30px_-16px_rgba(255,90,44,0.7)] transition hover:bg-[#e6501c] disabled:opacity-70"
                        :disabled="detecting"
                        @click="useMyCity"
                    >
                        <span
                            class="flex h-[22px] w-[22px] rotate-[-45deg] items-center justify-center rounded-[50%_50%_50%_0] bg-white/25"
                        >
                            <span class="rotate-45 text-[11px]">📍</span>
                        </span>
                        {{ detecting ? 'Te caut…' : 'Lângă locația mea' }}
                    </button>
                    <p v-if="detectFailed" class="mt-2 text-[12.5px] text-clay">
                        N-am putut afla unde ești — caută-ți orașul mai jos.
                    </p>

                    <div
                        class="my-4 flex items-center gap-3 text-[12px] font-semibold text-sage"
                    >
                        <span class="h-px flex-1 bg-line" />
                        sau
                        <span class="h-px flex-1 bg-line" />
                    </div>

                    <input
                        v-model="cityQuery"
                        type="search"
                        placeholder="Caută orașul tău…"
                        class="w-full rounded-[14px] border border-line bg-white px-4.5 py-3.5 text-[15px] shadow-[0_20px_40px_-30px_rgba(11,20,16,0.35)] outline-none focus:border-grass"
                    />
                </div>
            </div>

            <div class="mx-auto max-w-[1180px] px-5 pb-20">
                <div
                    v-if="!matchingCities.length"
                    class="py-10 text-center text-[14.5px] text-sage"
                >
                    Niciun oraș care să semene cu „{{ cityQuery }}”.
                    <br />
                    <Link
                        :href="organizationApplication.create.url()"
                        class="font-semibold text-grass-deep"
                    >
                        Listează primul club de acolo →
                    </Link>
                </div>
                <div
                    v-else
                    class="grid grid-cols-2 gap-3.5 md:grid-cols-3 lg:grid-cols-4"
                >
                    <button
                        v-for="c in matchingCities"
                        :key="c.name"
                        type="button"
                        class="group cursor-pointer overflow-hidden rounded-[18px] border border-line bg-white text-left transition hover:-translate-y-1 hover:border-grass hover:shadow-[0_24px_44px_-26px_rgba(11,20,16,0.45)]"
                        @click="chooseCity(c.name)"
                    >
                        <div
                            class="h-[52px]"
                            :style="{ background: sportGradient(c.color) }"
                        />
                        <div class="px-4 pt-3 pb-3.5">
                            <div
                                class="font-archivo text-[16.5px] leading-tight font-extrabold group-hover:text-grass-deep"
                            >
                                {{ c.name }}
                            </div>
                            <!-- Three numbers, not a sentence: they are what a
                                 visitor actually compares cities on. -->
                            <div
                                class="mt-2.5 grid grid-cols-3 gap-1 border-t border-line pt-2.5"
                            >
                                <div>
                                    <div
                                        class="font-jetbrains text-[15px] leading-none font-bold"
                                    >
                                        {{ c.locationCount }}
                                    </div>
                                    <div
                                        class="mt-1 text-[9.5px] leading-tight text-sage"
                                    >
                                        {{
                                            c.locationCount === 1
                                                ? 'locație'
                                                : 'locații'
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="font-jetbrains text-[15px] leading-none font-bold"
                                    >
                                        {{ c.clubCount }}
                                    </div>
                                    <div
                                        class="mt-1 text-[9.5px] leading-tight text-sage"
                                    >
                                        {{
                                            c.clubCount === 1
                                                ? 'club'
                                                : 'cluburi'
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="font-jetbrains text-[15px] leading-none font-bold"
                                    >
                                        {{ c.sportCount }}
                                    </div>
                                    <div
                                        class="mt-1 text-[9.5px] leading-tight text-sage"
                                    >
                                        {{
                                            c.sportCount === 1
                                                ? 'sport'
                                                : 'sporturi'
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </template>

        <!-- ===== City chosen: locations, with the filters that now matter ===== -->
        <template v-else>
            <!-- Search bar -->
            <div
                class="sticky top-[60px] z-[60] border-b border-line bg-white py-3.5"
            >
                <div class="mx-auto max-w-[1280px] px-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="flex items-center gap-2 rounded-[10px] bg-[#eaf6ef] py-2 pr-2 pl-3.5"
                        >
                            <span
                                class="font-archivo text-[14.5px] font-extrabold text-grass-deep"
                            >
                                📍 {{ city }}
                            </span>
                            <Link
                                :href="explore.url()"
                                class="rounded-md bg-white px-2 py-1 font-jetbrains text-[10.5px] font-bold text-sage transition hover:text-grass-deep"
                            >
                                SCHIMBĂ
                            </Link>
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Caută o locație…"
                            class="min-w-[160px] flex-1 rounded-[10px] border border-line bg-[#f7f8f6] px-3.5 py-2.5 text-sm"
                        />
                    </div>
                    <!-- Amenities. Wrapped, never a scrolling strip: options
                         hidden off the right edge are options nobody uses. -->
                    <div v-if="facilities.length" class="pt-3">
                        <div class="mb-2 flex items-center gap-2.5">
                            <span
                                class="font-jetbrains text-[10.5px] font-bold tracking-[0.1em] text-sage uppercase"
                            >
                                Facilități locație
                            </span>
                            <button
                                v-if="filters.facilities.length"
                                type="button"
                                class="cursor-pointer font-jetbrains text-[10.5px] font-bold text-clay uppercase transition hover:underline"
                                @click="applyFilters({ facilitati: [] })"
                            >
                                Șterge ({{ filters.facilities.length }})
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="facility in facilities"
                                :key="facility.id"
                                type="button"
                                class="cursor-pointer rounded-full border-[1.5px] px-3 py-[6px] text-[12.5px] font-semibold transition"
                                :class="
                                    filters.facilities.includes(facility.id)
                                        ? 'border-grass bg-[#eaf6ef] text-grass-deep'
                                        : 'border-line bg-white hover:border-grass'
                                "
                                :aria-pressed="
                                    filters.facilities.includes(facility.id)
                                "
                                @click="toggleFacility(facility.id)"
                            >
                                <span v-if="facility.icon" class="mr-1">
                                    {{ facility.icon }}
                                </span>
                                {{ facility.name }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map -->
            <div class="relative h-[260px] bg-[#eef2ea] min-[900px]:h-[380px]">
                <LocationsMap
                    v-if="mapKey && mappable.length"
                    :locations="mappable"
                    :api-key="mapKey"
                    :center="cityCenter"
                    :active-slug="activePin"
                    @select="activePin = $event"
                />
                <div
                    v-else
                    class="absolute inset-0 flex items-center justify-center px-5 text-center text-[13.5px] text-sage"
                >
                    {{
                        mapKey
                            ? 'Nicio locație cu coordonate pe hartă.'
                            : 'Harta nu este configurată.'
                    }}
                </div>

                <div
                    v-if="activePinLocation"
                    class="absolute bottom-3.5 left-3.5 z-[2] w-[220px] rounded-xl border border-line bg-white px-3.5 py-3 shadow-[0_20px_40px_-20px_rgba(0,0,0,0.3)]"
                >
                    <Link
                        :href="locationHref(activePinLocation)"
                        class="font-archivo text-[14px] font-extrabold hover:text-grass-deep"
                    >
                        {{ activePinLocation.name }}
                    </Link>
                    <div
                        class="mt-1 font-jetbrains text-[10.5px] text-grass-deep"
                    >
                        {{ activePinLocation.clubCount }}
                        {{
                            activePinLocation.clubCount === 1
                                ? 'CLUB'
                                : 'CLUBURI'
                        }}
                        <template v-if="activePinLocation.live">
                            · ACUM ACTIV</template
                        >
                    </div>
                </div>

                <button
                    type="button"
                    class="absolute right-3.5 bottom-3.5 z-[2] flex cursor-pointer items-center gap-1.5 rounded-[10px] border border-line bg-white px-3 py-2 text-[12.5px] font-semibold shadow-[0_8px_20px_-10px_rgba(0,0,0,0.3)] disabled:opacity-60"
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
                            {{
                                locations.length === 1
                                    ? 'rezultat'
                                    : 'rezultate'
                            }}
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
                                <!-- How you get in, before what is played here:
                                     somebody who wants to swim after work is
                                     choosing between these three, not between
                                     sports. -->
                                <div class="mb-2.5 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="way in loc.ways"
                                        :key="way.key"
                                        class="rounded-[7px] border-[1.5px] px-2.5 py-[3px] text-[11px] font-bold"
                                        :class="
                                            filters.way === way.key
                                                ? 'border-grass-deep bg-grass-deep text-white'
                                                : 'border-line bg-[#f7f8f6] text-sage'
                                        "
                                    >
                                        {{ way.label }}
                                    </span>
                                </div>
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
                                        ><b class="text-ink">{{
                                            loc.clubCount
                                        }}</b>
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
                                :style="{
                                    background: sportGradient(sport.color),
                                }"
                            >
                                <span class="text-[30px]">{{
                                    sport.icon
                                }}</span>
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
        </template>
    </div>
</template>
