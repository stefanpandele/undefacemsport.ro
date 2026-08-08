<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteFooter from '@/components/landing/SiteFooter.vue';
import SiteNav from '@/components/SiteNav.vue';
import { trackEvent } from '@/lib/gtm';
import organizationApplication from '@/routes/organization-application';

type PricingPlan = {
    key: string;
    name: string;
    price: number;
    tagline: string;
    sports: number | null;
    locations: number | null;
    galleryImages: number | null;
};

defineProps<{ plans: PricingPlan[] }>();

/** The middle tier is the one most clubs land on, so it carries the emphasis. */
const RECOMMENDED = 'pro';

function limitLabel(value: number | null, one: string, many: string): string {
    if (value === null) {
        return `${many} nelimitate`;
    }

    return `${value} ${value === 1 ? one : many}`;
}
</script>

<template>
    <Head title="Prețuri — Unde Facem Sport" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />

        <div class="mx-auto max-w-[1180px] px-5 pt-12 pb-8">
            <div class="text-center">
                <span
                    class="mb-3.5 block font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-grass-deep uppercase"
                >
                    Pentru organizații
                </span>
                <h1
                    class="font-archivo text-[clamp(28px,5.5vw,44px)] leading-[1.05] font-extrabold tracking-[-0.02em]"
                >
                    Cât costă să fii găsit?
                </h1>
                <p class="mx-auto mt-3.5 max-w-[52ch] text-[16px] text-sage">
                    Planurile diferă prin cât poți lista — câte sporturi și câte
                    locații. Nu prin cât de bine arăți: galeria, orarul,
                    antrenorii și poziția pe hartă sunt la fel pentru toți.
                </p>
            </div>

            <div
                class="mt-11 grid grid-cols-1 items-start gap-4 md:grid-cols-3"
            >
                <div
                    v-for="plan in plans"
                    :key="plan.key"
                    class="relative overflow-hidden rounded-[20px] border bg-white transition"
                    :class="
                        plan.key === RECOMMENDED
                            ? 'border-grass shadow-[0_30px_60px_-32px_rgba(11,20,16,0.4)] md:-translate-y-3'
                            : 'border-line'
                    "
                >
                    <div
                        v-if="plan.key === RECOMMENDED"
                        class="bg-grass py-2 text-center font-jetbrains text-[10.5px] font-bold tracking-[0.12em] text-white uppercase"
                    >
                        Cel mai ales
                    </div>

                    <div class="p-6">
                        <div class="font-archivo text-[20px] font-extrabold">
                            {{ plan.name }}
                        </div>
                        <p
                            class="mt-1.5 min-h-[40px] text-[13.5px] leading-snug text-sage"
                        >
                            {{ plan.tagline }}
                        </p>

                        <div class="mt-5 flex items-baseline gap-1.5">
                            <template v-if="plan.price === 0">
                                <span
                                    class="font-archivo text-[34px] leading-none font-extrabold text-grass-deep"
                                >
                                    Gratuit
                                </span>
                            </template>
                            <template v-else>
                                <span
                                    class="font-archivo text-[34px] leading-none font-extrabold"
                                >
                                    {{ plan.price }}
                                </span>
                                <span class="text-[14px] text-sage">
                                    lei / lună
                                </span>
                            </template>
                        </div>

                        <div class="mt-6 space-y-2.5 border-t border-line pt-5">
                            <div class="flex items-start gap-2.5">
                                <span class="text-[13px] text-grass-deep">
                                    ✓
                                </span>
                                <span class="text-[14px]">
                                    <b>{{
                                        limitLabel(
                                            plan.sports,
                                            'sport',
                                            'sporturi',
                                        )
                                    }}</b>
                                </span>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="text-[13px] text-grass-deep">
                                    ✓
                                </span>
                                <span class="text-[14px]">
                                    <b>{{
                                        limitLabel(
                                            plan.locations,
                                            'locație',
                                            'locații',
                                        )
                                    }}</b>
                                </span>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="text-[13px] text-grass-deep">
                                    ✓
                                </span>
                                <span class="text-[14px] text-sage">
                                    {{
                                        limitLabel(
                                            plan.galleryImages,
                                            'poză',
                                            'poze',
                                        )
                                    }}
                                    per sport
                                </span>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="text-[13px] text-grass-deep">
                                    ✓
                                </span>
                                <span class="text-[14px] text-sage">
                                    Orar, antrenori și contact
                                </span>
                            </div>
                        </div>

                        <Link
                            :href="organizationApplication.create.url()"
                            class="mt-6 inline-flex w-full items-center justify-center rounded-full px-[22px] py-3 text-[14.5px] font-semibold transition"
                            :class="
                                plan.key === RECOMMENDED
                                    ? 'bg-clay text-white hover:bg-[#e6501c]'
                                    : 'border-[1.5px] border-line text-ink hover:border-grass'
                            "
                            @click="
                                trackEvent('cta_click', {
                                    cta: 'add_organization',
                                    location: `pricing_${plan.key}`,
                                })
                            "
                        >
                            Începe cu {{ plan.name }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- The part a club actually worries about, said plainly. -->
        <div class="mx-auto max-w-[1180px] px-5 pb-16">
            <div
                class="grid grid-cols-1 gap-3.5 rounded-[20px] border border-line bg-white p-6 sm:grid-cols-3"
            >
                <div>
                    <div class="font-archivo text-[15px] font-extrabold">
                        Vizitatorii nu plătesc niciodată
                    </div>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-sage">
                        Căutarea, orarul și datele de contact ale organizațiilor
                        sunt gratuite pentru oricine caută unde să facă sport.
                    </p>
                </div>
                <div>
                    <div class="font-archivo text-[15px] font-extrabold">
                        Planul nu-ți schimbă locul în listă
                    </div>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-sage">
                        Nicio listă publică nu se ordonează după cât plătești.
                        Un club gratuit apare lângă unul plătit, la fel.
                    </p>
                </div>
                <div>
                    <div class="font-archivo text-[15px] font-extrabold">
                        Începi gratuit
                    </div>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-sage">
                        Te listezi fără să plătești nimic și treci pe un
                        plan mai mare doar când ai nevoie de mai mult spațiu.
                    </p>
                </div>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>
