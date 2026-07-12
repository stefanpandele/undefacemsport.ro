<script setup lang="ts">
import { ref } from 'vue';

defineProps<{ locationShared: boolean }>();
defineEmits<{ share: [] }>();

const radii = ['2 km', '5 km', '10 km', '20 km'];
const activeRadius = ref('5 km');

const emptyStats = [
    'Cluburi active',
    'Locații listate',
    'Sporturi disponibile',
    'Sport popular',
];
const filledStats = [
    { n: '211', l: 'Cluburi active' },
    { n: '54', l: 'Locații listate' },
    { n: '8', l: 'Sporturi disponibile' },
    { n: 'Baschet', l: 'Cel mai popular sport' },
];
</script>

<template>
    <section id="locatii" class="py-13">
        <div class="wrap">
            <div
                class="relative overflow-hidden rounded-3xl bg-panel px-6 py-[30px] text-[#eaf1ec] min-[900px]:px-11 min-[900px]:py-10"
            >
                <svg
                    class="pointer-events-none absolute inset-0 opacity-[0.12]"
                    viewBox="0 0 1200 500"
                    preserveAspectRatio="xMidYMid slice"
                >
                    <g stroke="#15B877" stroke-width="1.4" fill="none">
                        <circle cx="1050" cy="80" r="100" />
                    </g>
                </svg>

                <!-- EMPTY STATE -->
                <div
                    v-if="!locationShared"
                    class="relative z-[2] px-2.5 pt-5 pb-1.5 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-full border-[1.5px] border-dashed border-white/25 bg-white/[0.06] text-lg"
                    >
                        📍
                    </div>
                    <h3 class="mb-2 font-archivo text-[19px] font-extrabold text-white">
                        Orașul tău în cifre
                    </h3>
                    <p class="mx-auto mb-5 max-w-[36ch] text-sm text-[#9fb3a6]">
                        Distribuie-ți locația mai sus ca să vezi câte cluburi și
                        locații active sunt lângă tine.
                    </p>
                    <div
                        class="mx-auto mb-[22px] grid max-w-[340px] grid-cols-2 gap-3.5 opacity-50 md:max-w-none md:grid-cols-4"
                    >
                        <div
                            v-for="l in emptyStats"
                            :key="l"
                            class="border-t-2 border-white/[0.14] pt-3.5"
                        >
                            <div class="font-jetbrains text-[26px] font-bold text-[#5c6b62]">
                                —
                            </div>
                            <div class="mt-1 text-[12.5px] text-[#9fb3a6]">{{ l }}</div>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-clay px-[22px] py-3 text-[14.5px] font-semibold text-white transition hover:bg-[#e6501c]"
                        @click="$emit('share')"
                    >
                        📍 Distribuie locația
                    </button>
                </div>

                <!-- FILLED STATE -->
                <div v-else class="motion-safe:animate-in motion-safe:fade-in">
                    <div
                        class="relative z-[2] mb-[26px] flex flex-col gap-1.5 md:flex-row md:items-end md:justify-between"
                    >
                        <div>
                            <span
                                class="flex items-center gap-[7px] font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass uppercase"
                            >
                                <span class="h-[7px] w-[7px] rounded-full bg-grass" />
                                Detectat din locația ta
                            </span>
                            <h2
                                class="mt-2 flex flex-wrap items-center gap-2.5 font-archivo text-[clamp(24px,3.4vw,36px)] font-extrabold text-white"
                            >
                                Cluj-Napoca
                                <span
                                    class="font-inter text-[15px] font-medium text-[#9fb3a6]"
                                >
                                    în cifre
                                </span>
                            </h2>
                        </div>
                        <a href="#" class="text-[13px] font-semibold text-grass whitespace-nowrap">
                            Nu ești aici? Schimbă orașul →
                        </a>
                    </div>

                    <div class="relative z-[2] mb-[22px] flex flex-wrap items-center gap-2">
                        <span class="text-[11.5px] whitespace-nowrap text-[#9fb3a6]">
                            Rază de căutare:
                        </span>
                        <button
                            v-for="r in radii"
                            :key="r"
                            type="button"
                            class="cursor-pointer rounded-lg border-[1.5px] px-[11px] py-1.5 font-jetbrains text-[11.5px] font-bold transition"
                            :class="
                                activeRadius === r
                                    ? 'border-grass bg-grass text-panel'
                                    : 'border-white/20 bg-transparent text-[#9fb3a6]'
                            "
                            @click="activeRadius = r"
                        >
                            {{ r }}
                        </button>
                    </div>

                    <div class="relative z-[2] grid grid-cols-2 gap-3.5 md:grid-cols-4">
                        <div
                            v-for="s in filledStats"
                            :key="s.l"
                            class="border-t-2 border-white/[0.14] pt-3.5"
                        >
                            <div class="font-jetbrains text-[26px] font-bold text-white">
                                {{ s.n }}
                            </div>
                            <div class="mt-1 text-[12.5px] text-[#9fb3a6]">{{ s.l }}</div>
                        </div>
                    </div>

                    <div class="relative z-[2] mt-7">
                        <a
                            href="#"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-clay px-[22px] py-3 text-[14.5px] font-semibold text-white transition hover:bg-[#e6501c]"
                        >
                            Explorează Cluj-Napoca →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
