<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import clubApplication from '@/routes/club-application';
import { trackEvent } from '@/lib/gtm';

const plans = [
    { pn: 'Basic', pp: 'Gratuit · listare + orar' },
    { pn: 'Premium', pp: '49 lei/lună · poziționare + badge' },
    { pn: 'Pro / Business', pp: '~50 €/lună · multi-locație + CRM' },
];
</script>

<template>
    <section class="py-13">
        <div class="wrap">
            <div
                class="grid items-center gap-[30px] overflow-hidden rounded-3xl bg-[linear-gradient(115deg,var(--color-grass-deep),#0a6342)] px-6 py-[34px] text-white min-[900px]:grid-cols-[1.2fr_1fr] min-[900px]:p-12"
            >
                <div>
                    <span
                        class="font-jetbrains text-[11px] font-semibold tracking-[0.16em] text-[#bdf4d8] uppercase"
                    >
                        Pentru cluburi & antrenori
                    </span>
                    <h2
                        class="my-3 font-archivo text-[clamp(26px,3.6vw,40px)] font-extrabold text-white"
                    >
                        Adaugă-ți locația și programul. Gratuit.
                    </h2>
                    <p class="max-w-[42ch] text-[15.5px] text-[#dcf3e7]">
                        Sute de oameni caută zilnic unde să facă sport în orașul
                        lor. Fii vizibil acolo unde te caută.
                    </p>
                    <div class="mt-[22px] flex flex-wrap gap-3">
                        <Link
                            :href="clubApplication.create.url()"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-[22px] py-3 text-[14.5px] font-semibold text-grass-deep transition hover:bg-[#f2f5ef]"
                            @click="trackEvent('cta_click', { cta: 'add_club', location: 'club_cta_section' })"
                        >
                            Adaugă clubul tău
                        </Link>
                        <a
                            href="#"
                            class="inline-flex items-center justify-center gap-2 rounded-full border-[1.5px] border-white/40 px-[22px] py-3 text-[14.5px] font-semibold text-white transition hover:border-white"
                        >
                            Vezi prețurile
                        </a>
                    </div>
                </div>

                <div>
                    <div
                        v-for="plan in plans"
                        :key="plan.pn"
                        class="mt-3 rounded-2xl border border-white/[0.18] bg-white/[0.08] p-[18px] first:mt-0"
                    >
                        <div class="font-archivo text-base font-extrabold">
                            {{ plan.pn }}
                        </div>
                        <div class="mt-0.5 font-jetbrains text-[12.5px] text-[#bdf4d8]">
                            {{ plan.pp }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
