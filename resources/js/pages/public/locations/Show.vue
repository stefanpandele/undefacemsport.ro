<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SiteNav from '@/components/SiteNav.vue';
import ClubBlock from '@/components/sports/ClubBlock.vue';
import LocationsMap from '@/components/sports/LocationsMap.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { gradientStyle, sportGradient } from '@/lib/gradients';
import clubApplication from '@/routes/club-application';
import type {
    Person,
    LocationDetail,
    ScheduleSlot,
    WayIn,
} from '@/types/sports';

const props = defineProps<{
    location: LocationDetail;
    activeSport: string | null;
}>();

const mapKey = computed(() => usePage().props.maps?.key ?? '');

// Hero carousel: one slide per sport played here, since locations carry no
// photos of their own yet.
const slides = computed(() =>
    props.location.sports.length
        ? props.location.sports.map((sport) => sportGradient(sport.color))
        : [gradientStyle('g2')],
);

const activeSlide = ref(0);

// Preselected server-side from ?sport= when arriving from the explore page.
const activeSport = ref<string | null>(props.activeSport);

// Distance is only known once the visitor shares their position.
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

const distance = computed(() => {
    const { lat, lng } = props.location;

    if (!myPosition.value || lat === null || lng === null) {
        return null;
    }

    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const dLat = toRad(lat - myPosition.value.lat);
    const dLng = toRad(lng - myPosition.value.lng);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(myPosition.value.lat)) *
            Math.cos(toRad(lat)) *
            Math.sin(dLng / 2) ** 2;
    const km = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return `${km < 10 ? km.toFixed(1) : Math.round(km)} km de mine`;
});

// Every way into the active sport here: a club's programme, walking in off the
// street, booking the whole space. Only the ones that exist at this address.
const ways = computed<WayIn[]>(() =>
    activeSport.value ? (props.location.ways[activeSport.value] ?? []) : [],
);

const chosenWay = ref<string | null>(null);

// A choice made for swimming means nothing for basketball.
watch(activeSport, () => {
    chosenWay.value = null;
});

const activeWay = computed<WayIn | null>(
    () =>
        ways.value.find((way) => way.key === chosenWay.value) ??
        ways.value[0] ??
        null,
);

const filteredClubs = computed(() =>
    activeSport.value
        ? props.location.clubs.filter((c) => c.sport === activeSport.value)
        : [],
);

// Person modal
const activeCoach = ref<Person | null>(null);
const coachOpen = ref(false);

function openCoach(coach: Person) {
    activeCoach.value = coach;
    coachOpen.value = true;
}

// "Who else is in the hall" modal, opened from a schedule slot's info button.
const activeHallSlot = ref<ScheduleSlot | null>(null);
const hallOpen = ref(false);

function openHall(slot: ScheduleSlot) {
    activeHallSlot.value = slot;
    hallOpen.value = true;
}

/**
 * Jump to that club's block further down the page. The modal has to close
 * first, or its overlay would swallow the scroll.
 */
