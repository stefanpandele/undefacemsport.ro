<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import SiteNav from '@/components/SiteNav.vue';
import WeekSchedule from '@/components/sports/WeekSchedule.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { gradientStyle, sportGradient } from '@/lib/gradients';
import type { Person, ScheduleDay } from '@/types/sports';

type OrganizationPerson = Person & { sportIcon: string; sportLabel: string };

type CourseLocation = {
    slug: string;
    name: string;
    city: string;
    schedule: ScheduleDay[];
};

/** One sport this organization teaches, with everything the page says about it. */
type Course = {
    key: string;
    label: string;
    icon: string;
    color: string | null;
    title: string;
    trustChips: string[];
    sessionFormat: string[];
    audience: string[];
    ages: string[];
    /** How far along the groups are — a separate axis from who they are for. */
    levels: string[];
    gallery: string[];
    locations: CourseLocation[];
};

type LeisureSpace = {
    id: number;
    name: string;
    sport: string | null;
    location: string | null;
    locationSlug: string | null;
    price: string | null;
    priceNotes: string | null;
    isFree: boolean;
    capacity: number | null;
    isIndoor: boolean | null;
    hasFloodlights: boolean | null;
    surface: string | null;
    openNow: boolean;
    closesAt: string | null;
    week: { day: string; hours: string }[];
};

/** One way into this organization's spaces: turn up, or book the whole thing. */
type LeisureWay = {
    key: string;
    label: string;
    verb: string;
    how: string;
    spaces: LeisureSpace[];
};

/** Sold one appointment at a time: a session, a consultation, a massage. */
type OrganizationService = {
    key: string;
    icon: string;
    name: string;
    specialty: string | null;
    price: string | null;
    priceNotes: string | null;
    duration: string | null;
    description: string;
    person: string | null;
    sports: { key: string; label: string; icon: string }[];
};

type OrganizationProfile = {
    slug: string;
    name: string;
    representative: string;
    about: string;
    phone: string | null;
    socials: { label: string; url: string }[];
    people: OrganizationPerson[];
    locations: { slug: string; name: string; address: string }[];
    tabs: { key: string; label: string }[];
    courses: Course[];
    leisure: LeisureWay[];
    services: OrganizationService[];
};

const props = defineProps<{ organization: OrganizationProfile }>();

/**
 * Which tab opens. The listing a visitor came from puts it in the fragment —
 * `/la/aqua#agrement` from the leisure index — so they land on what they were
 * looking for rather than on whatever this organization does most of.
 */
const activeTab = ref(props.organization.tabs[0]?.key ?? '');

onMounted(() => {
    const requested = window.location.hash.replace('#', '');

    if (props.organization.tabs.some((tab) => tab.key === requested)) {
        activeTab.value = requested;
    }
});

function selectTab(key: string) {
    activeTab.value = key;
    history.replaceState(null, '', `#${key}`);
}

const activeCourse = ref(props.organization.courses[0]?.key ?? '');

const course = computed(() =>
    props.organization.courses.find((c) => c.key === activeCourse.value),
);

// Benefits that describe *who* a sport suits or *how* a session is run get their
// own labeled group instead of blending into the generic chip row, where they
// would be easy to miss.
type HighlightGroup = {
    key: string;
    title: string;
    icon: string;
    items: string[];
    bg: string;
    text: string;
};

const highlightGroups = computed<HighlightGroup[]>(() => {
    const detail = course.value;

    if (!detail) {
        return [];
    }

    return [
        {
            key: 'sessionFormat',
            title: 'Format sesiune',
            icon: '🎯',
            items: detail.sessionFormat,
            bg: 'bg-[#fff1eb]',
            text: 'text-clay',
        },
        {
            key: 'audience',
            title: 'Pentru cine',
            icon: '🤝',
            items: detail.audience,
            bg: 'bg-[#eef1fb]',
            text: 'text-[#3d4b9e]',
        },
    ].filter((group) => group.items.length > 0);
});

// Person modal
const activeCoach = ref<OrganizationPerson | null>(null);
const coachOpen = ref(false);

function openCoach(coach: Person) {
    const full = props.organization.people.find((c) => c.key === coach.key);
    activeCoach.value = full ?? { ...coach, sportIcon: '', sportLabel: '' };
    coachOpen.value = true;
}

const tabClass =
    'rounded-full px-4 py-2 text-[13.5px] font-semibold transition whitespace-nowrap';
