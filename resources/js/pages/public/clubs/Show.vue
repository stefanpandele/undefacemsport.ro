<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PublicTopBar from '@/components/sports/PublicTopBar.vue';
import WeekSchedule from '@/components/sports/WeekSchedule.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { gradientStyle } from '@/lib/gradients';
import type { Coach, ScheduleDay, TrustChip } from '@/types/sports';

type ClubCoach = Coach & { sportIcon: string; sportLabel: string };

type SportDetail = {
    title: string;
    trustChips: TrustChip[];
    ages: string[];
    gallery: string[];
};

type ClubLocation = {
    slug: string;
    name: string;
    city: string;
    schedule: ScheduleDay[];
};

type ClubProfile = {
    slug: string;
    name: string;
    representative: string;
    about: string;
    socials: { label: string; url: string }[];
    sports: { key: string; label: string; icon: string; locationCount: number }[];
    sportDetails: Record<string, SportDetail>;
    coaches: ClubCoach[];
    locationsBySport: Record<string, ClubLocation[]>;
};

const props = defineProps<{ club: ClubProfile }>();

const activeSport = ref(props.club.sports[0]?.key ?? '');

const activeDetail = computed(() => props.club.sportDetails[activeSport.value]);
const activeLocations = computed(
    () => props.club.locationsBySport[activeSport.value] ?? [],
);
const activeSportLabel = computed(
    () => props.club.sports.find((s) => s.key === activeSport.value)?.label ?? '',
);

// Coach modal
const activeCoach = ref<ClubCoach | null>(null);
const coachOpen = ref(false);

function openCoach(coach: Coach) {
    const full = props.club.coaches.find((c) => c.key === coach.key);
    activeCoach.value = full ?? { ...coach, sportIcon: '', sportLabel: '' };
    coachOpen.value = true;
}

function sportHeadGradient(key: string): string {
    return gradientStyle(key === 'inot' ? 'g1' : key === 'polo' ? 'g6' : 'g4');
}
</script>

