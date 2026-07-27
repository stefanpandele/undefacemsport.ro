<script setup lang="ts">
import type { Coach, ScheduleDay, ScheduleSlot } from '@/types/sports';

const props = defineProps<{
    schedule: ScheduleDay[];
    coaches: Coach[];
}>();

defineEmits<{
    openCoach: [coach: Coach];
    openHall: [slot: ScheduleSlot];
}>();

function coachByKey(key: string): Coach | undefined {
    return props.coaches.find((c) => c.key === key);
}

function shortName(coach: Coach): string {
    const [first, last] = coach.name.split(' ');

    return `${first} ${last?.[0] ?? ''}.`;
}

function clubLabel(slot: ScheduleSlot): string {
    return slot.otherClubs.length === 1
        ? '1 club'
        : `${slot.otherClubs.length} cluburi`;
}
</script>

<template>
    <div class="rounded-xl bg-panel px-3.5 py-1.5">
        <div
            v-for="row in schedule"
            :key="row.day"
            class="grid grid-cols-[52px_1fr] items-center gap-3 border-b border-white/[0.08] py-2.5 last:border-b-0"
        >
            <span
                class="font-jetbrains text-xs font-bold"
                :class="row.slots.length ? 'text-grass' : 'text-[#7f9488]'"
            >
                {{ row.day }}
            </span>
            <div class="flex flex-wrap gap-2">
                <span
                    v-if="!row.slots.length"
                    class="font-jetbrains text-[11.5px] text-[#4b5a51]"
                >
                    —
                </span>
                <div
                    v-for="(slot, i) in row.slots"
                    :key="i"
                    class="rounded-lg border px-2.5 py-[5px] leading-tight"
                    :class="
                        slot.foreign
                            ? 'border-[#d4573f]/40 bg-[#d4573f]/10'
                            : 'border-grass/35 bg-grass/10'
                    "
                >
                    <div
                        class="font-jetbrains text-[11.5px] font-bold"
                        :class="slot.foreign ? 'text-[#e08268]' : 'text-grass'"
                    >
                        {{ slot.time }}
                    </div>

                    <!-- This club's own session: age group and coach. -->
                    <template v-if="!slot.foreign">
                        <div class="mt-px text-[9.5px] text-[#9fb3a6]">
                            {{ slot.group }}
                        </div>
                        <button
                            v-if="coachByKey(slot.coach)"
                            type="button"
                            class="mt-[5px] flex items-center gap-1.5 border-t border-white/10 pt-[5px]"
                            @click="$emit('openCoach', coachByKey(slot.coach)!)"
                        >
                            <span
                                class="flex h-4 w-4 items-center justify-center rounded-full text-[8px]"
                                :style="{
                                    background: coachByKey(slot.coach)!
                                        .gradient,
                                }"
                            >
                                🧑‍🏫
                            </span>
                            <span
                                class="text-[9.5px] font-semibold text-[#cfe9dd]"
                            >
                                {{ shortName(coachByKey(slot.coach)!) }}
                            </span>
                        </button>
                    </template>

                    <!-- Another club has the hall then: interval only, no details. -->
                    <div v-else class="mt-px text-[9.5px] text-[#c98b7a]">
                        Sala e ocupată
                    </div>

                    <!-- How many other clubs share this interval, and which ones. -->
                    <div
                        v-if="slot.otherClubs.length"
                        class="mt-[5px] flex items-center gap-1 border-t pt-[5px]"
                        :class="
                            slot.foreign
                                ? 'border-[#d4573f]/25'
                                : 'border-white/10'
                        "
                    >
                        <span
                            class="font-jetbrains text-[9.5px] font-bold"
                            :class="
                                slot.foreign
                                    ? 'text-[#e08268]'
                                    : 'text-[#9fb3a6]'
                            "
                        >
                            +{{ clubLabel(slot) }}
                        </span>
                        <button
                            type="button"
                            class="flex h-[13px] w-[13px] cursor-pointer items-center justify-center rounded-full border text-[8px] font-bold transition-colors"
                            :class="
                                slot.foreign
                                    ? 'border-[#e08268]/60 text-[#e08268] hover:bg-[#e08268]/20'
                                    : 'border-[#9fb3a6]/60 text-[#9fb3a6] hover:bg-white/10'
                            "
                            :aria-label="`Vezi cluburile care au antrenament la ${slot.time}`"
                            @click="$emit('openHall', slot)"
                        >
                            i
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
