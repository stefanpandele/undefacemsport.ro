<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SiteNav from '@/components/SiteNav.vue';
import { sportGradient } from '@/lib/gradients';
import locations from '@/routes/locations';

type SpecialtyChip = {
    key: string;
    label: string;
    icon: string;
    color: string | null;
    serviceCount: number;
};

type ServiceCard = {
    id: number;
    name: string;
    specialty: string | null;
    specialtyLabel: string | null;
    description: string;
    duration: string | null;
    price: string | null;
    priceNotes: string | null;
    person: string | null;
    sports: { key: string; label: string; icon: string }[];
};

type PracticePerson = {
    key: string;
    name: string;
    profession: string;
    role: string;
    bio: string;
    photo: string | null;
};

const props = defineProps<{
    practice: {
        slug: string;
        name: string;
        about: string;
        phone: string | null;
        specialties: SpecialtyChip[];
        services: ServiceCard[];
        people: PracticePerson[];
        locations: { slug: string; name: string; address: string }[];
    };
}>();

const activeSpecialty = ref<string | null>(null);

const visibleServices = computed(() =>
    activeSpecialty.value
        ? props.practice.services.filter((s) => s.specialty === activeSpecialty.value)
        : props.practice.services,
);

const heroGradient = computed(() =>
    sportGradient(props.practice.specialties[0]?.color ?? null),
);
</script>

