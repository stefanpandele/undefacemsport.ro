<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DirectoryFilters from '@/components/directory/DirectoryFilters.vue';
import SiteNav from '@/components/SiteNav.vue';
import { sportGradient } from '@/lib/gradients';
import directory from '@/routes/directory';
import organizations from '@/routes/organizations';

type ClubCard = {
    slug: string;
    name: string;
    sports: { key: string; label: string; icon: string; color: string | null }[];
    cities: string[];
};

const props = defineProps<{
    clubs: ClubCard[];
    counties: string[];
    sports: { value: string; label: string; icon: string }[];
    filters: { county: string | null; sport: string | null };
}>();

const query = ref('');

function normalize(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

const matching = computed(() => {
    const needle = normalize(query.value.trim());

    return needle
        ? props.clubs.filter((club) => normalize(club.name).includes(needle))
        : props.clubs;
});
</script>

<template>
    <Head title="Cluburi sportive — Unde Facem Sport" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[1180px] px-5 pt-12 pb-20">
            <div class="text-center">
                <span
                    class="mb-3.5 block font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass-deep uppercase"
                >
                    {{ clubs.length }}
                    {{ clubs.length === 1 ? 'club' : 'cluburi' }}
                </span>
                <h1
                    class="font-archivo text-[clamp(28px,5.5vw,44px)] leading-[1.05] font-extrabold tracking-[-0.02em]"
                >
                    Cine te învață
                </h1>
                <p class="mx-auto mt-3.5 max-w-[48ch] text-[16px] text-sage">
                    Antrenamente pe grupe, cu antrenor și program recurent.
                    Alege pe cine vrei să te învețe, apoi vezi unde și când.
                </p>

                <DirectoryFilters
                    v-model:query="query"
                    :url="directory.clubs.url()"
                    :counties="counties"
                    :options="sports"
                    :county="filters.county"
                    :option="filters.sport"
                    option-param="sport"
                    option-placeholder="Toate sporturile"
                    option-label="Sport"
                    search-placeholder="Caută un club…"
                />
            </div>

            <p
                v-if="!matching.length"
                class="py-14 text-center text-[14.5px] text-sage"
            >
                Niciun club care să răspundă filtrelor.
            </p>

            <div
                v-else
                class="mt-10 grid gap-3.5 min-[900px]:grid-cols-3 sm:grid-cols-2"
            >
                <Link
                    v-for="club in matching"
                    :key="club.slug"
                    :href="organizations.show.url(club.slug) + '#cursuri'"
                    class="flex flex-col gap-2.5 rounded-2xl border-[1.5px] border-line bg-white px-4.5 py-4 transition hover:-translate-y-0.5 hover:border-grass"
                >
                    <span class="font-archivo text-base font-extrabold">
                        {{ club.name }}
                    </span>
                    <span v-if="club.cities.length" class="text-[13px] text-sage">
                        📍 {{ club.cities.join(' · ') }}
                    </span>
                    <span class="mt-auto flex flex-wrap gap-1.5 pt-1">
                        <span
                            v-for="sport in club.sports"
                            :key="sport.key"
                            class="rounded-lg px-2.5 py-1 text-[11.5px] font-semibold text-white"
                            :style="{ background: sportGradient(sport.color) }"
                        >
                            {{ sport.icon }} {{ sport.label }}
                        </span>
                    </span>
                </Link>
            </div>
        </div>
    </div>
</template>
