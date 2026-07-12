<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineEmits<{ share: [] }>();

const radii = ['2 km', '5 km', '10 km', '20 km'];
const activeRadius = ref('5 km');

const stats = [
    { n: '340+', l: 'Locații' },
    { n: '1.100+', l: 'Cluburi active' },
    { n: '18', l: 'Orașe' },
    { n: '9', l: 'Sporturi' },
];
</script>

<template>
    <section class="relative overflow-hidden pt-11 pb-14">
        <svg
            class="pointer-events-none absolute inset-0 z-0 opacity-[0.12]"
            viewBox="0 0 1200 500"
            preserveAspectRatio="xMidYMid slice"
        >
            <g stroke="#15B877" stroke-width="1.4" fill="none">
                <circle cx="1000" cy="120" r="110" />
                <rect x="1080" y="60" width="140" height="260" />
            </g>
        </svg>

        <div
            class="wrap relative z-[1] grid gap-9 min-[900px]:grid-cols-[1.1fr_0.9fr] min-[900px]:items-center"
        >
            <div>
                <span
                    class="mb-3.5 block font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass-deep uppercase"
                >
                    Catalogul sportului din România
                </span>
                <h1
                    class="font-archivo text-[clamp(30px,6vw,52px)] leading-[1.02] font-extrabold tracking-[-0.02em]"
                >
                    Unde faci sport, azi?
                </h1>
                <p class="my-4 max-w-[44ch] text-[16.5px] text-sage">
                    Cauți locația, vezi cluburile și orarul, suni direct. Fără
                    grupuri Facebook, fără telefoane la nimereală.
                </p>

                <div
                    class="flex flex-col gap-2 rounded-[14px] border border-line bg-white p-2 shadow-[0_20px_40px_-30px_rgba(11,20,16,0.35)] sm:flex-row"
                >
                    <select
                        class="flex-1 rounded-[10px] border-none bg-[#f7f8f6] px-3.5 py-3 text-[14.5px]"
                    >
                        <option>Ce sport?</option>
                        <option>Fotbal</option>
                        <option>Baschet</option>
                        <option>Înot</option>
                        <option>Tenis de masă</option>
                    </select>
                    <select
                        class="flex-1 rounded-[10px] border-none bg-[#f7f8f6] px-3.5 py-3 text-[14.5px]"
                    >
                        <option>În ce oraș?</option>
                        <option>București</option>
                        <option>Cluj-Napoca</option>
                        <option>Brașov</option>
                        <option>Timișoara</option>
                    </select>
                    <button
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-clay px-[22px] py-3 text-[14.5px] font-semibold whitespace-nowrap text-white transition hover:bg-[#e6501c]"
                    >
                        Caută
                    </button>
                </div>

                <div
                    class="mt-[34px] grid grid-cols-2 overflow-hidden rounded-2xl border border-line bg-white sm:grid-cols-4"
                >
                    <div
                        v-for="(s, i) in stats"
                        :key="s.l"
                        class="border-line px-5 py-[18px]"
                        :class="[
                            i % 2 === 0 ? 'border-r' : '',
                            i < 2 ? 'border-b sm:border-b-0' : '',
                            'sm:border-r',
                            i === stats.length - 1 ? 'sm:border-r-0' : '',
                        ]"
                    >
                        <div class="font-archivo text-[26px] font-extrabold">
                            <span class="text-grass-deep">{{ s.n }}</span>
                        </div>
                        <div
                            class="mt-1 font-jetbrains text-[10.5px] font-semibold tracking-[0.1em] text-sage uppercase"
                        >
                            {{ s.l }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- GEO CARD -->
            <div
                class="relative overflow-hidden rounded-[20px] border border-line bg-white text-center shadow-[0_30px_60px_-32px_rgba(11,20,16,0.4)]"
            >
                <svg
                    class="pointer-events-none absolute top-0 left-0 h-[140px] w-full opacity-[0.15]"
                    viewBox="0 0 300 140"
                >
                    <circle
                        cx="150"
                        cy="20"
                        r="70"
                        stroke="#15B877"
                        stroke-width="1.4"
                        fill="none"
                    />
                </svg>
                <div
                    class="relative bg-[linear-gradient(180deg,#eaf6ef,transparent)] px-6 pt-[34px] pb-[22px]"
                >
                    <div
                        class="mx-auto mb-[18px] flex h-[52px] w-[52px] rotate-[-45deg] items-center justify-center rounded-[50%_50%_50%_0] bg-clay shadow-[0_10px_20px_-8px_rgba(255,90,44,0.5)]"
                    >
                        <span class="rotate-45 text-xl text-white">📍</span>
                    </div>
                    <h3 class="mb-2 font-archivo text-xl font-extrabold">
                        Vezi ce ai lângă tine
                    </h3>
                    <p class="mx-auto max-w-[30ch] text-sm text-sage">
                        Îți arătăm locurile sportive din orașul tău — nu dintr-un
                        oraș în care nu ești.
                    </p>
                </div>
                <div class="px-6 pt-5 pb-[26px]">
                    <div class="mb-4">
                        <span class="mb-2 block text-xs font-semibold text-sage">
                            Caută într-o rază de
                        </span>
                        <div class="flex justify-center gap-1.5">
                            <button
                                v-for="r in radii"
                                :key="r"
                                type="button"
                                class="cursor-pointer rounded-lg border-[1.5px] px-3 py-[7px] font-jetbrains text-xs font-bold transition"
                                :class="
                                    activeRadius === r
                                        ? 'border-grass bg-grass text-white'
                                        : 'border-line bg-white text-sage'
                                "
                                @click="activeRadius = r"
                            >
                                {{ r }}
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mb-3 inline-flex w-full items-center justify-center gap-2 rounded-full bg-clay px-[22px] py-3 text-[14.5px] font-semibold text-white transition hover:bg-[#e6501c]"
                        @click="$emit('share')"
                    >
                        📍 Distribuie locația
                    </button>
                    <div class="text-[13px] text-sage">
                        sau
                        <a href="#" class="font-semibold text-grass-deep">
                            alege orașul manual →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
