<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AnafLookupController from '@/actions/App/Http/Controllers/AnafLookupController';
import { store } from '@/actions/App/Http/Controllers/ClubApplicationController';
import InputError from '@/components/InputError.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { home } from '@/routes';
import { trackEvent } from '@/lib/gtm';
import SiteNav from '@/components/landing/SiteNav.vue';
import SiteFooter from '@/components/landing/SiteFooter.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const page = usePage();
const turnstile = computed(() => page.props.turnstile);
const turnstileRef = ref<InstanceType<typeof TurnstileWidget> | null>(null);

type CompanyDetails = {
    company_name: string;
    address: string;
    registration_number: string;
    is_vat_payer: boolean;
};

const form = useForm({
    club_name: '',
    fiscal_code: '',
    contact_name: '',
    contact_phone: '',
    contact_email: '',
    company: null as CompanyDetails | null,
    turnstile_token: '',
});

const submitted = ref(false);

// ANAF lookup
const company = ref<CompanyDetails | null>(null);
const lookupFailed = ref(false);
const lookingUp = ref(false);

const canLookup = computed(
    () => form.fiscal_code.replace(/\D/g, '').length >= 6 && !lookingUp.value,
);

watch(
    () => form.fiscal_code,
    () => {
        lookupFailed.value = false;
    },
);

async function lookupCompany() {
    if (!canLookup.value) {
        return;
    }

    lookingUp.value = true;
    lookupFailed.value = false;
    company.value = null;
    form.clearErrors('fiscal_code');

    try {
        const response = await fetch(
            AnafLookupController.url({ query: { cui: form.fiscal_code } }),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            lookupFailed.value = true;

            return;
        }

        company.value = (await response.json()) as CompanyDetails;
    } catch {
        lookupFailed.value = true;
    } finally {
        lookingUp.value = false;
    }
}

function submit() {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            trackEvent('form_submit', { form: 'club_application' });
            submitted.value = true;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        onError: () => {
            // Turnstile tokens are single-use — force a fresh challenge.
            form.turnstile_token = '';
            turnstileRef.value?.reset();
        },
    });
}

const fieldClass =
    'w-full rounded-[10px] border border-line bg-[#f7f8f6] px-3.5 py-3 text-[14.5px] outline-none focus-visible:border-grass-deep focus-visible:ring-2 focus-visible:ring-grass/30';
const labelClass = 'mb-1.5 block text-[13px] font-semibold text-sage';
</script>