const sectionTitle = 'mb-3.5 font-archivo text-[19px] font-extrabold';
</script>

<template>
    <Head :title="`${organization.name} — Unde Facem Sport`" />

    <div class="min-h-screen bg-paper pb-24 font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[820px] px-5">
            <!-- Profile header -->
            <div class="border-b border-line py-8">
                <div
                    class="flex flex-col items-center gap-1 text-center sm:flex-row sm:items-start sm:text-left"
                >
                    <div
                        class="flex h-[88px] w-[88px] shrink-0 items-center justify-center overflow-hidden rounded-full border-[3px] border-white text-4xl shadow-[0_0_0_2px_var(--color-line)]"
                        :style="
                            organization.people[0]?.photo
                                ? {}
                                : {
                                      background:
                                          organization.people[0]?.gradient ??
                                          gradientStyle('g6'),
                                  }
                        "
                    >
                        <img
                            v-if="organization.people[0]?.photo"
                            :src="organization.people[0].photo"
                            :alt="organization.representative"
                            class="h-full w-full object-cover"
                        />
                        <template v-else>🧑‍🏫</template>
                    </div>
                    <div>
                        <h1 class="font-archivo text-2xl font-extrabold">
                            {{ organization.name }}
                        </h1>
                        <div
                            v-if="organization.representative"
                            class="mt-1 text-[13.5px] font-semibold text-grass-deep"
                        >
                            cu {{ organization.representative }}
                        </div>
                        <p class="mt-2.5 max-w-[52ch] text-[14.5px] text-sage">
                            {{ organization.about }}
                        </p>
                        <div
                            class="mt-3.5 flex justify-center gap-2.5 sm:justify-start"
                        >
                            <a
                                v-for="soc in organization.socials"
                                :key="soc.label"
                                :href="soc.url"
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-[#eaf6ef] text-sm font-bold text-grass-deep"
                            >
                                {{ soc.label }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What it offers. One tab per kind, and only the kinds it has —
                 a tab opening on an empty panel is a promise the page cannot
                 keep. -->
            <div
                v-if="organization.tabs.length"
                class="flex gap-2 overflow-x-auto border-b border-line py-4"
            >
                <button
                    v-for="tab in organization.tabs"
                    :key="tab.key"
                    type="button"
                    :class="[
                        tabClass,
                        activeTab === tab.key
                            ? 'bg-grass-deep text-white'
                            : 'border border-line bg-white text-sage hover:border-grass',
                    ]"
                    :aria-pressed="activeTab === tab.key"
                    @click="selectTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <p
                v-if="!organization.tabs.length"
                class="py-8 text-[14.5px] text-sage"
            >
                Această organizație nu a publicat încă nimic.
            </p>

            <!-- Cursuri -->
            <section v-if="activeTab === 'cursuri'" class="py-6.5">
                <div class="flex flex-wrap gap-3.5 py-1 pb-5">
                    <button
                        v-for="item in organization.courses"
                        :key="item.key"
                        type="button"
                        class="w-[148px] overflow-hidden rounded-2xl border-[1.5px] bg-white transition hover:-translate-y-1"
                        :class="
                            activeCourse === item.key
                                ? 'border-grass shadow-[0_0_0_3px_rgba(21,184,119,0.18)]'
                                : 'border-line'
                        "
                        @click="activeCourse = item.key"
                    >
                        <div
                            class="flex h-[66px] items-center justify-center text-[28px] text-white"
                            :style="{ background: sportGradient(item.color) }"
                        >
                            {{ item.icon }}
                        </div>
                        <div class="px-2 pt-2.5 pb-3 text-center">
                            <div class="font-archivo text-sm font-extrabold">
                                {{ item.label }}
                            </div>
                            <div
                                class="mt-[3px] font-jetbrains text-[10px] font-semibold"
                                :class="
                                    activeCourse === item.key
                                        ? 'text-grass-deep'
                                        : 'text-sage'
                                "
                            >
                                {{ item.locations.length }}
                                {{
                                    item.locations.length === 1
                                        ? 'LOCAȚIE'
                                        : 'LOCAȚII'
                                }}
                            </div>
                        </div>
                    </button>
                </div>

                <div
                    v-if="course"
                    class="mb-6 rounded-[18px] border border-line bg-white p-5"
                >
                    <div
                        class="mb-3 flex items-center gap-2 font-archivo text-base font-extrabold"
                    >
                        <span v-if="course.icon">{{ course.icon }}</span>
                        {{ course.title }}
                    </div>
                    <div
                        v-if="course.trustChips.length"
                        class="mb-2.5 flex flex-wrap gap-1.5"
                    >
                        <span
                            v-for="chip in course.trustChips"
                            :key="chip"
                            class="inline-flex items-center gap-1 rounded-lg bg-[#eaf6ef] px-2.5 py-1.5 text-[11.5px] font-semibold text-grass-deep"
                        >
                            {{ chip }}
                        </span>
                    </div>

                    <div
                        v-if="highlightGroups.length"
                        class="mb-4 flex flex-col gap-2.5"
                    >
                        <div v-for="group in highlightGroups" :key="group.key">
                            <div
                                class="mb-1.5 font-jetbrains text-[10px] font-semibold tracking-[0.08em] text-sage uppercase"
                            >
                                {{ group.icon }} {{ group.title }}
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="item in group.items"
                                    :key="item"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11.5px] font-semibold"
                                    :class="[group.bg, group.text]"
                                >
                                    {{ item }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Who it is for, and how far along: two axes, because a
                         child and an adult can both be beginners. -->
                    <div class="mb-4 flex flex-wrap gap-1.5">
                        <span
                            v-for="age in course.ages"
                            :key="age"
                            class="rounded-lg border border-line bg-[#f2f5ef] px-3 py-1.5 text-[11.5px] font-semibold text-sage"
                        >
                            {{ age }}
                        </span>
                        <span
                            v-for="level in course.levels"
                            :key="level"
                            class="rounded-lg border border-grass/35 bg-[#eaf6ef] px-3 py-1.5 text-[11.5px] font-semibold text-grass-deep"
                        >
                            {{ level }}
                        </span>
                    </div>
                    <div
                        v-if="course.gallery.length"
                        class="flex gap-2.5 overflow-x-auto pb-0.5"
                    >
                        <img
                            v-for="(photo, i) in course.gallery"
                            :key="i"
                            :src="photo"
                            alt=""
                            class="h-[88px] w-[88px] shrink-0 rounded-[14px] object-cover"
                        />
                    </div>
                </div>

                <h2 :class="sectionTitle">
                    Unde și când
                    <span
                        class="font-inter text-[13.5px] font-medium text-sage"
                    >
                        — program de {{ (course?.label ?? '').toLowerCase() }}
                    </span>
                </h2>

                <div
                    v-for="loc in course?.locations ?? []"
                    :key="loc.slug"
                    class="mb-3.5 rounded-2xl border border-line bg-white p-4.5"
                >
                    <div class="mb-3 flex items-start justify-between gap-2.5">
                        <div>
                            <Link
                                :href="`/locatii/${loc.slug}`"
                                class="font-archivo text-[15.5px] font-extrabold hover:text-grass-deep"
                            >
                                {{ loc.name }}
                            </Link>
                            <div
                                class="mt-0.5 font-jetbrains text-[11px] text-sage"
                            >
                                📍 {{ loc.city }}
                            </div>
                        </div>
                        <Link
                            :href="`/locatii/${loc.slug}`"
                            class="text-xs font-semibold whitespace-nowrap text-grass-deep"
                        >
                            Vezi locația →
                        </Link>
                    </div>
                    <div
                        class="mb-2 font-jetbrains text-[10.5px] font-semibold tracking-[0.1em] text-sage uppercase"
                    >
                        Program aici
                    </div>
                    <WeekSchedule
                        :schedule="loc.schedule"
                        :people="organization.people"
                        @open-coach="openCoach"
                    />
                </div>
            </section>

            <!-- Agrement și închiriere: un tab pentru fiecare, pentru că prețul
                 și felul în care intri sunt complet diferite. -->
            <section
                v-for="way in organization.leisure"
                v-show="activeTab === way.key"
                :key="way.key"
                class="py-6.5"
            >
                <div class="mb-6">
                    <h2 class="mb-1 font-archivo text-[19px] font-extrabold">
                        {{ way.verb }}
                    </h2>
                    <p class="mb-3.5 text-[13px] text-sage">{{ way.how }}</p>

                    <div
                        v-for="space in way.spaces"
                        :key="`${way.key}-${space.id}`"
                        class="mb-3.5 rounded-2xl border border-line bg-white p-4.5"
                    >
                        <div
                            class="mb-2 flex items-start justify-between gap-2.5"
                        >
                            <div>
                                <div
                                    class="font-archivo text-[15.5px] font-extrabold"
                                >
                                    {{ space.name }}
                                </div>
                                <div
                                    v-if="space.location"
                                    class="mt-0.5 font-jetbrains text-[11px] text-sage"
                                >
                                    📍
                                    <Link
                                        v-if="space.locationSlug"
                                        :href="`/locatii/${space.locationSlug}`"
                                        class="hover:text-grass-deep"
                                    >
                                        {{ space.location }}
                                    </Link>
                                    <template v-else>{{
                                        space.location
                                    }}</template>
                                </div>
                            </div>
                            <div class="text-right">
                                <div
                                    class="font-jetbrains text-[13px] font-semibold"
                                >
                                    {{ space.price ?? 'Preț nespecificat' }}
                                </div>
                                <div
                                    v-if="space.openNow"
                                    class="mt-0.5 text-[11px] font-semibold text-grass-deep"
                                >
                                    deschis acum{{
                                        space.closesAt
                                            ? ` · până la ${space.closesAt}`
                                            : ''
                                    }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="mb-2.5 flex flex-wrap gap-1.5 text-[11.5px] font-semibold text-sage"
                        >
                            <span
                                v-if="space.sport"
                                class="rounded-lg bg-[#eaf6ef] px-2.5 py-1 text-grass-deep"
                            >
                                {{ space.sport }}
                            </span>
                            <span
                                v-if="space.capacity"
                                class="rounded-lg border border-line px-2.5 py-1"
                            >
                                {{ space.capacity }} locuri
                            </span>
                            <span
                                v-if="space.surface"
                                class="rounded-lg border border-line px-2.5 py-1"
                            >
                                {{ space.surface }}
                            </span>
                            <span
                                v-if="space.isIndoor !== null"
                                class="rounded-lg border border-line px-2.5 py-1"
                            >
                                {{ space.isIndoor ? 'interior' : 'exterior' }}
                            </span>
                            <span
                                v-if="space.hasFloodlights"
                                class="rounded-lg border border-line px-2.5 py-1"
                            >
                                nocturnă
                            </span>
                        </div>

                        <p
                            v-if="space.priceNotes"
                            class="mb-2.5 text-[13px] text-sage"
                        >
                            {{ space.priceNotes }}
                        </p>

                        <div
                            class="grid grid-cols-2 gap-x-4 gap-y-1 text-[12.5px] sm:grid-cols-4"
                        >
                            <div
                                v-for="day in space.week"
                                :key="day.day"
                                class="flex justify-between gap-2"
                            >
                                <span class="text-sage">{{ day.day }}</span>
                                <span>{{ day.hours }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Servicii -->
            <section v-if="activeTab === 'servicii'" class="py-6.5">
                <div class="grid gap-2.5 sm:grid-cols-2">
                    <div
                        v-for="service in organization.services"
                        :key="service.key"
                        class="flex items-start gap-3 rounded-2xl border-[1.5px] border-line bg-white px-4 py-3.5"
                    >
                        <span class="text-[22px] leading-none">{{
                            service.icon
                        }}</span>
                        <div class="min-w-0">
                            <div
                                class="font-archivo text-[15px] font-extrabold"
                            >
                                {{ service.name }}
                            </div>
                            <div
                                v-if="service.specialty"
                                class="mt-0.5 text-[12px] font-semibold text-grass-deep"
                            >
                                {{ service.specialty }}
                            </div>
                            <div
                                v-if="service.price"
                                class="mt-0.5 font-jetbrains text-[13px] font-semibold"
                            >
                                {{ service.price }}
                                <span
                                    v-if="service.duration"
                                    class="font-normal text-sage"
                                >
                                    · {{ service.duration }}
                                </span>
                            </div>
                            <div v-else class="mt-0.5 text-[13px] text-sage">
                                Preț nespecificat
                            </div>
                            <p
                                v-if="service.description"
                                class="mt-1 text-[13px] text-sage"
                            >
                                {{ service.description }}
                            </p>
                            <div
                                v-if="service.person"
                                class="mt-1 text-[12.5px] text-sage"
                            >
                                cu {{ service.person }}
                            </div>
                            <!-- Which athletes this is for, as the practitioner
                                 ticked it. -->
                            <div
                                v-if="service.sports.length"
                                class="mt-2 flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="sport in service.sports"
                                    :key="sport.key"
                                    class="rounded-[7px] border border-line bg-[#f2f5ef] px-2 py-0.5 text-[10.5px] font-bold text-sage"
                                >
                                    {{ sport.icon }} {{ sport.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- People and addresses belong to the organization, not to one of
                 its offers, so they sit below the tabs rather than inside one. -->
            <section v-if="organization.people.length" class="py-6.5">
                <h2 :class="sectionTitle">Echipa</h2>
                <div
                    class="grid grid-cols-1 gap-3.5 min-[900px]:grid-cols-3 sm:grid-cols-2"
                >
                    <div
                        v-for="coach in organization.people"
                        :key="coach.key"
                        class="rounded-2xl border border-line bg-white p-4.5 text-center transition hover:-translate-y-[3px] hover:shadow-[0_20px_36px_-22px_rgba(11,20,16,0.35)]"
                    >
                        <div
                            class="mx-auto mb-3 flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border-[2.5px] border-white text-[26px] shadow-[0_0_0_2px_var(--color-line)]"
                            :style="
                                coach.photo
                                    ? {}
                                    : { background: coach.gradient }
                            "
                        >
                            <img
                                v-if="coach.photo"
                                :src="coach.photo"
                                :alt="coach.name"
                                class="h-full w-full object-cover"
                            />
                            <template v-else>🧑‍🏫</template>
                        </div>
                        <button
                            type="button"
                            class="font-archivo text-[15px] font-extrabold hover:text-grass-deep"
                            @click="openCoach(coach)"
                        >
                            {{ coach.name }}
                            <span
                                v-if="coach.solo"
                                class="ml-1 rounded-[5px] bg-[#fff1eb] px-1.5 py-0.5 align-middle text-[9.5px] font-bold text-clay"
                            >
                                1:1
                            </span>
                        </button>
                        <div
                            class="mt-0.5 text-xs font-semibold text-grass-deep"
                        >
                            {{ coach.role }}
                        </div>
                        <button
                            v-if="coach.sportLabel"
                            type="button"
                            class="mt-2 inline-flex items-center gap-1 rounded-[7px] border border-line bg-[#f2f5ef] px-2.5 py-1 text-[10.5px] font-bold text-sage transition hover:border-grass hover:bg-grass hover:text-white"
                            @click="openCoach(coach)"
                        >
                            {{ coach.sportIcon }} {{ coach.sportLabel }}
                        </button>
                        <div class="mt-2.5 text-xs leading-snug text-sage">
                            {{ coach.bio }}
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="organization.locations.length" class="py-6.5">
                <h2 :class="sectionTitle">Adrese</h2>
                <div class="grid gap-2.5 sm:grid-cols-2">
                    <Link
                        v-for="loc in organization.locations"
                        :key="loc.slug"
                        :href="`/locatii/${loc.slug}`"
                        class="rounded-2xl border border-line bg-white px-4 py-3.5 transition hover:border-grass"
                    >
                        <div class="font-archivo text-[15px] font-extrabold">
                            {{ loc.name }}
                        </div>
                        <div class="mt-0.5 text-[13px] text-sage">
                            {{ loc.address }}
                        </div>
                    </Link>
                </div>
            </section>
        </div>

        <!-- Fixed contact bar -->
        <div
            class="fixed inset-x-0 bottom-0 z-[70] flex justify-center gap-2.5 border-t border-line bg-white px-5 py-3"
        >
            <a
                :href="organization.phone ? `tel:${organization.phone}` : '#'"
                class="inline-flex max-w-[220px] flex-1 items-center justify-center gap-2 rounded-full bg-clay px-5 py-3 text-[14.5px] font-semibold text-white"
            >
                Sună acum
            </a>
            <a
                :href="
                    organization.phone
                        ? `https://wa.me/${organization.phone.replace(/\D/g, '')}`
                        : '#'
                "
                target="_blank"
                rel="noopener"
                class="inline-flex max-w-[220px] flex-1 items-center justify-center gap-2 rounded-full bg-[#25D366] px-5 py-3 text-[14.5px] font-semibold text-white"
            >
                WhatsApp
            </a>
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
                <div
                    v-if="activeCoach?.sportLabel"
                    class="mx-auto mt-2.5 inline-flex items-center gap-1 rounded-[7px] border border-line bg-[#f2f5ef] px-2.5 py-1 text-[10.5px] font-bold text-sage"
                >
                    {{ activeCoach.sportIcon }} {{ activeCoach.sportLabel }}
                </div>
                <p class="mt-3.5 text-[13px] leading-relaxed text-sage">
                    {{ activeCoach?.bio }}
                </p>
            </DialogContent>
        </Dialog>
    </div>
</template>
