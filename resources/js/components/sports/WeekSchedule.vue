<script setup lang="ts">
import type { Coach, ScheduleDay } from '@/types/sports';

const props = defineProps<{
    schedule: ScheduleDay[];
    coaches: Coach[];
}>();

defineEmits<{ openCoach: [coach: Coach] }>();

function coachByKey(key: string): Coach | undefined {
    return props.coaches.find((c) => c.key === key);
}

function shortName(coach: Coach): string {
    const [first, last] = coach.name.split(' ');
    return `${first} ${last?.[0] ?? ''}.`;
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
                <span v-if="!row.slots.length" class="font-jetbrains text-[11.5px] text-[#4b5a51]">
                    —
                </span>
                <div
                    v-for="(slot, i) in row.slots"
                    :key="i"
                    class="rounded-lg border border-grass/35 bg-grass/10 px-2.5 py-[5px] leading-tight"
                >
                    <div class="font-jetbrains text-[11.5px] font-bold text-grass">
                        {{ slot.time }}
                    </div>
                    <div class="mt-px text-[9.5px] text-[#9fb3a6]">{{ slot.group }}</div>
                    <button
                        v-if="coachByKey(slot.coach)"
                        type="button"
                        class="mt-[5px] flex items-center gap-1.5 border-t border-white/10 pt-[5px]"
                        @click="$emit('openCoach', coachByKey(slot.coach)!)"
                    >
                        <span
                            class="flex h-4 w-4 items-center justify-center rounded-full text-[8px]"
                            :style="{ background: coachByKey(slot.coach)!.gradient }"
                        >
                            🧑‍🏫
                        </span>
                        <span class="text-[9.5px] font-semibold text-[#cfe9dd]">
                            {{ shortName(coachByKey(slot.coach)!) }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