function goToClub(key: string) {
    hallOpen.value = false;

    requestAnimationFrame(() => {
        document
            .getElementById(`club-${key}`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}
</script>

<template>
    <Head :title="`${location.name} — Unde Facem Sport`" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[1180px] px-5">
            <!-- Hero + map -->
            <div
                class="grid grid-cols-1 gap-3.5 pt-4 min-[900px]:grid-cols-[2fr_1fr]"
            >
                <div
                    class="relative h-[220px] overflow-hidden rounded-[20px] min-[900px]:h-[320px]"
                >
                    <div
                        v-for="(slide, i) in slides"
                        :key="i"
                        class="absolute inset-0 transition-opacity duration-500"
                        :style="{
                            background: slide,
                            opacity: activeSlide === i ? 1 : 0,
                        }"
                    />
                    <div
                        class="absolute inset-0 z-[2]"
                        style="
                            background: linear-gradient(
                                to top,
                                rgba(4, 8, 6, 0.88) 0%,
                                rgba(4, 8, 6, 0.35) 45%,
                                transparent 75%
                            );
                        "
                    />
                    <div
                        class="relative z-[3] flex h-full flex-col justify-end p-5 text-white"
                    >
                        <h1
                            class="font-archivo text-[clamp(22px,3.4vw,32px)] font-extrabold"
                        >
                            {{ location.name }}
                        </h1>
                        <div
                            class="mt-1.5 font-jetbrains text-[12.5px] opacity-95"
                        >
                            📍 {{ location.address.toUpperCase() }}
                        </div>
                    </div>
                    <div
                        v-if="slides.length > 1"
                        class="absolute right-5 bottom-4 z-[4] flex gap-[7px]"
                    >
                        <button
                            v-for="(slide, i) in slides"
                            :key="i"
                            type="button"
                            class="h-[7px] rounded-full transition-all"
                            :class="
                                activeSlide === i
                                    ? 'w-5 bg-white'
                                    : 'w-[7px] bg-white/50'
                            "
                            @click="activeSlide = i"
                        />
                    </div>
                </div>
                <div
                    class="relative h-[180px] overflow-hidden rounded-[20px] bg-[#eef2ea] min-[900px]:h-[320px]"
                >
                    <LocationsMap
                        v-if="
                            mapKey &&
                            location.lat !== null &&
                            location.lng !== null
                        "
                        :locations="[location]"
                        :api-key="mapKey"
                        :single-zoom="16"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center px-4 text-center text-[13px] text-sage"
                    >
                        {{
                            mapKey
                                ? 'Locația nu are încă coordonate pe hartă.'
                                : 'Harta nu este configurată.'
                        }}
                    </div>
                    <div
                        v-if="distance"
                        class="absolute bottom-3 left-3 z-[2] rounded-[9px] bg-ink px-2.5 py-[7px] font-jetbrains text-[11px] font-bold whitespace-nowrap text-white"
                    >
                        📍 {{ distance }}
                    </div>
                    <button
                        type="button"
                        class="absolute right-3 bottom-3 z-[2] cursor-pointer rounded-[10px] border border-line bg-white px-2.5 py-[7px] text-xs font-semibold disabled:opacity-60"
                        :disabled="locating"
                        @click="locateMe"
                    >
                        📍 {{ locating ? 'Te caut…' : 'Locația mea' }}
                    </button>
                </div>
            </div>

            <!-- Facilities + CTA -->
            <section class="py-6.5">
                <div
                    class="grid grid-cols-1 gap-3.5 min-[900px]:grid-cols-[2fr_1fr]"
                >
                    <div
                        class="relative overflow-hidden rounded-[18px] border-[1.5px] border-dashed border-line bg-[#fafaf7] px-5 py-5.5"
                    >
                        <div class="relative mb-4 flex items-center gap-2.5">
                            <h2 class="font-archivo text-[19px] font-extrabold">
                                Facilități
                            </h2>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#eaf6ef] px-2.5 py-1.5 font-jetbrains text-[11.5px] font-bold text-grass-deep"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-grass"
                                />
                                {{ location.facilities.length }} TOTAL
                            </span>
                        </div>
                        <div
                            v-if="!location.facilities.length"
                            class="relative text-[13.5px] text-sage"
                        >
                            Nicio facilitate înregistrată încă pentru această
                            locație.
                        </div>
                        <div
                            class="relative grid grid-cols-2 gap-x-2.5 gap-y-4 sm:grid-cols-4"
                        >
                            <div
                                v-for="fac in location.facilities"
                                :key="fac.label"
                                class="flex flex-col items-center gap-2 text-center transition hover:-translate-y-[3px]"
                            >
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full border-[1.5px] border-line bg-white text-[19px] shadow-[0_8px_18px_-10px_rgba(11,20,16,0.25)]"
                                >
                                    {{ fac.icon }}
                                </div>
                                <div
                                    class="text-[11.5px] leading-tight font-semibold"
                                >
                                    {{ fac.label }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex flex-col justify-center rounded-[18px] border-[1.5px] border-dashed border-line bg-[#fafaf7] p-5.5 text-center"
                    >
                        <div class="mb-2.5 text-[26px]">🙋</div>
                        <h3
                            class="mb-1.5 font-archivo text-[17px] font-extrabold"
                        >
                            Ții lecții aici?
                        </h3>
                        <p class="mb-4 text-[13.5px] text-sage">
                            Adaugă-ți clubul și programul, ca lumea să te
                            găsească.
                        </p>
                        <Link
                            :href="clubApplication.create.url()"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-clay px-5 py-3 text-[14.5px] font-semibold text-white transition hover:bg-[#e6501c]"
                        >
                            Adaugă-ți clubul
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Sport selector -->
            <section class="py-6.5">
                <h2 class="mb-3.5 font-archivo text-[19px] font-extrabold">
                    Ce sport te interesează?
                </h2>
                <div class="flex flex-wrap gap-3.5 py-1">
                    <button
                        v-for="sport in location.sports"
                        :key="sport.key"
                        type="button"
                        class="w-[148px] overflow-hidden rounded-2xl border-[1.5px] bg-white transition hover:-translate-y-1"
                        :class="
                            activeSport === sport.key
                                ? 'border-grass shadow-[0_0_0_3px_rgba(21,184,119,0.18)]'
                                : 'border-line'
                        "
                        @click="activeSport = sport.key"
                    >
                        <div
                            class="flex h-[66px] items-center justify-center text-[28px] text-white"
                            :style="{ background: sportGradient(sport.color) }"
                        >
                            {{ sport.icon }}
                        </div>
                        <div class="px-2 pt-2.5 pb-3 text-center">
                            <div class="font-archivo text-sm font-extrabold">
                                {{ sport.label }}
                            </div>
                            <div
                                class="mt-[3px] font-jetbrains text-[10px] font-semibold"
                                :class="
                                    activeSport === sport.key
                                        ? 'text-grass-deep'
                                        : 'text-sage'
                                "
                            >
                                {{ sport.clubCount }}
                                {{ sport.clubCount === 1 ? 'CLUB' : 'CLUBURI' }}
                            </div>
                        </div>
                    </button>
                </div>

                <div
                    v-if="!activeSport"
                    class="mt-4.5 rounded-2xl border-[1.5px] border-dashed border-line px-5 py-10 text-center text-sage"
                >
                    <div class="mb-2.5 text-[26px]">
                        {{ location.sports.length ? '👆' : '🏟️' }}
                    </div>
                    <p class="mx-auto max-w-[36ch] text-sm">
                        <template v-if="location.sports.length">
                            Alege un sport de mai sus ca să vezi cluburile și
                            orarul disponibil pentru el, aici.
                        </template>
                        <template v-else>
                            Niciun club nu ține încă lecții aici.
                        </template>
                    </p>
                </div>
            </section>

            <!-- How you get in -->
            <div v-if="activeSport" class="pb-15">
                <!-- Only ever a chooser when there is a choice: one way in is not
                     an option, it is just the offer. -->
                <template v-if="ways.length > 1">
                    <h2 class="mb-3.5 font-archivo text-[19px] font-extrabold">
                        Cum vrei să intri aici?
                    </h2>
                    <div
                        class="mb-6 grid gap-2.5"
                        style="
                            grid-template-columns: repeat(
                                auto-fit,
                                minmax(215px, 1fr)
                            );
                        "
                    >
                        <button
                            v-for="way in ways"
                            :key="way.key"
                            type="button"
                            class="flex flex-col gap-1 rounded-2xl border-[1.5px] px-4 py-3.5 text-left transition"
                            :class="
                                activeWay?.key === way.key
                                    ? 'border-grass bg-white shadow-[0_0_0_3px_rgba(21,184,119,0.18)]'
                                    : 'border-line bg-[#f4f6f1] hover:border-grass/45'
                            "
                            @click="chosenWay = way.key"
                        >
                            <span class="font-archivo text-base font-extrabold">
                                {{ way.verb }}
                            </span>
                            <span class="text-[13px] text-sage">{{
                                way.how
                            }}</span>
                            <span
                                v-if="way.price"
                                class="mt-0.5 font-jetbrains text-[13px] font-semibold"
                            >
                                {{ way.price }}
                            </span>
                            <span class="font-jetbrains text-[11px] text-sage">
                                {{ way.who }}
                            </span>
                        </button>
                    </div>
                </template>

                <!-- Organised programmes: the clubs, untouched. Nothing about a
                     venue's hours belongs in here. -->
                <template v-if="activeWay?.key === 'organizat'">
                    <h2
                        class="mb-3.5 flex items-center gap-2.5 font-archivo text-[19px] font-extrabold"
                    >
                        Cluburi
                        <span
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#eaf6ef] px-2.5 py-1.5 font-jetbrains text-[11.5px] font-bold text-grass-deep"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-grass" />
                            {{ filteredClubs.length }}
                            {{
                                filteredClubs.length === 1 ? 'CLUB' : 'CLUBURI'
                            }}
                        </span>
                    </h2>

                    <ClubBlock
                        v-for="club in filteredClubs"
                        :key="club.key"
                        :club="club"
                        @open-coach="openCoach"
                        @open-hall="openHall"
                    />
                </template>

                <!-- Walking in, or booking the whole space. -->
                <template v-else-if="activeWay">
                    <div class="grid gap-3.5">
                        <article
                            v-for="space in activeWay.spaces"
                            :key="space.id"
                            class="rounded-2xl border-[1.5px] border-line bg-white p-5"
                        >
                            <header
                                class="flex flex-wrap items-baseline justify-between gap-2.5"
                            >
                                <h3
                                    class="font-archivo text-base font-extrabold"
                                >
                                    {{ space.name }}
                                </h3>
                                <span
                                    v-if="space.openNow"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#eaf6ef] px-2.5 py-1 font-jetbrains text-[11px] font-bold text-grass-deep"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-grass"
                                    />
                                    <template v-if="space.closesAt">
                                        DESCHIS PÂNĂ LA {{ space.closesAt }}
                                    </template>
                                    <template v-else>DESCHIS ACUM</template>
                                </span>
                                <span
                                    v-else
                                    class="rounded-lg bg-[#f4f6f1] px-2.5 py-1 font-jetbrains text-[11px] font-bold text-sage"
                                >
                                    ÎNCHIS ACUM
                                </span>
                            </header>

                            <p class="mt-1 text-[13px] text-sage">
                                <template v-if="space.operator">
                                    Operat de {{ space.operator }}
                                </template>
                                <template v-else>
                                    Spațiu public, neadministrat
                                </template>
                            </p>

                            <p
                                v-if="space.price"
                                class="mt-2.5 font-jetbrains text-[15px] font-bold"
                                :class="space.isFree ? 'text-grass-deep' : ''"
                            >
                                {{ space.price }}
                            </p>
                            <p v-else class="mt-2.5 text-[13px] text-sage">
                                Preț nespecificat — întreabă la fața locului.
                            </p>
                            <p
                                v-if="space.priceNotes"
                                class="mt-1 text-[13px] text-sage"
                            >
                                {{ space.priceNotes }}
                            </p>

                            <!-- Today first: someone deciding now needs today, not
                                 the shape of the week. -->
                            <div v-if="space.today.length" class="mt-4">
                                <p
                                    class="mb-1.5 font-jetbrains text-[10px] font-bold tracking-[0.11em] text-sage uppercase"
                                >
                                    Azi
                                </p>
                                <ul class="flex flex-wrap gap-1.5">
                                    <li
                                        v-for="interval in space.today"
                                        :key="interval.start"
                                        class="rounded-lg border border-line px-2.5 py-1 font-jetbrains text-[12px]"
                                    >
                                        {{ interval.start }}–{{ interval.end }}
                                        <span
                                            v-if="interval.price !== null"
                                            class="text-sage"
                                        >
                                            · {{ interval.price }} lei
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <details class="mt-3.5">
                                <summary
                                    class="cursor-pointer font-jetbrains text-[11px] font-bold tracking-[0.08em] text-sage uppercase"
                                >
                                    Programul săptămânii
                                </summary>
                                <dl
                                    class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 font-jetbrains text-[12px]"
                                >
                                    <template
                                        v-for="row in space.week"
                                        :key="row.day"
                                    >
                                        <dt class="text-sage">{{ row.day }}</dt>
                                        <dd
                                            :class="
                                                row.hours === 'închis'
                                                    ? 'text-clay'
                                                    : ''
                                            "
                                        >
                                            {{ row.hours }}
                                        </dd>
                                    </template>
                                </dl>
                            </details>

                            <ul
                                class="mt-3.5 flex flex-wrap gap-1.5 text-[12px] text-sage"
                            >
                                <li
                                    v-if="space.capacity"
                                    class="rounded-full border border-line px-2.5 py-0.5"
                                >
                                    {{ space.capacity }} locuri
                                </li>
                                <li
                                    v-if="space.isIndoor"
                                    class="rounded-full border border-line px-2.5 py-0.5"
                                >
                                    Acoperit
                                </li>
                                <li
                                    v-if="space.hasFloodlights"
                                    class="rounded-full border border-line px-2.5 py-0.5"
                                >
                                    Nocturnă
                                </li>
                                <li
                                    v-if="space.surface"
                                    class="rounded-full border border-line px-2.5 py-0.5"
                                >
                                    {{ space.surface }}
                                </li>
                            </ul>

                            <!-- Nobody maintains an unmanaged record, so the page
                                 says how old it is instead of pretending. -->
                            <p
                                v-if="space.unmanaged"
                                class="mt-3.5 border-l-[3px] border-clay pl-3 text-[12px] text-sage"
                            >
                                <template v-if="space.lastVerified">
                                    Verificat {{ space.lastVerified }}.
                                </template>
                                <template v-else>Neverificat încă.</template>
                                Dacă informația e greșită, spune-ne.
                            </p>
                        </article>
                    </div>
                </template>

                <div v-else class="py-8 text-center text-sage">
                    Nimeni nu oferă încă acest sport aici.
                    <Link
                        :href="clubApplication.create.url()"
                        class="font-semibold text-grass-deep"
                    >
                        Fii primul care se listează →
                    </Link>
                </div>
            </div>

            <!-- Paid things that are not a sport. Outside the sport sections
                 because they answer a different question: not "where do I play"
                 but "what else can I get here". -->
            <section v-if="location.extras.length" class="pb-15">
                <h2 class="mb-1 font-archivo text-[19px] font-extrabold">
                    Și, la fața locului
                </h2>
                <p class="mb-3.5 text-[13px] text-sage">
                    Se plătesc separat de sport.
                </p>
                <div class="grid gap-2.5 sm:grid-cols-2">
                    <div
                        v-for="extra in location.extras"
                        :key="extra.key"
                        class="flex items-start gap-3 rounded-2xl border-[1.5px] border-line bg-white px-4 py-3.5"
                    >
                        <span class="text-[22px] leading-none">{{
                            extra.icon
                        }}</span>
                        <div class="min-w-0">
                            <div
                                class="font-archivo text-[15px] font-extrabold"
                            >
                                {{ extra.name }}
                            </div>
                            <div
                                v-if="extra.detail"
                                class="mt-0.5 font-jetbrains text-[13px] font-semibold"
                            >
                                {{ extra.detail }}
                                <span
                                    v-if="extra.meta"
                                    class="font-normal text-sage"
                                >
                                    · {{ extra.meta }}
                                </span>
                            </div>
                            <div v-else class="mt-0.5 text-[13px] text-sage">
                                Preț nespecificat
                            </div>
                            <div
                                v-if="extra.by"
                                class="mt-0.5 font-jetbrains text-[11px] text-sage"
                            >
                                {{ extra.by }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Today at this location. A view of the place, not of anybody's
                 offer — which is why it sits outside the sport sections. -->
            <section v-if="location.day" class="pb-15">
                <h2 class="mb-1 font-archivo text-[19px] font-extrabold">
                    {{ location.day.label }}
                </h2>
                <p class="mb-3.5 text-[13px] text-sage">
                    Ce se întâmplă în fiecare spațiu de aici, indiferent cine îl
                    oferă.
                </p>
                <div class="overflow-x-auto">
                    <div class="min-w-[560px]">
                        <div
                            v-for="row in location.day.rows"
                            :key="row.name"
                            class="mb-1.5 grid grid-cols-[120px_1fr] items-center gap-2.5"
                        >
                            <div class="text-[13px]">
                                {{ row.name }}
                                <small
                                    v-if="row.sub"
                                    class="block font-jetbrains text-[11px] text-sage"
                                >
                                    {{ row.sub }}
                                </small>
                            </div>
                            <div
                                class="relative h-8 rounded-lg bg-[#f4f6f1]"
                                :style="{
                                    display: 'grid',
                                    gridTemplateColumns: `repeat(${location.day.to - location.day.from}, 1fr)`,
                                }"
                            >
                                <div
                                    v-for="bar in row.bars"
                                    :key="`${bar.start}-${bar.end}`"
                                    class="my-1 flex items-center overflow-hidden rounded-md bg-grass px-2 font-jetbrains text-[11px] whitespace-nowrap text-white"
                                    :style="{
                                        gridColumn: `${Math.max(1, bar.start - location.day.from + 1)} / ${Math.max(2, bar.end - location.day.from + 1)}`,
                                    }"
                                >
                                    {{ bar.label }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="mt-1 grid grid-cols-[120px_1fr] gap-2.5 font-jetbrains text-[10px] text-sage"
                        >
                            <span />
                            <div
                                :style="{
                                    display: 'grid',
                                    gridTemplateColumns: `repeat(${location.day.to - location.day.from}, 1fr)`,
                                }"
                            >
                                <span
                                    v-for="hour in location.day.to -
                                    location.day.from"
                                    :key="hour"
                                >
                                    {{
                                        String(
                                            location.day.from + hour - 1,
                                        ).padStart(2, '0')
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Person modal -->
        <Dialog v-model:open="coachOpen">
            <DialogContent class="max-w-[320px] text-center">
                <DialogHeader>
                    <div
                        class="mx-auto mb-4 flex h-[140px] w-[140px] items-center justify-center overflow-hidden rounded-full border-4 border-white text-[56px] shadow-[0_0_0_2px_var(--color-line)]"
                        :style="
                            activeCoach?.photo
                                ? {}
                                : { background: activeCoach?.gradient }
                        "
                    >
                        <img
                            v-if="activeCoach?.photo"
                            :src="activeCoach.photo"
                            :alt="activeCoach.name"
                            class="h-full w-full object-cover"
                        />
                        <template v-else>🧑‍🏫</template>
                    </div>
                    <DialogTitle class="font-archivo text-[19px]">
                        {{ activeCoach?.name }}
                    </DialogTitle>
                </DialogHeader>
                <div class="text-[13px] font-semibold text-grass-deep">
                    {{ activeCoach?.role }}
                </div>
                <p class="mt-3.5 text-[13px] leading-relaxed text-sage">
                    {{ activeCoach?.bio }}
                </p>
            </DialogContent>
        </Dialog>

        <!-- Who else has the hall at this interval -->
        <Dialog v-model:open="hallOpen">
            <DialogContent class="max-w-[380px]">
                <DialogHeader>
                    <DialogTitle class="font-archivo text-[19px]">
                        În aceeași sală, {{ activeHallSlot?.time }}
                    </DialogTitle>
                </DialogHeader>
                <p class="text-[13px] leading-relaxed text-sage">
                    {{
                        activeHallSlot?.otherClubs.length === 1
                            ? 'Încă un club'
                            : `Încă ${activeHallSlot?.otherClubs.length} cluburi`
                    }}
                    țin antrenament aici în acest interval.
                </p>
                <div class="mt-1 flex flex-col gap-1.5">
                    <button
                        v-for="club in activeHallSlot?.otherClubs ?? []"
                        :key="club.key"
                        type="button"
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-line bg-white px-3.5 py-3 text-left transition hover:border-grass hover:bg-[#f7faf8]"
                        @click="goToClub(club.key)"
                    >
                        <span class="text-[14px] font-semibold text-ink">
                            {{ club.name }}
                        </span>
                        <span
                            class="shrink-0 font-jetbrains text-[11px] font-bold text-grass-deep"
                        >
                            vezi orarul →
                        </span>
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
