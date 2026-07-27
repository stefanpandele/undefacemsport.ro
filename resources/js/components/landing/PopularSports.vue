<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { explore } from '@/routes';
import sportRoutes from '@/routes/sports';

export type PopularSport = {
    key: string;
    label: string;
    icon: string;
    color: string | null;
    locationCount: number;
    clubCount: number;
    cityCount: number;
};

defineProps<{ sports: PopularSport[] }>();
</script>

<template>
    <section v-if="sports.length" class="py-13">
        <div class="wrap">
            <div class="mb-[26px] flex items-end justify-between gap-4">
                <h2
                    class="font-archivo text-[clamp(24px,3.4vw,34px)] font-extrabold tracking-[-0.02em]"
                >
                    Sporturi populare
                </h2>
                <Link
                    :href="sportRoutes.index.url()"
                    class="hidden shrink-0 text-[13.5px] font-semibold text-grass-deep hover:underline sm:block"
                >
                    Vezi toate sporturile →
                </Link>
            </div>

            <!-- Seven sports and a way out, so the row always ends on a whole
                 card instead of trailing off. -->
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-8">
                <Link
                    v-for="sport in sports"
                    :key="sport.key"
                    :href="explore.url({ query: { sport: sport.key } })"
                    class="rounded-[14px] border border-line bg-white px-2.5 py-4 text-center transition hover:-translate-y-[3px] hover:border-grass"
                >
                    <div class="text-[17px]">{{ sport.icon }}</div>
                    <div class="mt-1 text-[13px] font-semibold">
                        {{ sport.label }}
                    </div>
                    <div class="mt-0.5 font-jetbrains text-[10px] text-sage">
                        {{ sport.locationCount }}
                        {{ sport.locationCount === 1 ? 'locație' : 'locații' }}
                    </div>
                </Link>

                <Link
                    :href="sportRoutes.index.url()"
                    class="flex flex-col items-center justify-center rounded-[14px] border border-dashed border-grass bg-[#eaf6ef] px-2.5 py-4 text-center transition hover:-translate-y-[3px] hover:bg-[#e0f1e8]"
                >
                    <div class="text-[15px] font-semibold text-grass-deep">
                        Vezi toate
                    </div>
                    <div
                        class="mt-0.5 font-jetbrains text-[10px] text-grass-deep/80"
                    >
                        sporturile →
                    </div>
                </Link>
            </div>
        </div>
    </section>
</template>
