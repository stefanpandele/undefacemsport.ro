<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { login, register } from '@/routes';

import clubApplication from '@/routes/club-application';

const openMenu = ref<null | 'visitor' | 'club'>(null);
const root = ref<HTMLElement | null>(null);

function toggle(menu: 'visitor' | 'club') {
    openMenu.value = openMenu.value === menu ? null : menu;
}

function handleOutside(event: MouseEvent) {
    if (root.value && !root.value.contains(event.target as Node)) {
        openMenu.value = null;
    }
}

onMounted(() => document.addEventListener('click', handleOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleOutside));

const links = [
    { label: 'Explorează', href: '/explorare' },
    { label: 'Locații', href: '/explorare' },
    { label: 'Prețuri', href: '#' },
];

const menuItem =
    'block px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-[#f2f5ef]';
const menuPanel =
    'absolute right-0 top-[calc(100%+8px)] z-[70] min-w-[172px] overflow-hidden rounded-[14px] border border-line bg-white shadow-[0_20px_40px_-22px_rgba(11,20,16,0.4)]';
const btnBase =
    'inline-flex items-center justify-center gap-2 rounded-full px-[22px] py-3 text-[14.5px] font-semibold transition';
</script>

<template>
    <nav
        class="sticky top-0 z-[60] border-b border-line bg-paper/85 backdrop-blur-md"
    >
        <div class="wrap flex h-16 items-center gap-7">
            <Link href="/" class="flex items-center" aria-label="Unde Facem Sport">
                <img
                    src="/images/unde-facem-sport-logo-2.svg"
                    alt="Unde Facem Sport"
                    class="h-9 w-auto"
                />
            </Link>

            <div class="hidden gap-[26px] min-[900px]:flex">
                <a
                    v-for="link in links"
                    :key="link.label"
                    :href="link.href"
                    class="text-[14.5px] font-medium text-sage transition-colors hover:text-ink"
                >
                    {{ link.label }}
                </a>
                <Link
                    :href="clubApplication.create.url()"
                    class="text-[14.5px] font-medium text-sage transition-colors hover:text-ink"
                >
                    Adaugă club
                </Link>
            </div>

            <div ref="root" class="ml-auto flex items-center gap-3">
                <!-- VIZITATOR -->
                <div class="relative">
                    <button
                        type="button"
                        :class="[
                            btnBase,
                            'border-[1.5px] border-line bg-transparent text-ink hover:border-grass',
                        ]"
                        @click="toggle('visitor')"
                    >
                        Vizitator
                    </button>
                    <div v-if="openMenu === 'visitor'" :class="menuPanel">
                        <Link :href="login()" :class="menuItem">Login</Link>
                        <Link :href="register()" :class="menuItem">Register</Link>
                    </div>
                </div>

                <!-- CLUB -->
                <div class="relative">
                    <button
                        type="button"
                        :class="[btnBase, 'bg-clay text-white hover:bg-[#e6501c]']"
                        @click="toggle('club')"
                    >
                        Club
                    </button>
                    <div v-if="openMenu === 'club'" :class="menuPanel">
                        <!-- Club login = panou Filament, deci <a> normal (nu Inertia) -->
                        <a href="/club/login" :class="menuItem">Login</a>
                        <!-- Club register = formularul nostru Inertia cu aprobare -->
                        <Link :href="clubApplication.create.url()" :class="menuItem">Register</Link>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</template>
