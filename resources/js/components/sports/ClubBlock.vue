<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import WeekSchedule from '@/components/sports/WeekSchedule.vue';
import { gradientStyle } from '@/lib/gradients';
import clubs from '@/routes/clubs';
import type { Coach, LocationClub, ScheduleSlot } from '@/types/sports';

const props = defineProps<{ club: LocationClub }>();

defineEmits<{
    openCoach: [coach: Coach];
    openHall: [slot: ScheduleSlot];
}>();

const MINI_GALLERY_SIZE = 3;

const miniGallery = computed(() =>
    props.club.photos.slice(0, MINI_GALLERY_SIZE),
);
const remainingPhotos = computed(() =>
    Math.max(props.club.photos.length - MINI_GALLERY_SIZE, 0),
);
</script>

<template>
    <!-- The id is the anchor the hall-occupancy modal links to; scroll-mt keeps
         the block clear of the sticky top bar when jumped to. -->
    <div
        :id="`club-${club.key}`"
        class="mb-4 scroll-mt-24 rounded-[18px] border border-line bg-white p-5"
    >
        <Link
            :href="clubs.show.url(club.slug)"
            class="group mb-3.5 flex cursor-pointer gap-3.5"
        >
            <div
                class="flex h-[60px] w-[60px] shrink-0 items-center justify-center overflow-hidden rounded-full border-[2.5px] border-white text-2xl shadow-[0_0_0_2px_var(--color-line)]"
                :style="
                    club.coaches[0]?.photo
                        ? {}
                        : {
                              background:
                                  club.coaches[0]?.gradient ??
                                  gradientStyle('g2'),
                          }
                "
            >
                <img
                    v-if="club.coaches[0]?.photo"
                    :src="club.coaches[0].photo"
                    :alt="club.coaches[0].name"
                    class="h-full w-full object-cover"
                />
                <template v-else>🧑‍🏫</template>
            </div>
            <div>
                <div
                    class="font-archivo text-[17px] font-extrabold group-hover:text-grass-deep"
                >
                    {{ club.name }}
                </div>
                <div class="mt-0.5 text-[12.5px] font-semibold text-grass-deep">
                    cu {{ club.representative }}
                </div>
                <div class="mt-1.5 text-[13.5px] text-sage">
                    {{ club.about }}
                </div>
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
                    <img
                        v-for="(photo, i) in miniGallery"
                        :key="i"
                        :src="photo"
                        alt=""
                        class="h-[58px] w-[58px] shrink-0 rounded-[10px] object-cover"
                    />
                    <div
                        v-if="remainingPhotos"
                        class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[10px] bg-[#f2f5ef] font-jetbrains text-[11px] font-bold text-sage"
                    >
                        +{{ remainingPhotos }}
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
                        class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm transition group-hover:shadow-[0_0_0_2px_var(--color-grass)]"
                        :style="
                            coach.photo ? {} : { background: coach.gradient }
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
                    <div>
                        <div
                            class="flex items-center gap-1.5 text-xs font-semibold group-hover:text-grass-deep"
                        >
                            {{ coach.name }}
                            <span
                                v-if="coach.solo"
                                class="rounded-[5px] bg-[#fff1eb] px-1.5 py-0.5 text-[9px] font-bold text-clay"
                            >
                                1:1
                            </span>
                        </div>
                        <div class="text-[10px] text-sage">
                            {{ coach.role }}
                        </div>
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
                :class="
                    chip.solo
                        ? 'bg-[#fff1eb] text-clay'
                        : 'bg-[#eaf6ef] text-grass-deep'
                "
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
                @open-hall="$emit('openHall', $event)"
            />
        </div>

        <!-- Contact -->
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-t border-dashed border-line pt-3.5"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-[30px] w-[30px] shrink-0 items-center justify-center overflow-hidden rounded-full text-sm"
                    :style="
                        club.coaches[0]?.photo
                            ? {}
                            : {
                                  background:
                                      club.coaches[0]?.gradient ??
                                      gradientStyle('g2'),
                              }
                    "
                >
                    <img
                        v-if="club.coaches[0]?.photo"
                        :src="club.coaches[0].photo"
                        :alt="club.contactName"
                        class="h-full w-full object-cover"
                    />
                    <template v-else>🧑‍🏫</template>
                </div>
                <span class="text-[13.5px] text-sage">
                    Contact: <b class="text-ink">{{ club.contactName }}</b>
                    <template v-if="club.contactPhone">
                        · {{ club.contactPhone }}</template
                    >
                </span>
            </div>
            <div v-if="club.contactPhone" class="flex gap-2">
                <a
                    :href="`https://wa.me/${club.contactPhone.replace(/\D/g, '')}`"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 rounded-full bg-[#25D366] px-4 py-2.5 text-[13.5px] font-semibold text-white"
                >
                    WhatsApp
                </a>
                <a
                    :href="`tel:${club.contactPhone}`"
                    class="inline-flex items-center gap-2 rounded-full border-[1.5px] border-line bg-white px-4 py-2.5 text-[13.5px] font-semibold"
                >
                    Sună
                </a>
            </div>
        </div>
    </div>
</template>
