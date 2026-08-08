<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DirectoryFilters from '@/components/directory/DirectoryFilters.vue';
import SiteNav from '@/components/SiteNav.vue';
import directory from '@/routes/directory';
import locations from '@/routes/locations';

/**
 * A place rather than a company, because that is what is being chosen. Whoever
 * runs the pool never enters into "I want to swim after work" — and the park
 * court has no operator at all.
 */
type VenueCard = {
    slug: string;
    name: string;
    city: string;
    address: string;
    sports: { key: string; label: string; icon: string }[];
    ways: { key: string; label: string; price: string | null }[];
    unmanaged: boolean;
};

const props = defineProps<{
    venues: VenueCard[];
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
        ? props.venues.filter(
              (venue) =>
                  normalize(venue.name).includes(needle) ||
                  normalize(venue.city).includes(needle),
          )
        : props.venues;
});
</script>

<template>
    <Head title="Baze sportive — Unde Facem Sport" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[1180px] px-5 pt-12 pb-20">
            <div class="text-center">
                <span
                    class="mb-3.5 block font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass-deep uppercase"
                >
                    {{ venues.length }}
                    {{ venues.length === 1 ? 'loc' : 'locuri' }}
                </span>
                <h1
                    class="font-archivo text-[clamp(28px,5.5vw,44px)] leading-[1.05] font-extrabold tracking-[-0.02em]"
                >
                    Unde poți intra singur
                </h1>
                <p class="mx-auto mt-3.5 max-w-[48ch] text-[16px] text-sage">
                    Fără înscriere și fără grupă: plătești intrarea și intri, sau
                    închiriezi tot spațiul pentru grupul tău.
                </p>

                <DirectoryFilters
                    v-model:query="query"
                    :url="directory.venues.url()"
                    :counties="counties"
                    :options="sports"
                    :county="filters.county"
                    :option="filters.sport"
                    option-param="sport"
                    option-placeholder="Toate sporturile"
                    option-label="Sport"
                    search-placeholder="Caută un loc sau un oraș…"
                />
            </div>

            <p
                v-if="!matching.length"
                class="py-14 text-center text-[14.5px] text-sage"
            >
                Niciun loc care să răspundă filtrelor.
            </p>

            <div
                v-else
                class="mt-10 grid gap-3.5 min-[900px]:grid-cols-3 sm:grid-cols-2"
            >
                <Link
                    v-for="venue in matching"
                    :key="venue.slug"
                    :href="locations.show.url(venue.slug)"
                    class="flex flex-col gap-2.5 rounded-2xl border-[1.5px] border-line bg-white px-4.5 py-4 transition hover:-translate-y-0.5 hover:border-grass"
                >
                    <span class="font-archivo text-base font-extrabold">
                        {{ venue.name }}
                    </span>
                    <span class="text-[13px] text-sage">
                        📍 {{ [venue.address, venue.city].filter(Boolean).join(', ') }}
                    </span>

                    <span
                        v-if="venue.unmanaged"
                        class="w-fit rounded-lg bg-[#eaf6ef] px-2.5 py-1 text-[11.5px] font-semibold text-grass-deep"
                    >
                        Spațiu public, neadministrat
                    </span>

                    <span class="flex flex-col gap-1">
                        <span
                            v-for="way in venue.ways"
                            :key="way.key"
                            class="flex justify-between gap-2 text-[13px]"
                        >
                            <span class="text-sage">{{ way.label }}</span>
                            <span class="font-jetbrains font-semibold">
                                {{ way.price ?? 'preț nespecificat' }}
                            </span>
                        </span>
                    </span>

                    <span
                        v-if="venue.sports.length"
                        class="mt-auto flex flex-wrap gap-1.5 pt-1"
                    >
                        <span
                            v-for="sport in venue.sports"
                            :key="sport.key"
                            class="rounded-lg border border-line bg-[#f2f5ef] px-2.5 py-1 text-[11.5px] font-semibold text-sage"
                        >
                            {{ sport.icon }} {{ sport.label }}
                        </span>
                    </span>
                </Link>
            </div>
        </div>
    </div>
</template>
