<script setup lang="ts">
const sports = [
    { nm: 'Fotbal', ct: '86 locații' },
    { nm: 'Baschet', ct: '54 locații' },
    { nm: 'Înot', ct: '38 locații' },
    { nm: 'Tenis', ct: '41 locații' },
    { nm: 'Tenis de masă', ct: '29 locații' },
    { nm: 'Volei', ct: '22 locații' },
    { nm: 'Handbal', ct: '17 locații' },
    { nm: 'Fitness', ct: '64 locații' },
];
</script>

<template>
    <section class="py-13">
        <div class="wrap">
            <div class="mb-[26px]">
                <h2
                    class="font-archivo text-[clamp(24px,3.4vw,34px)] font-extrabold tracking-[-0.02em]"
                >
                    Sporturi populare
                </h2>
            </div>
            <div
                class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-8"
            >
                <button
                    v-for="sport in sports"
                    :key="sport.nm"
                    type="button"
                    class="cursor-pointer rounded-[14px] border border-line bg-white px-2.5 py-4 text-center transition hover:-translate-y-[3px] hover:border-grass"
                >
                    <div class="text-[13px] font-semibold">{{ sport.nm }}</div>
                    <div class="mt-0.5 font-jetbrains text-[10px] text-sage">
                        {{ sport.ct }}
                    </div>
                </button>
            </div>
        </div>
    </section>
</template>
