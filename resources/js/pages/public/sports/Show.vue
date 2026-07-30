<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import SiteNav from '@/components/SiteNav.vue';
import { sportGradient } from '@/lib/gradients';
import locations from '@/routes/locations';
import sportRoutes from '@/routes/sports';

type CityOption = {
    name: string;
    slug: string;
    locationCount: number;
    ways: Record<string, number>;
};

type LocationCard = {
    slug: string;
    name: string;
    address: string;
    price: string | null;
    openNow: boolean | null;
    who: string;
};

type Way = {
    key: string;
    label: string;
    description: string;
    locations: LocationCard[];
};

const props = defineProps<{
    sport: { key: string; label: string; icon: string; color: string | null };
    city: string | null;
    cities: CityOption[];
    ways: Way[];
}>();

const gradient = computed(() => sportGradient(props.sport.color));

const totalPlaces = computed(() =>
    props.ways.reduce((sum, way) => sum + way.locations.length, 0),
);

// The title is the page's whole SEO argument: "baschet cluj" is the search, so
// those two words come first and in that order.
const title = computed(() =>
    props.city
        ? `${props.sport.label} în ${props.city} — unde poți juca`
        : `${props.sport.label} — unde poți face ${props.sport.label.toLowerCase()}`,
);
</script>

<template>
    <Head>
        <title>{{ title }}</title>
        <meta
            name="description"
            :content="
                city
                    ? `${totalPlaces} locuri unde poți face ${sport.label.toLowerCase()} în ${city}: cluburi, acces liber și terenuri de închiriat, cu program și prețuri.`
                    : `Orașele în care poți face ${sport.label.toLowerCase()}, cu cluburi, acces liber și terenuri de închiriat.`
            "
        />
    </Head>

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[1180px] px-5 pt-12 pb-20">
            <!-- Hero -->
            <div class="flex flex-wrap items-center gap-4">
                <div
                    class="flex h-[72px] w-[72px] items-center justify-center rounded-2xl text-[34px] text-white"
                    :style="{ background: gradient }"
                >
                    {{ sport.icon }}
                </div>
                <div>
                    <nav
                        class="mb-1.5 font-jetbrains text-[11px] tracking-[0.11em] text-sage uppercase"
                    >
                        <Link
                            :href="sportRoutes.index.url()"
                            class="hover:text-grass-deep"
                        >
                            Sporturi
                        </Link>
                        <span class="mx-1.5">/</span>
                        <template v-if="city">
                            <Link
                                :href="sportRoutes.show.url({ slug: sport.key })"
                                class="hover:text-grass-deep"
                            >
                                {{ sport.label }}
                            </Link>
                            <span class="mx-1.5">/</span>
                            <span class="text-ink">{{ city }}</span>
                        </template>
                        <span v-else class="text-ink">{{ sport.label }}</span>
                    </nav>
                    <h1
                        class="font-archivo text-[clamp(26px,5vw,40px)] leading-[1.05] font-extrabold tracking-[-0.02em]"
                    >
                        <template v-if="city">
                            {{ sport.label }} în {{ city }}
                        </template>
                        <template v-else>{{ sport.label }}</template>
                    </h1>
                </div>
            </div>

            <!-- City picker. Deliberately not guessed for the visitor: sending
                 someone from Cluj to București without saying so is worse than
                 asking. -->
            <section v-if="!city" class="pt-10">
                <h2 class="mb-1 font-archivo text-[19px] font-extrabold">
                    În ce oraș?
                </h2>
                <p class="mb-4 text-[14.5px] text-sage">
                    Alege orașul ca să vezi unde poți face
                    {{ sport.label.toLowerCase() }} și cum se intră.
                </p>

                <div
                    v-if="cities.length"
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="option in cities"
                        :key="option.slug"
                        :href="
                            sportRoutes.show.url({
                                slug: sport.key,
                                city: option.slug,
                            })
                        "
                        class="rounded-2xl border-[1.5px] border-line bg-white px-4.5 py-4 transition hover:-translate-y-0.5 hover:border-grass"
                    >
                        <div class="font-archivo text-base font-extrabold">
                            {{ option.name }}
                        </div>
                        <div
                            class="mt-1 font-jetbrains text-[11px] text-sage uppercase"
                        >
                            {{ option.locationCount }}
                            {{ option.locationCount === 1 ? 'LOC' : 'LOCURI' }}
                        </div>
                        <ul class="mt-2.5 flex flex-wrap gap-1.5">
                            <li
                                v-for="(count, key) in option.ways"
                                :key="key"
                                class="rounded-full border border-line px-2.5 py-0.5 text-[11.5px] text-sage"
                            >
                                <template v-if="key === 'organizat'">
                                    {{ count }} cu cluburi
                                </template>
                                <template v-else-if="key === 'liber'">
                                    {{ count }} cu acces liber
                                </template>
                                <template v-else>
                                    {{ count }} de închiriat
                                </template>
                            </li>
                        </ul>
                    </Link>
                </div>

                <div
                    v-else
                    class="rounded-2xl border-[1.5px] border-dashed border-line px-5 py-12 text-center text-sage"
                >
                    <div class="mb-2.5 text-[26px]">🏟️</div>
                    <p class="mx-auto max-w-[40ch] text-sm">
                        Nu avem încă niciun loc unde se poate face
                        {{ sport.label.toLowerCase() }}. Dacă știi unul, spune-ne.
                    </p>
                </div>
            </section>

            <!-- The answer: where, grouped by how you get in. -->
            <template v-else>
                <p class="pt-4 text-[15px] text-sage">
                    {{ totalPlaces }}
                    {{ totalPlaces === 1 ? 'loc' : 'locuri' }} în {{ city }},
                    grupate după cum se intră.
                </p>

                <div v-if="cities.length > 1" class="mt-5 flex flex-wrap gap-1.5">
                    <Link
                        v-for="option in cities"
                        :key="option.slug"
                        :href="
                            sportRoutes.show.url({
                                slug: sport.key,
                                city: option.slug,
                            })
                        "
                        class="rounded-full border px-3 py-1 text-[13px] transition"
                        :class="
                            option.name === city
                                ? 'border-grass bg-white font-semibold text-grass-deep'
                                : 'border-line text-sage hover:border-grass'
                        "
                    >
                        {{ option.name }}
                    </Link>
                </div>

                <section
                    v-for="way in ways"
                    :key="way.key"
                    class="pt-10"
                >
                    <h2 class="font-archivo text-[19px] font-extrabold">
                        {{ way.label }}
                    </h2>
                    <p class="mb-4 text-[14px] text-sage">{{ way.description }}</p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <Link
                            v-for="place in way.locations"
                            :key="place.slug"
                            :href="
                                locations.show.url({ slug: place.slug }) +
                                `?sport=${sport.key}`
                            "
                            class="flex flex-col gap-1 rounded-2xl border-[1.5px] border-line bg-white px-4.5 py-4 transition hover:-translate-y-0.5 hover:border-grass"
                        >
                            <div
                                class="flex flex-wrap items-baseline justify-between gap-2"
                            >
                                <span class="font-archivo text-base font-extrabold">
                                    {{ place.name }}
                                </span>
                                <span
                                    v-if="place.openNow"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#eaf6ef] px-2 py-0.5 font-jetbrains text-[10.5px] font-bold text-grass-deep"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-grass" />
                                    DESCHIS
                                </span>
                            </div>
                            <span class="text-[13px] text-sage">
                                {{ place.address }}
                            </span>
                            <span
                                v-if="place.price"
                                class="mt-0.5 font-jetbrains text-[13px] font-semibold"
                            >
                                {{ place.price }}
                            </span>
                            <span class="font-jetbrains text-[11px] text-sage">
                                {{ place.who }}
                            </span>
                        </Link>
                    </div>
                </section>

                <div
                    v-if="!ways.length"
                    class="mt-8 rounded-2xl border-[1.5px] border-dashed border-line px-5 py-12 text-center text-sage"
                >
                    <p class="mx-auto max-w-[40ch] text-sm">
                        Nu avem încă niciun loc de
                        {{ sport.label.toLowerCase() }} în {{ city }}.
                    </p>
                </div>
            </template>
        </div>
    </div>
</template>
