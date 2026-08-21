<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DirectoryFilters from '@/components/directory/DirectoryFilters.vue';
import SiteNav from '@/components/SiteNav.vue';
import directory from '@/routes/directory';
import organizations from '@/routes/organizations';

type PracticeCard = {
    slug: string;
    name: string;
    specialties: { key: string; label: string; icon: string }[];
    cities: string[];
};

const props = defineProps<{
    practices: PracticeCard[];
    counties: string[];
    specialties: { value: string; label: string; icon: string }[];
    filters: { county: string | null; specialty: string | null };
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
        ? props.practices.filter((practice) =>
              normalize(practice.name).includes(needle),
          )
        : props.practices;
});
</script>

<template>
    <Head title="Cabinete și clinici — Unde Facem Sport" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[1180px] px-5 pt-12 pb-20">
            <div class="text-center">
                <span
                    class="mb-3.5 block font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass-deep uppercase"
                >
                    {{ practices.length }}
                    {{ practices.length === 1 ? 'cabinet' : 'cabinete' }}
                </span>
                <h1
                    class="font-archivo text-[clamp(28px,5.5vw,44px)] leading-[1.05] font-extrabold tracking-[-0.02em]"
                >
                    Unde te recuperezi
                </h1>
                <p class="mx-auto mt-3.5 max-w-[48ch] text-[16px] text-sage">
                    Kinetoterapie, fizioterapie, medicină sportivă, nutriție —
                    pe programare, la cine lucrează cu sportivi.
                </p>

                <DirectoryFilters
                    v-model:query="query"
                    :url="directory.practices.url()"
                    :counties="counties"
                    :options="specialties"
                    :county="filters.county"
                    :option="filters.specialty"
                    option-param="specialitate"
                    option-placeholder="Toate specialitățile"
                    option-label="Specialitate"
                    search-placeholder="Caută un cabinet…"
                />
            </div>

            <p
                v-if="!matching.length"
                class="py-14 text-center text-[14.5px] text-sage"
            >
                Niciun cabinet care să răspundă filtrelor.
            </p>

            <div
                v-else
                class="mt-10 grid gap-3.5 min-[900px]:grid-cols-3 sm:grid-cols-2"
            >
                <Link
                    v-for="practice in matching"
                    :key="practice.slug"
                    :href="
                        organizations.show.url(practice.slug) + '#servicii'
                    "
                    class="flex flex-col gap-2.5 rounded-2xl border-[1.5px] border-line bg-white px-4.5 py-4 transition hover:-translate-y-0.5 hover:border-grass"
                >
                    <span class="font-archivo text-base font-extrabold">
                        {{ practice.name }}
                    </span>
                    <span
                        v-if="practice.cities.length"
                        class="text-[13px] text-sage"
                    >
                        📍 {{ practice.cities.join(' · ') }}
                    </span>
                    <span class="mt-auto flex flex-wrap gap-1.5 pt-1">
                        <span
                            v-for="specialty in practice.specialties"
                            :key="specialty.key"
                            class="rounded-lg bg-[#eef1fb] px-2.5 py-1 text-[11.5px] font-semibold text-[#3d4b9e]"
                        >
                            {{ specialty.icon }} {{ specialty.label }}
                        </span>
                    </span>
                </Link>
            </div>
        </div>
    </div>
</template>