<template>
    <Head :title="t('club_application.meta.title')" />

    <div class="min-h-screen bg-paper font-inter text-ink antialiased">
        <SiteNav />
        <div class="mx-auto w-full max-w-2xl px-4 py-8">
            <!-- Form -->
            <div v-if="!submitted">
                <div class="px-2 pt-5 pb-7 text-center">
                    <span
                        class="font-jetbrains text-[11px] font-semibold tracking-[0.12em] text-grass-deep uppercase"
                    >
                        {{ t('club_application.form.eyebrow') }}
                    </span>
                    <h1
                        class="mt-2.5 mb-2 font-archivo text-[clamp(24px,4vw,32px)] font-extrabold tracking-[-0.02em]"
                    >
                        {{ t('club_application.form.heading') }}
                    </h1>
                    <p class="text-[14.5px] text-sage">
                        {{ t('club_application.form.subheading') }}
                    </p>
                </div>

                <form
                    class="rounded-[20px] border border-line bg-white p-7 sm:p-[34px]"
                    @submit.prevent="submit"
                >
                    <div class="mb-4.5">
                        <label for="club_name" :class="labelClass">
                            {{ t('club_application.form.club_name.label') }}
                        </label>
                        <input
                            id="club_name"
                            v-model="form.club_name"
                            type="text"
                            :class="fieldClass"
                            :placeholder="t('club_application.form.club_name.placeholder')"
                        />
                        <InputError class="mt-1.5" :message="form.errors.club_name" />
                    </div>

                    <!-- CUI + ANAF lookup -->
                    <div class="mb-4.5">
                        <label for="fiscal_code" :class="labelClass">{{ t('club_application.form.fiscal_code.label') }}</label>
                        <div class="flex items-start gap-2">
                            <input
                                id="fiscal_code"
                                v-model="form.fiscal_code"
                                type="text"
                                :class="fieldClass"
                                :placeholder="t('club_application.form.fiscal_code.placeholder')"
                                @keydown.enter.prevent="lookupCompany"
                                @change="lookupCompany"
                            />
                        </div>

                        <div
                            v-if="company"
                            class="mt-3.5 rounded-[14px] border-[1.5px] border-grass bg-[#eaf6ef] px-4 py-3.5"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-[13.5px] font-bold text-grass-deep"
                            >
                                ✓ {{ t('club_application.anaf.found') }}
                            </div>
                            <div class="flex justify-between gap-4 py-1 text-[12.5px]">
                                <span class="text-sage">{{ t('club_application.anaf.name') }}</span>
                                <span class="text-right">{{ company.company_name || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 py-1 text-[12.5px]">
                                <span class="shrink-0 text-sage">{{ t('club_application.anaf.address') }}</span>
                                <span class="text-right">{{ company.address || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 py-1 text-[12.5px]">
                                <span class="text-sage">{{ t('club_application.anaf.registration_number') }}</span>
                                <span class="text-right">{{ company.registration_number || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 py-1 text-[12.5px]">
                                <span class="text-sage">{{ t('club_application.anaf.vat_status') }}</span>
                                <span class="text-right">
                                    {{ company.is_vat_payer ? t('club_application.anaf.vat_payer') : t('club_application.anaf.vat_non_payer') }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="lookupFailed"
                            class="mt-2.5 rounded-xl border-[1.5px] border-clay bg-[#fff1eb] px-3.5 py-2.5 text-[12.5px] text-clay"
                        >
                            ⚠️ {{ t('club_application.anaf.not_found') }}
                        </div>

                        <InputError class="mt-1.5" :message="form.errors.fiscal_code" />
                    </div>

                    <div class="my-[22px] border-t border-dashed border-line" />

                    <div class="mb-4.5">
                        <label for="contact_name" :class="labelClass">
                            {{ t('club_application.form.contact_name.label') }}
                        </label>
                        <input
                            id="contact_name"
                            v-model="form.contact_name"
                            type="text"
                            :class="fieldClass"
                            :placeholder="t('club_application.form.contact_name.placeholder')"
                        />
                        <InputError class="mt-1.5" :message="form.errors.contact_name" />
                    </div>

                    <div class="mb-4.5">
                        <label for="contact_phone" :class="labelClass">{{ t('club_application.form.contact_phone.label') }}</label>
                        <input
                            id="contact_phone"
                            v-model="form.contact_phone"
                            type="tel"
                            :class="fieldClass"
                            :placeholder="t('club_application.form.contact_phone.placeholder')"
                        />
                        <InputError class="mt-1.5" :message="form.errors.contact_phone" />
                    </div>

                    <div>
                        <label for="contact_email" :class="labelClass">{{ t('club_application.form.contact_email.label') }}</label>
                        <input
                            id="contact_email"
                            v-model="form.contact_email"
                            type="email"
                            :class="fieldClass"
                            :placeholder="t('club_application.form.contact_email.placeholder')"
                        />
                        <InputError class="mt-1.5" :message="form.errors.contact_email" />
                    </div>

                    <div
                        class="mt-5 flex items-start gap-2.5 rounded-xl bg-[#f7f8f6] px-4 py-3.5 text-[12.5px] text-sage"
                    >
                        <span>ℹ️</span>
                        <span>{{ t('club_application.form.info') }}</span>
                    </div>

                    <div class="mt-5 flex items-center justify-center">
                        <TurnstileWidget
                            ref="turnstileRef"
                            v-model="form.turnstile_token"
                            :site-key="turnstile.siteKey"
                            :enabled="turnstile.enabled"
                        />
                        <InputError class="mt-1.5" :message="form.errors.turnstile_token" />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.turnstile_token"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-clay px-[22px] py-[13px] text-[14.5px] font-semibold text-white transition hover:bg-[#e6501c] disabled:cursor-not-allowed disabled:bg-[#e8b9a8] disabled:hover:bg-[#e8b9a8]"
                    >
                        {{ form.processing ? t('club_application.form.submitting') : t('club_application.form.submit') }}
                    </button>
                </form>
            </div>

<!--             Success-->
            <div v-else class="px-5 py-[50px] text-center">
                <div
                    class="mx-auto mb-5 flex h-[70px] w-[70px] items-center justify-center rounded-full bg-grass text-[32px] text-white"
                >
                    ✓
                </div>
                <h1
                    class="mb-2.5 font-archivo text-[26px] font-extrabold tracking-[-0.02em]"
                >
                    {{ t('club_application.success.heading') }}
                </h1>
                <p class="mx-auto mb-6 max-w-[40ch] text-[14.5px] text-sage">
                    {{ t('club_application.success.body') }}
                </p>
                <div
                    class="mb-6 inline-flex items-center gap-1.5 rounded-lg bg-[#fff7e6] px-3.5 py-[7px] font-jetbrains text-[11.5px] font-bold text-[#946200]"
                >
                    ⏳ {{ t('club_application.success.badge') }}
                </div>
                <div>
                    <Link
                        :href="home.url()"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-clay px-[22px] py-[13px] text-[14.5px] font-semibold text-white transition hover:bg-[#e6501c]"
                    >
                        {{ t('club_application.success.back') }}
                    </Link>
                </div>
            </div>
        </div>
        <SiteFooter />
    </div>
</template>
