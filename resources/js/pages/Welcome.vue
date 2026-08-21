<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AccountCta from '@/components/landing/AccountCta.vue';
import CityNumbers from '@/components/landing/CityNumbers.vue';
import HeroSection from '@/components/landing/HeroSection.vue';
import HowItWorks from '@/components/landing/HowItWorks.vue';
import PopularSports from '@/components/landing/PopularSports.vue';
import type { PopularSport } from '@/components/landing/PopularSports.vue';
import SiteFooter from '@/components/landing/SiteFooter.vue';
import SiteNav from '@/components/SiteNav.vue';

defineProps<{
    popularSports: PopularSport[];
    sports: { value: string; label: string }[];
    cities: { name: string; lat: number | null; lng: number | null }[];
    stats: { locations: number; clubs: number; cities: number; sports: number };
}>();

// Shared state: sharing location in the hero reveals the "city numbers" section.
const locationShared = ref(false);

function shareLocation() {
    locationShared.value = true;
}
</script>

<template>
    <Head title="Unde Facem Sport — Toate locațiile sportive din România" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />
        <HeroSection
            :sports="sports"
            :cities="cities"
            :stats="stats"
            @share="shareLocation"
        />
        <PopularSports :sports="popularSports" />
        <CityNumbers :location-shared="locationShared" @share="shareLocation" />
        <HowItWorks />
        <AccountCta />
        <SiteFooter />
    </div>
</template>
