<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ClubBlock from '@/components/sports/ClubBlock.vue';
import PublicTopBar from '@/components/sports/PublicTopBar.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { gradientStyle } from '@/lib/gradients';
import type { Coach, LocationDetail } from '@/types/sports';

import clubApplication from '@/routes/club-application';

const props = defineProps<{ location: LocationDetail }>();

// Hero carousel
const activeSlide = ref(0);

// Sport filter
const activeSport = ref<string | null>(null);

const filteredClubs = computed(() =>
    activeSport.value
        ? props.location.clubs.filter((c) => c.sport === activeSport.value)
        : [],
);

// Coach modal
const activeCoach = ref<Coach | null>(null);
const coachOpen = ref(false);

function openCoach(coach: Coach) {
    activeCoach.value = coach;
    coachOpen.value = true;
}
</script>

<template>
    <Head :title="`${location.name} — Unde Facem Sport`" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <PublicTopBar />

        <div class="mx-auto max-w-[1180px] px-5">
            <!-- Hero + map -->
            <div class="grid grid-cols-1 gap-3.5 pt-4 min-[900px]:grid-cols-[2fr_1fr]">
                <div class="relative h-[220px] overflow-hidden rounded-[20px] min-[900px]:h-[320px]">
                    <div
                        v-for="(g, i) in location.gallery"
                        :key="g"
                        class="absolute inset-0 transition-opacity duration-500"
                        :style="{ background: gradientStyle(g), opacity: activeSlide === i ? 1 : 0 }"
                    />
                    <div
                        class="absolute inset-0 z-[2]"
                        style="background: linear-gradient(to top, rgba(4,8,6,0.88) 0%, rgba(4,8,6,0.35) 45%, transparent 75%)"
                    />
                    <div class="relative z-[3] flex h-full flex-col justify-end p-5 text-white">
                        <h1 class="font-archivo text-[clamp(22px,3.4vw,32px)] font-extrabold">
                            {{ location.name }}
                        </h1>
                        <div class="mt-1.5 font-jetbrains text-[12.5px] opacity-95">
                            📍 {{ location.address.toUpperCase() }}
                        </div>
                    </div>
                    <div class="absolute right-5 bottom-4 z-[4] flex gap-[7px]">
                        <button
                            v-for="(g, i) in location.gallery"
                            :key="g"
                            type="button"
                            class="h-[7px] rounded-full transition-all"
                            :class="activeSlide === i ? 'w-5 bg-white' : 'w-[7px] bg-white/50'"
                            @click="activeSlide = i"
                        />
                    </div>
                </div>
                <div
                    class="relative h-[180px] overflow-hidden rounded-[20px] min-[900px]:h-[320px]"
                    style="background: linear-gradient(#eef2ea,#eef2ea), repeating-linear-gradient(0deg, transparent 0 30px, #dfe6da 30px 31px), repeating-linear-gradient(90deg, transparent 0 30px, #dfe6da 30px 31px)"
                >
                    <div
                        class="absolute top-[44%] left-[44%] h-[26px] w-[26px] rotate-[-45deg] rounded-[50%_50%_50%_0] bg-clay"
                    />
                    <div
                        class="absolute bottom-3 left-3 rounded-[9px] bg-ink px-2.5 py-[7px] font-jetbrains text-[11px] font-bold whitespace-nowrap text-white"
                    >
                        📍 {{ location.distance }}
                    </div>
                    <div
                        class="absolute right-3 bottom-3 rounded-[10px] border border-line bg-white px-2.5 py-[7px] text-xs font-semibold"
                    >
                        📍 Locația mea
                    </div>
                </div>
            </div>

            <!-- Facilities + CTA -->
            <section class="py-6.5">
                <div class="grid grid-cols-1 gap-3.5 min-[900px]:grid-cols-[2fr_1fr]">
                    <div
                        class="relative overflow-hidden rounded-[18px] border-[1.5px] border-dashed border-line bg-[#fafaf7] px-5 py-5.5"
                    >
                        <div class="relative mb-4 flex items-center gap-2.5">
                            <h2 class="font-archivo text-[19px] font-extrabold">Facilități</h2>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#eaf6ef] px-2.5 py-1.5 font-jetbrains text-[11.5px] font-bold text-grass-deep"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-grass" />
                                {{ location.facilities.length }} TOTAL
                            </span>
                        </div>
                        <div class="relative grid grid-cols-2 gap-x-2.5 gap-y-4 sm:grid-cols-4">
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
                                <div class="text-[11.5px] font-semibold leading-tight">
                                    {{ fac.label }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex flex-col justify-center rounded-[18px] border-[1.5px] border-dashed border-line bg-[#fafaf7] p-5.5 text-center"
                    >
                        <div class="mb-2.5 text-[26px]">🙋</div>
                        <h3 class="mb-1.5 font-archivo text-[17px] font-extrabold">Ții lecții aici?</h3>
                        <p class="mb-4 text-[13.5px] text-sage">
                            Adaugă-ți clubul și programul, ca lumea să te găsească.
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
                            :style="{ background: gradientStyle(sport.key === 'inot' ? 'g1' : sport.key === 'polo' ? 'g2' : 'g4') }"
                        >
                            {{ sport.icon }}
                        </div>
                        <div class="px-2 pt-2.5 pb-3 text-center">
                            <div class="font-archivo text-sm font-extrabold">{{ sport.label }}</div>
                            <div
                                class="mt-[3px] font-jetbrains text-[10px] font-semibold"
                                :class="activeSport === sport.key ? 'text-grass-deep' : 'text-sage'"
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
                    <div class="mb-2.5 text-[26px]">👆</div>
                    <p class="mx-auto max-w-[36ch] text-sm">
                        Alege un sport de mai sus ca să vezi cluburile și orarul disponibil
                        pentru el, aici.
                    </p>
                </div>
            </section>

            <!-- Clubs -->
            <div v-if="activeSport" class="pb-15">
                <h2 class="mb-3.5 flex items-center gap-2.5 font-archivo text-[19px] font-extrabold">
                    Cluburi
                    <span
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#eaf6ef] px-2.5 py-1.5 font-jetbrains text-[11.5px] font-bold text-grass-deep"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-grass" />
                        {{ filteredClubs.length }}
                        {{ filteredClubs.length === 1 ? 'CLUB' : 'CLUBURI' }}
                    </span>
                </h2>

                <template v-if="filteredClubs.length">
                    <ClubBlock
                        v-for="club in filteredClubs"
                        :key="club.slug"
                        :club="club"
                        @open-coach="openCoach"
                    />
                </template>
                <div v-else class="py-8 text-center text-sage">
                    Niciun club nu ține încă lecții de acest sport aici.
                    <Link :href="clubApplication.create.url()" class="font-semibold text-grass-deep">
                        Fii primul care se listează →
                    </Link>
                </div>
            </div>
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
                <p class="mt-3.5 text-[13px] leading-relaxed text-sage">
                    {{ activeCoach?.bio }}
                </p>
            </DialogContent>
        </Dialog>
    </div>
</template>
