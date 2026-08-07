<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { trackEvent } from '@/lib/gtm';
import { explore, login, pricing, register } from '@/routes';
import organizationApplication from '@/routes/organization-application';
import sportRoutes from '@/routes/sports';

type Menu = 'nav' | 'visitor' | 'club';

const openMenu = ref<Menu | null>(null);
const root = ref<HTMLElement | null>(null);

function toggle(menu: Menu) {
    openMenu.value = openMenu.value === menu ? null : menu;
}

function handleOutside(event: MouseEvent) {
    if (root.value && !root.value.contains(event.target as Node)) {
        openMenu.value = null;
    }
}

onMounted(() => document.addEventListener('click', handleOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleOutside));

// The main bar is the catalogue and nothing else. Pricing lives under Club,
// because a visitor looking for a hall never pays anything — it is the club
// that needs it, and it is a page you read before deciding, not a nav item.
const links = [
    { label: 'Explorează', href: explore.url(), icon: '🔎' },
    { label: 'Sporturi', href: sportRoutes.index.url(), icon: '🏅' },
];

const sectionLabel =
    'px-1 pb-2 font-jetbrains text-[10px] font-bold tracking-[0.14em] text-sage uppercase';
const sheetItem =
    'flex items-center gap-3 rounded-[12px] px-3 py-3 text-[15px] font-medium text-ink transition-colors hover:bg-[#f2f5ef] active:bg-[#eaf6ef]';
const sheetIcon =
    'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#f2f5ef] text-[15px]';

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
        <div ref="root" class="wrap flex h-16 items-center gap-7">
            <Link
                href="/"
                class="flex items-center"
                aria-label="Unde Facem Sport"
            >
                <img
                    src="/images/unde-facem-sport-logo-2.svg"
                    alt="Unde Facem Sport"
                    class="h-9 w-auto"
                />
            </Link>

            <!-- Catalogue on the left, accounts on the right. Listing a club
                 used to sit here too, pointing at the same page as Register
                 inside the Club menu — one destination, two links. -->
            <div class="hidden gap-[26px] min-[900px]:flex">
                <Link
                    v-for="link in links"
                    :key="link.label"
                    :href="link.href"
                    class="text-[14.5px] font-medium text-sage transition-colors hover:text-ink"
                >
                    {{ link.label }}
                </Link>
            </div>

            <div class="ml-auto flex items-center gap-3">
                <!-- Everything folds into one menu below 900px: three pill
                     buttons and a logo do not fit on a phone, and a bar that
                     wraps is worse than a bar with one control. -->
                <button
                    type="button"
                    aria-label="Meniu"
                    :aria-expanded="openMenu === 'nav'"
                    class="flex h-11 w-11 cursor-pointer flex-col items-center justify-center gap-[5px] rounded-full border-[1.5px] transition min-[900px]:hidden"
                    :class="
                        openMenu === 'nav'
                            ? 'border-grass bg-[#eaf6ef]'
                            : 'border-line hover:border-grass'
                    "
                    @click="toggle('nav')"
                >
                    <!-- The bars fold into an X, so the button says whether the
                         sheet is open without needing a second glance. -->
                    <span
                        class="block h-[1.5px] w-[18px] rounded bg-ink transition-transform duration-200"
                        :class="
                            openMenu === 'nav'
                                ? 'translate-y-[6.5px] rotate-45'
                                : ''
                        "
                    />
                    <span
                        class="block h-[1.5px] w-[18px] rounded bg-ink transition-opacity duration-200"
                        :class="openMenu === 'nav' ? 'opacity-0' : ''"
                    />
                    <span
                        class="block h-[1.5px] w-[18px] rounded bg-ink transition-transform duration-200"
                        :class="
                            openMenu === 'nav'
                                ? '-translate-y-[6.5px] -rotate-45'
                                : ''
                        "
                    />
                </button>

                <!-- VIZITATOR -->
                <div class="relative hidden min-[900px]:block">
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
                        <Link :href="register()" :class="menuItem"
                            >Register</Link
                        >
                    </div>
                </div>

                <!-- CLUB -->
                <div class="relative hidden min-[900px]:block">
                    <button
                        type="button"
                        :class="[
                            btnBase,
                            'bg-clay text-white hover:bg-[#e6501c]',
                        ]"
                        @click="toggle('club')"
                    >
                        Club
                    </button>
                    <div v-if="openMenu === 'club'" :class="menuPanel">
                        <!-- Club login = panou Filament, deci <a> normal (nu Inertia) -->
                        <a href="/cont/login" :class="menuItem">Login</a>
                        <!-- Club register = formularul nostru Inertia cu aprobare -->
                        <Link
                            :href="organizationApplication.create.url()"
                            :class="menuItem"
                            @click="
                                trackEvent('cta_click', {
                                    cta: 'add_club',
                                    location: 'nav_club_menu',
                                })
                            "
                        >
                            Register
                        </Link>
                        <!-- Separated: reading the price is not signing in. -->
                        <div class="my-1 border-t border-line" />
                        <Link :href="pricing.url()" :class="menuItem">
                            Prețuri
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile sheet: full width under the bar, so items are thumb-sized
             instead of crammed into a 172px dropdown. -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-3 opacity-0"
        >
            <div
                v-if="openMenu === 'nav'"
                class="absolute inset-x-0 top-full z-[70] border-b border-line bg-white shadow-[0_28px_50px_-24px_rgba(11,20,16,0.45)] min-[900px]:hidden"
            >
                <div class="wrap py-4">
                    <div :class="sectionLabel">Catalog</div>
                    <Link
                        v-for="link in links"
                        :key="link.label"
                        :href="link.href"
                        :class="sheetItem"
                    >
                        <span :class="sheetIcon">{{ link.icon }}</span>
                        {{ link.label }}
                    </Link>

                    <!-- Same two audiences as the desktop bar, in the same
                         order — a phone should not learn a different site. -->
                    <div class="mt-4 mb-2 border-t border-line" />
                    <div :class="sectionLabel">Vizitator</div>
                    <Link :href="login()" :class="sheetItem">
                        <span :class="sheetIcon">👤</span>
                        Login
                    </Link>
                    <Link :href="register()" :class="sheetItem">
                        <span :class="sheetIcon">✨</span>
                        Register
                    </Link>

                    <div class="mt-4 mb-2 border-t border-line" />
                    <div :class="sectionLabel">Club</div>
                    <!-- Club login = panou Filament, deci <a> normal -->
                    <a href="/cont/login" :class="sheetItem">
                        <span :class="sheetIcon">🔑</span>
                        Login
                    </a>
                    <Link
                        :href="organizationApplication.create.url()"
                        :class="sheetItem"
                        @click="
                            trackEvent('cta_click', {
                                cta: 'add_club',
                                location: 'nav_mobile',
                            })
                        "
                    >
                        <span :class="sheetIcon">➕</span>
                        Register
                    </Link>
                    <Link :href="pricing.url()" :class="sheetItem">
                        <span :class="sheetIcon">🏷️</span>
                        Prețuri
                    </Link>
                </div>
            </div>
        </Transition>

        <!-- Dimmed page behind, so the sheet reads as a layer and a tap
             anywhere closes it. -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="openMenu === 'nav'"
                class="fixed inset-0 top-16 z-[55] bg-ink/25 min-[900px]:hidden"
                @click="openMenu = null"
            />
        </Transition>
    </nav>
</template>
