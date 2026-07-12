<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import CityNumbers from '@/components/landing/CityNumbers.vue';
import ClubCta from '@/components/landing/ClubCta.vue';
import HeroSection from '@/components/landing/HeroSection.vue';
import HowItWorks from '@/components/landing/HowItWorks.vue';
import PopularSports from '@/components/landing/PopularSports.vue';
import SiteFooter from '@/components/landing/SiteFooter.vue';
import SiteNav from '@/components/landing/SiteNav.vue';

// Shared state: sharing location in the hero reveals the "city numbers" section.
const locationShared = ref(false);

function shareLocation() {
    locationShared.value = true;
}
</script>

<template>
    <Head title="terenuri.ro — Toate locațiile sportive din România">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />
        <HeroSection @share="shareLocation" />
        <PopularSports />
        <CityNumbers :location-shared="locationShared" @share="shareLocation" />
        <HowItWorks />
        <ClubCta />
        <SiteFooter />
    </div>
</template>