<template>
    <Head :title="`${club.name} — Unde Facem Sport`" />

    <div class="min-h-screen bg-paper pb-24 font-inter text-ink antialiased">
        <PublicTopBar />

        <div class="mx-auto max-w-[820px] px-5">
            <!-- Profile header -->
            <div class="border-b border-line py-8">
                <div
                    class="flex flex-col items-center gap-1 text-center sm:flex-row sm:items-start sm:text-left"
                >
                    <div
                        class="flex h-[88px] w-[88px] shrink-0 items-center justify-center rounded-full border-[3px] border-white text-4xl shadow-[0_0_0_2px_var(--color-line)]"
                        :style="{ background: club.coaches[0]?.gradient ?? gradientStyle('g6') }"
                    >
                        🧑‍🏫
                    </div>
                    <div>
                        <h1 class="font-archivo text-2xl font-extrabold">{{ club.name }}</h1>
                        <div class="mt-1 text-[13.5px] font-semibold text-grass-deep">
                            cu {{ club.representative }}
                        </div>
                        <p class="mt-2.5 max-w-[52ch] text-[14.5px] text-sage">
                            {{ club.about }}
                        </p>
                        <div class="mt-3.5 flex justify-center gap-2.5 sm:justify-start">
                            <a
                                v-for="soc in club.socials"
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

            <!-- Sports -->
            <section class="py-6.5">
                <h2 class="mb-3.5 font-archivo text-[19px] font-extrabold">Sporturi</h2>
                <div class="flex flex-wrap gap-3.5 py-1 pb-5">
                    <button
                        v-for="sport in club.sports"
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
                            :style="{ background: sportHeadGradient(sport.key) }"
                        >
                            {{ sport.icon }}
                        </div>
                        <div class="px-2 pt-2.5 pb-3 text-center">
                            <div class="font-archivo text-sm font-extrabold">{{ sport.label }}</div>
                            <div
                                class="mt-[3px] font-jetbrains text-[10px] font-semibold"
                                :class="activeSport === sport.key ? 'text-grass-deep' : 'text-sage'"
                            >
                                {{ sport.locationCount }}
                                {{ sport.locationCount === 1 ? 'LOCAȚIE' : 'LOCAȚII' }}
                            </div>
                        </div>
                    </button>
                </div>

                <!-- Sport detail -->
                <div
                    v-if="activeDetail"
                    class="mb-2 rounded-[18px] border border-line bg-white p-5"
                >
                    <div class="mb-3 flex items-center gap-2 font-archivo text-base font-extrabold">
                        {{ activeDetail.title }}
                    </div>
                    <div class="mb-2.5 flex flex-wrap gap-1.5">
                        <span
                            v-for="chip in activeDetail.trustChips"
                            :key="chip.label"
                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11.5px] font-semibold"
                            :class="chip.solo ? 'bg-[#fff1eb] text-clay' : 'bg-[#eaf6ef] text-grass-deep'"
                        >
                            {{ chip.label }}
                        </span>
                    </div>
                    <div class="mb-4 flex flex-wrap gap-1.5">
                        <span
                            v-for="age in activeDetail.ages"
                            :key="age"
                            class="rounded-lg border border-line bg-[#f2f5ef] px-3 py-1.5 text-[11.5px] font-semibold text-sage"
                        >
                            {{ age }}
                        </span>
                    </div>
                    <div class="flex gap-2.5 overflow-x-auto pb-0.5">
                        <div
                            v-for="g in activeDetail.gallery"
                            :key="g"
                            class="h-[88px] w-[88px] shrink-0 rounded-[14px]"
                            :style="{ background: gradientStyle(g) }"
                        />
                    </div>
                </div>
            </section>

            <!-- Coaches -->
            <section class="py-6.5">
                <h2 class="mb-3.5 font-archivo text-[19px] font-extrabold">Antrenori</h2>
                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 min-[900px]:grid-cols-3">
                    <div
                        v-for="coach in club.coaches"
                        :key="coach.key"
                        class="rounded-2xl border border-line bg-white p-4.5 text-center transition hover:-translate-y-[3px] hover:shadow-[0_20px_36px_-22px_rgba(11,20,16,0.35)]"
                    >
                        <div
                            class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full border-[2.5px] border-white text-[26px] shadow-[0_0_0_2px_var(--color-line)]"
                            :style="{ background: coach.gradient }"
                        >
                            🧑‍🏫
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
                        <div class="mt-0.5 text-xs font-semibold text-grass-deep">
                            {{ coach.role }}
                        </div>
                        <button
                            type="button"
                            class="mt-2 inline-flex items-center gap-1 rounded-[7px] border border-line bg-[#f2f5ef] px-2.5 py-1 text-[10.5px] font-bold text-sage transition hover:border-grass hover:bg-grass hover:text-white"
                            @click="openCoach(coach)"
                        >
                            {{ coach.sportIcon }} {{ coach.sportLabel }}
                        </button>
                        <div class="mt-2.5 text-xs leading-snug text-sage">{{ coach.bio }}</div>
                    </div>
                </div>
            </section>

            <!-- Locations -->
            <section class="py-6.5">
                <h2 class="mb-3.5 font-archivo text-[19px] font-extrabold">
                    Locații
                    <span class="font-inter text-[13.5px] font-medium text-sage">
                        — program de {{ activeSportLabel.toLowerCase() }}
                    </span>
                </h2>

                <div
                    v-for="loc in activeLocations"
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
                            <div class="mt-0.5 font-jetbrains text-[11px] text-sage">
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
                        :coaches="club.coaches"
                        @open-coach="openCoach"
                    />
                </div>
            </section>
        </div>

        <!-- Fixed contact bar -->
        <div
            class="fixed inset-x-0 bottom-0 z-[70] flex justify-center gap-2.5 border-t border-line bg-white px-5 py-3"
        >
            <a
                href="#"
                class="inline-flex max-w-[220px] flex-1 items-center justify-center gap-2 rounded-full bg-clay px-5 py-3 text-[14.5px] font-semibold text-white"
            >
                Sună acum
            </a>
            <a
                href="#"
                class="inline-flex max-w-[220px] flex-1 items-center justify-center gap-2 rounded-full bg-[#25D366] px-5 py-3 text-[14.5px] font-semibold text-white"
            >
                WhatsApp
            </a>
        </div>

        <!-- Coach modal -->
        <Dialog v-model:open="coachOpen">
            <DialogContent class="max-w-[320px] text-center">
                <DialogHeader>
                    <div
                        class="mx-auto mb-4 flex h-[140px] w-[140px] items-center justify-center rounded-full border-4 border-white text-[56px] shadow-[0_0_0_2px_var(--color-line)]"
                        :style="{ background: activeCoach?.gradient }"
                    >
                        🧑‍🏫
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
