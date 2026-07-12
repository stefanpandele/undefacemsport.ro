<script setup lang="ts">
const steps = [
    {
        no: 'PASUL 01',
        h: 'Caută locația sau sportul',
        p: 'Filtrează după oraș, sport sau facilități.',
    },
    {
        no: 'PASUL 02',
        h: 'Vezi cluburile și orarul',
        p: 'Zile, ore, grupe de vârstă — ținute la zi de cluburi.',
    },
    {
        no: 'PASUL 03',
        h: 'Contactează direct',
        p: 'Telefon sau WhatsApp, fără intermediari.',
    },
];
</script>

<template>
    <section class="py-13">
        <div class="wrap">
            <div
                class="relative overflow-hidden rounded-3xl bg-panel px-6 py-9 text-[#eaf1ec] min-[900px]:px-12 min-[900px]:py-[54px]"
            >
                <svg
                    class="pointer-events-none absolute inset-0 opacity-[0.16]"
                    viewBox="0 0 1200 600"
                    preserveAspectRatio="xMidYMid slice"
                >
                    <g stroke="#15B877" stroke-width="1.4" fill="none">
                        <circle cx="600" cy="300" r="120" />
                        <line x1="600" y1="0" x2="600" y2="600" />
                    </g>
                </svg>

                <div class="relative z-[2] mb-[30px] max-w-[34ch]">
                    <span
                        class="font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass uppercase"
                    >
                        Cum funcționează
                    </span>
                    <h2
                        class="mt-2.5 font-archivo text-[clamp(24px,3.2vw,34px)] font-extrabold text-white"
                    >
                        De la „unde fac sport?" la numărul de telefon, în trei pași.
                    </h2>
                </div>

                <div class="relative z-[2] grid gap-5 md:grid-cols-3">
                    <div
                        v-for="step in steps"
                        :key="step.no"
                        class="border-t-2 border-white/[0.16] pt-4"
                    >
                        <div
                            class="font-jetbrains text-xs font-bold tracking-[0.1em] text-grass"
                        >
                            {{ step.no }}
                        </div>
                        <h3 class="mt-2 mb-1.5 font-archivo text-[19px] font-bold text-white">
                            {{ step.h }}
                        </h3>
                        <p class="text-sm text-[#9fb3a6]">{{ step.p }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