<template>
    <Head>
        <title>{{ practice.name }} — servicii și prețuri</title>
        <meta
            name="description"
            :content="`${practice.name}: ${practice.services.length} servicii, cu durată și preț. ${practice.specialties.map((s) => s.label).join(', ')}.`"
        />
    </Head>

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[1180px] px-5 pt-12 pb-20">
            <div class="flex flex-wrap items-center gap-4">
                <div
                    class="flex h-[72px] w-[72px] items-center justify-center rounded-2xl text-[34px] text-white"
                    :style="{ background: heroGradient }"
                >
                    {{ practice.specialties[0]?.icon ?? '🩺' }}
                </div>
                <div>
                    <p
                        class="mb-1.5 font-jetbrains text-[11px] tracking-[0.11em] text-sage uppercase"
                    >
                        Cabinet / clinică
                    </p>
                    <h1
                        class="font-archivo text-[clamp(26px,5vw,40px)] leading-[1.05] font-extrabold tracking-[-0.02em]"
                    >
                        {{ practice.name }}
                    </h1>
                </div>
            </div>

            <p v-if="practice.about" class="mt-5 max-w-[64ch] text-[15px] text-sage">
                {{ practice.about }}
            </p>

            <!-- Derived from the services, so the page cannot claim a specialty it
                 sells nothing for. -->
            <div v-if="practice.specialties.length" class="mt-6">
                <div class="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        class="rounded-full border px-3 py-1 text-[13px] transition"
                        :class="
                            activeSpecialty
                                ? 'border-line text-sage hover:border-grass'
                                : 'border-grass bg-white font-semibold text-grass-deep'
                        "
                        @click="activeSpecialty = null"
                    >
                        Toate
                    </button>
                    <button
                        v-for="specialty in practice.specialties"
                        :key="specialty.key"
                        type="button"
                        class="rounded-full border px-3 py-1 text-[13px] transition"
                        :class="
                            activeSpecialty === specialty.key
                                ? 'border-grass bg-white font-semibold text-grass-deep'
                                : 'border-line text-sage hover:border-grass'
                        "
                        @click="activeSpecialty = specialty.key"
                    >
                        {{ specialty.icon }} {{ specialty.label }}
                        <span class="font-jetbrains text-[11px]">
                            ({{ specialty.serviceCount }})
                        </span>
                    </button>
                </div>
            </div>

            <!-- Services -->
            <section class="pt-10">
                <h2 class="mb-3.5 font-archivo text-[19px] font-extrabold">
                    Servicii
                </h2>

                <div v-if="visibleServices.length" class="grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="service in visibleServices"
                        :key="service.id"
                        class="rounded-2xl border-[1.5px] border-line bg-white p-5"
                    >
                        <header
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <h3 class="font-archivo text-base font-extrabold">
                                {{ service.name }}
                            </h3>
                            <span
                                v-if="service.duration"
                                class="font-jetbrains text-[12px] text-sage"
                            >
                                {{ service.duration }}
                            </span>
                        </header>

                        <p
                            v-if="service.specialtyLabel"
                            class="mt-1 text-[12.5px] text-sage"
                        >
                            {{ service.specialtyLabel }}
                        </p>

                        <p
                            v-if="service.price"
                            class="mt-2.5 font-jetbrains text-[15px] font-bold"
                        >
                            {{ service.price }}
                        </p>
                        <p v-else class="mt-2.5 text-[13px] text-sage">
                            Preț nespecificat — întreabă la programare.
                        </p>
                        <p
                            v-if="service.priceNotes"
                            class="mt-1 text-[13px] text-sage"
                        >
                            {{ service.priceNotes }}
                        </p>

                        <p
                            v-if="service.description"
                            class="mt-2.5 text-[13.5px] text-sage"
                        >
                            {{ service.description }}
                        </p>

                        <!-- Which athletes it is for, as the practitioner ticked
                             it — the reason a footballer finds this page. -->
                        <ul
                            v-if="service.sports.length"
                            class="mt-2.5 flex flex-wrap gap-1.5"
                        >
                            <li
                                v-for="sport in service.sports"
                                :key="sport.key"
                                class="rounded-full border border-line px-2.5 py-0.5 text-[11.5px] text-sage"
                            >
                                {{ sport.icon }} {{ sport.label }}
                            </li>
                        </ul>

                        <p
                            v-if="service.person"
                            class="mt-3 font-jetbrains text-[11.5px] text-sage"
                        >
                            {{ service.person }}
                        </p>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-2xl border-[1.5px] border-dashed border-line px-5 py-12 text-center text-sage"
                >
                    <p class="mx-auto max-w-[40ch] text-sm">
                        Nu sunt încă servicii publicate aici.
                    </p>
                </div>
            </section>

            <!-- The team -->
            <section v-if="practice.people.length" class="pt-10">
                <h2 class="mb-3.5 font-archivo text-[19px] font-extrabold">
                    Echipa
                </h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="person in practice.people"
                        :key="person.key"
                        class="flex gap-3.5 rounded-2xl border-[1.5px] border-line bg-white p-4"
                    >
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-[#f2f5ef] text-[24px]"
                        >
                            <img
                                v-if="person.photo"
                                :src="person.photo"
                                :alt="person.name"
                                class="h-full w-full object-cover"
                            />
                            <template v-else>🩺</template>
                        </div>
                        <div>
                            <div class="font-archivo text-[15px] font-extrabold">
                                {{ person.name }}
                            </div>
                            <div
                                class="font-jetbrains text-[11px] tracking-[0.06em] text-grass-deep uppercase"
                            >
                                {{ person.profession }}
                            </div>
                            <p v-if="person.bio" class="mt-1.5 text-[13px] text-sage">
                                {{ person.bio }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Where -->
            <section v-if="practice.locations.length" class="pt-10">
                <h2 class="mb-3.5 font-archivo text-[19px] font-extrabold">Unde</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="place in practice.locations"
                        :key="place.slug"
                        :href="locations.show.url({ slug: place.slug })"
                        class="rounded-2xl border-[1.5px] border-line bg-white px-4.5 py-4 transition hover:-translate-y-0.5 hover:border-grass"
                    >
                        <div class="font-archivo text-base font-extrabold">
                            {{ place.name }}
                        </div>
                        <div class="mt-1 text-[13px] text-sage">
                            {{ place.address }}
                        </div>
                    </Link>
                </div>
            </section>

            <a
                v-if="practice.phone"
                :href="`tel:${practice.phone}`"
                class="mt-10 inline-block rounded-xl bg-grass px-5 py-3 font-archivo text-[15px] font-extrabold text-white"
            >
                Sună pentru programare
            </a>
        </div>
    </div>
</template>
