<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import WeekSchedule from '@/components/sports/WeekSchedule.vue';
import { gradientStyle } from '@/lib/gradients';
import type { Coach, LocationClub } from '@/types/sports';

defineProps<{ club: LocationClub }>();

defineEmits<{ openCoach: [coach: Coach] }>();

const miniGalleryKeys = ['g1', 'g2', 'g3'];
</script>

<template>
    <div class="mb-4 rounded-[18px] border border-line bg-white p-5">
        <Link
            :href="`/cluburi/${club.slug}`"
            class="group mb-3.5 flex cursor-pointer gap-3.5"
        >
            <div
                class="flex h-[60px] w-[60px] shrink-0 items-center justify-center rounded-full border-[2.5px] border-white text-2xl shadow-[0_0_0_2px_var(--color-line)]"
                :style="{ background: club.coaches[0]?.gradient ?? gradientStyle('g2') }"
            >
                🧑‍🏫
            </div>
            <div>
                <div class="font-archivo text-[17px] font-extrabold group-hover:text-grass-deep">
                    {{ club.name }}
                </div>
                <div class="mt-0.5 text-[12.5px] font-semibold text-grass-deep">
                    cu {{ club.representative }}
                </div>
                <div class="mt-1.5 text-[13.5px] text-sage">{{ club.about }}</div>
            </div>
        </Link>

        <!-- Media: photos + coaches -->
        <div class="mb-3.5 flex flex-wrap gap-4">
            <div class="min-w-[190px] flex-1">
                <div
                    class="mb-[7px] font-jetbrains text-[9.5px] font-semibold tracking-[0.08em] text-sage uppercase"
                >
                    Poze club
                </div>
                <div class="flex gap-[7px]">
                    <div
                        v-for="g in miniGalleryKeys"
                        :key="g"
                        class="h-[58px] w-[58px] shrink-0 rounded-[10px]"
                        :style="{ background: gradientStyle(g) }"
                    />
                    <div
                        v-if="club.photos > 3"
                        class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[10px] bg-[#f2f5ef] font-jetbrains text-[11px] font-bold text-sage"
                    >
                        +{{ club.photos - 3 }}
                    </div>
                </div>
            </div>
            <div class="basis-[170px]">
                <div
                    class="mb-[7px] font-jetbrains text-[9.5px] font-semibold tracking-[0.08em] text-sage uppercase"
                >
                    Antrenori
                </div>
                <button
                    v-for="coach in club.coaches"
                    :key="coach.key"
                    type="button"
                    class="group flex w-full items-center gap-2 py-[5px] text-left transition hover:translate-x-0.5"
                    @click="$emit('openCoach', coach)"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm transition group-hover:shadow-[0_0_0_2px_var(--color-grass)]"
                        :style="{ background: coach.gradient }"
                    >
                        🧑‍🏫
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-semibold group-hover:text-grass-deep">
                            {{ coach.name }}
                            <span
                                v-if="coach.solo"
                                class="rounded-[5px] bg-[#fff1eb] px-1.5 py-0.5 text-[9px] font-bold text-clay"
                            >
                                1:1
                            </span>
                        </div>
                        <div class="text-[10px] text-sage">{{ coach.role }}</div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Trust chips -->
        <div class="mb-2.5 flex flex-wrap gap-1.5">
            <span
                v-for="chip in club.trustChips"
                :key="chip.label"
                class="inline-flex items-center gap-1 rounded-[7px] px-2.5 py-[5px] text-[11px] font-semibold"
                :class="chip.solo ? 'bg-[#fff1eb] text-clay' : 'bg-[#eaf6ef] text-grass-deep'"
            >
                {{ chip.label }}
            </span>
        </div>

        <!-- Age chips -->
        <div class="mb-4 flex flex-wrap gap-1.5">
            <span
                v-for="age in club.ages"
                :key="age"
                class="rounded-[7px] border border-line bg-[#f2f5ef] px-2.5 py-[5px] text-[11.5px] font-semibold text-sage"
            >
                {{ age }}
            </span>
        </div>

        <!-- Weekly schedule -->
        <div
            class="mb-2 font-jetbrains text-[10.5px] font-semibold tracking-[0.1em] text-sage uppercase"
        >
            Program săptămânal
        </div>
        <div class="mb-4">
            <WeekSchedule
                :schedule="club.schedule"
                :coaches="club.coaches"
                @open-coach="$emit('openCoach', $event)"
            />
        </div>

        <!-- Contact -->
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-t border-dashed border-line pt-3.5"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-full text-sm"
                    :style="{ background: club.coaches[0]?.gradient ?? gradientStyle('g2') }"
                >
                    🧑‍🏫
                </div>
                <span class="text-[13.5px] text-sage">
                    Contact: <b class="text-ink">{{ club.contactName }}</b> ·
                    {{ club.contactPhone }}
                </span>
            </div>
            <div class="flex gap-2">
                <a
                    href="#"
                    class="inline-flex items-center gap-2 rounded-full bg-[#25D366] px-4 py-2.5 text-[13.5px] font-semibold text-white"
                >
                    WhatsApp
                </a>
                <a
                    href="#"
                    class="inline-flex items-center gap-2 rounded-full border-[1.5px] border-line bg-white px-4 py-2.5 text-[13.5px] font-semibold"
                >
                    Sună
                </a>
            </div>
        </div>
    </div>
</template>
