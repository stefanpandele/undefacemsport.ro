<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import clubApplication from '@/routes/club-application';

</script>

<template>
    <nav class="sticky top-0 z-[60] border-b border-line bg-paper/90 backdrop-blur-md">
        <div class="mx-auto flex h-[60px] max-w-[1180px] items-center gap-6 px-5">
            <Link href="/" aria-label="Unde Facem Sport">
                <img
                    src="/images/unde-facem-sport-logo-2.svg"
                    alt="Unde Facem Sport"
                    class="h-8 w-auto"
                />
            </Link>
            <div class="ml-auto">
                <Link
                    :href="clubApplication.create.url()"
                    class="inline-flex items-center gap-2 rounded-full bg-clay px-5 py-2.5 text-[14.5px] font-semibold text-white transition hover:bg-[#e6501c]"
                >
                    Adaugă clubul
                </Link>
            </div>
        </div>
    </nav>
</template>
