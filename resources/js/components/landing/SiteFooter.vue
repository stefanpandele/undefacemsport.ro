<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const columns = [
    {
        h: 'Platformă',
        links: [
            { label: 'Locații', href: '#' },
            { label: 'Sporturi', href: '#' },
            { label: 'Cluburi', href: '#' },
            { label: 'Hartă', href: '#' },
        ],
    },
    {
        h: 'Pentru cluburi',
        links: [
            { label: 'Adaugă club', href: '/inscriere-club' },
            { label: 'Prețuri', href: '#' },
            { label: 'Intră în cont', href: '/club/login' },
        ],
    },
    {
        h: 'Companie',
        links: [
            { label: 'Despre', href: '#' },
            { label: 'Contact', href: '#' },
            { label: 'Termeni', href: '#' },
        ],
    },
];
</script>

<template>
    <footer class="mt-5 border-t border-line px-0 pt-11 pb-6">
        <div class="wrap">
            <div class="grid gap-[26px] md:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div>
                    <Link
                        href="/"
                        class="flex items-center gap-2 font-archivo text-[19px] font-black"
                    >
                        <span class="h-[9px] w-[9px] rounded-[2px] bg-clay" />
                        terenuri<span class="text-sage">.ro</span>
                    </Link>
                    <p class="mt-3 max-w-[32ch] text-sm text-sage">
                        Găsești locația, vezi cluburile și orarele, suni direct.
                    </p>
                </div>

                <div v-for="col in columns" :key="col.h">
                    <h4 class="mb-2.5 text-[13px] font-semibold">{{ col.h }}</h4>
                    <a
                        v-for="link in col.links"
                        :key="link.label"
                        :href="link.href"
                        class="block py-1 text-sm text-sage transition-colors hover:text-ink"
                    >
                        {{ link.label }}
                    </a>
                </div>
            </div>

            <div
                class="mt-[30px] flex flex-col gap-2 border-t border-line pt-5 text-[13px] text-sage md:flex-row md:justify-between"
            >
                <span>© 2026 terenuri.ro · Toate drepturile rezervate.</span>
                <span class="font-jetbrains tracking-[0.06em]">FĂCUT ÎN ROMÂNIA</span>
            </div>
        </div>
    </footer>
</template>
