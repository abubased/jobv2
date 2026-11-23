<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';

const submitted = ref(false);
const showStickyCta = ref(false);

const showRegistrationModal = ref(false);
const showPaymentModal = ref(false);
const showDuplicatePhoneModal = ref(false);
const duplicatePhoneMessage = ref('');

const selectedPaymentMethod = ref('');
const currentApplicationId = ref<number | null>(null);

const showPaymentFrame = ref(false);
const paymentFrameUrl = ref('');

const isPaymentLoading = ref(false);
const isSubmittingApplication = ref(false);

const paymentMethods = [
    {
        key: 'bkash',
        label: 'bKash',
        logo: '/images/bkash.jfif',
    },
    {
        key: 'nagad',
        label: 'Nagad',
        logo: '/images/Nagad.webp',
    },
    {
        key: 'rocket',
        label: 'Rocket',
        logo: '/images/Rocket.webp',
    },
    {
        key: 'upay',
        label: 'Upay',
        logo: '/images/Upay.png',
    },
];

const form = ref({
    name: '',
    address: '',
    phone: '',
    coinCount: '',
    coinYear: '',
});

const errors = ref<Record<string, string>>({});

const selectPaymentMethod = (method: string) => {
    selectedPaymentMethod.value = method;
};

const getCsrfToken = () => {
    const metaToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (metaToken) {
        return metaToken;
    }

    const cookieMatch = document.cookie.match(
        /(?:^|;\s*)XSRF-TOKEN=([^;]+)/
    );

    if (cookieMatch) {
        return decodeURIComponent(cookieMatch[1]);
    }

    return '';
};

const validateForm = () => {
    errors.value = {};

    if (!form.value.name.trim()) {
        errors.value.name = 'নাম লিখুন';
    }

    if (!form.value.address.trim()) {
        errors.value.address = 'সম্পূর্ণ ঠিকানা লিখুন';
    }

    if (!form.value.phone.trim()) {
        errors.value.phone = 'মোবাইল নাম্বার দিন';
    }

    if (!String(form.value.coinCount).trim()) {
        errors.value.coinCount = 'কয়েনের সংখ্যা লিখুন';
    }

    if (!String(form.value.coinYear).trim()) {
        errors.value.coinYear = 'কয়েনের সাল লিখুন';
    }

    return Object.keys(errors.value).length === 0;
};

const scrollToFirstError = () => {
    const fields = [
        'name',
        'address',
        'phone',
        'coinCount',
        'coinYear',
    ];

    for (const field of fields) {
        if (errors.value[field]) {
            const element = document.querySelector(
                `[data-error-field="${field}"]`
            ) as HTMLElement | null;

            if (element) {
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });

                element.focus();
            }

            break;
        }
    }
};

const handleFormSubmit = () => {
    if (isSubmittingApplication.value) {
        return;
    }

    if (!validateForm()) {
        scrollToFirstError();
        return;
    }

    submitForm();
};

const submitForm = async () => {
    if (isSubmittingApplication.value) {
        return;
    }

    isSubmittingApplication.value = true;

    /*
     * New Lalcoin form data.
     *
     * Backend should accept:
     * name
     * address
     * phone
     * coin_count
     * coin_year
     */
    const formData = {
        form_slug: 'lalcoin',
        name: form.value.name,
        address: form.value.address,
        phone: form.value.phone,
        form_data: {
            coin_count: form.value.coinCount,
            coin_year: form.value.coinYear,
        },
    };

    const csrfToken = getCsrfToken();

    try {
        const response = await fetch('/submit-form', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(formData),
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            const serverErrors = result.errors || {};
            const mappedErrors: Record<string, string> = {};

            Object.entries(serverErrors).forEach(([key, value]) => {
                const message = Array.isArray(value) ? String(value[0]) : String(value);
                const field = key.replace(/^form_data\./, '');

                if (field === 'coin_count') {
                    mappedErrors.coinCount = message;
                } else if (field === 'coin_year') {
                    mappedErrors.coinYear = message;
                } else {
                    mappedErrors[key] = message;
                }
            });

            errors.value = mappedErrors;

            if (Object.keys(mappedErrors).length > 0) {
                scrollToFirstError();
            } else {
                alert(
                    result.message ||
                        'ফরম সাবমিট করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।'
                );
            }

            return;
        }

        currentApplicationId.value = result.application_id ?? null;

        if (!currentApplicationId.value) {
            alert(result.message || 'আবেদন জমা হয়েছে, কিন্তু পেমেন্ট শুরু করা যায়নি।');
            return;
        }

        showRegistrationModal.value = false;

        /*
         * Same payment flow as the existing page.
         */
        showPaymentModal.value = true;
    } catch (error) {
        console.error('Submission error:', error);

        alert(
            'ফরম সাবমিট করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।'
        );
    } finally {
        isSubmittingApplication.value = false;
    }
};

const openPaymentFrame = async () => {
    if (
        isPaymentLoading.value ||
        !selectedPaymentMethod.value ||
        !currentApplicationId.value
    ) {
        return;
    }

    isPaymentLoading.value = true;

    try {
        const csrfToken = getCsrfToken();

        const response = await fetch('/init-payment', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                application_id: currentApplicationId.value,
                method: selectedPaymentMethod.value,
            }),
        });

        const result = await response.json();

        if (!response.ok || !result.success || !result.url || result.payment_status === 'paid') {
            alert(
                result.message ||
                    'পেমেন্ট এখনো নিশ্চিত হয়নি। আবেদন জমা আছে।'
            );

            return;
        }

        paymentFrameUrl.value = result.url;

        showPaymentModal.value = false;
        showPaymentFrame.value = true;
    } catch (error) {
        console.error('Payment init error:', error);

        alert(
            'পেমেন্ট শুরু করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।'
        );
    } finally {
        isPaymentLoading.value = false;
    }
};

const closePaymentFrame = () => {
    showPaymentFrame.value = false;
    paymentFrameUrl.value = '';
};

const handlePaymentModalMessage = (event: MessageEvent) => {
    if (
        event.origin !== window.location.origin ||
        event.data?.type !== 'close-payment-modal'
    ) {
        return;
    }

    closePaymentFrame();
};

const handleScroll = () => {
    const doc = document.documentElement;

    const scrollTop =
        window.scrollY || doc.scrollTop || 0;

    const scrollHeight =
        doc.scrollHeight - doc.clientHeight;

    if (scrollHeight <= 0) {
        showStickyCta.value = false;
        return;
    }

    const scrolledPercent =
        (scrollTop / scrollHeight) * 100;

    showStickyCta.value = scrolledPercent >= 5;
};

const resetForm = () => {
    form.value = {
        name: '',
        address: '',
        phone: '',
        coinCount: '',
        coinYear: '',
    };

    errors.value = {};

    submitted.value = false;
};

onMounted(() => {
    window.addEventListener(
        'scroll',
        handleScroll,
        { passive: true }
    );

    handleScroll();

    window.addEventListener(
        'message',
        handlePaymentModalMessage
    );
});

onBeforeUnmount(() => {
    window.removeEventListener(
        'scroll',
        handleScroll
    );

    window.removeEventListener(
        'message',
        handlePaymentModalMessage
    );
});
</script>

<template>
    <Head>
        <title>লাল কয়েন বিক্রির আবেদন ফরম</title>

        <meta
            name="description"
            content="লাল কয়েন বিক্রির জন্য অনলাইনে আবেদন করুন। সহজে আবেদন ফরম পূরণ করে আপনার আবেদন জমা দিন।"
        />

        <meta property="og:type" content="website" />
        <meta
            property="og:title"
            content="লাল কয়েন বিক্রির আবেদন ফরম"
        />
        <meta
            property="og:description"
            content="লাল কয়েন বিক্রির জন্য অনলাইনে আবেদন করুন। সহজে আবেদন ফরম পূরণ করে আপনার আবেদন জমা দিন।"
        />
        <meta
            property="og:url"
            content="https://application.submit-now.online/lalcoin"
        />
        <meta
            property="og:image"
            content="https://application.submit-now.online/images/lalcoin-share.jpg"
        />
        <meta
            property="og:image:alt"
            content="লাল কয়েন বিক্রির আবেদন ফরম"
        />

        <meta name="twitter:card" content="summary_large_image" />
        <meta
            name="twitter:title"
            content="লাল কয়েন বিক্রির আবেদন ফরম"
        />
        <meta
            name="twitter:description"
            content="লাল কয়েন বিক্রির জন্য অনলাইনে আবেদন করুন।"
        />
        <meta
            name="twitter:image"
            content="https://application.submit-now.online/images/lalcoin-share.jpg"
        />
    </Head>

    <div
        class="min-h-screen bg-gradient-to-b from-red-50 via-white to-orange-50 text-gray-800"
    >
        <!-- Header -->
        <header
            v-show="!showStickyCta"
            class="sticky top-0 z-30 border-b border-red-100 bg-white/95 px-4 py-3 shadow-sm backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-2xl items-center justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl border border-red-100 bg-red-50"
                    >
                        <img
                            src="/images/logo.png"
                            alt="Logo"
                            class="h-9 w-9 object-contain"
                        />
                    </div>

                    <div>
                        <p
                            class="text-sm font-bold text-red-700"
                        >
                            লাল কয়েন
                        </p>

                        <p
                            class="text-[11px] text-gray-500"
                        >
                            Coin Selling Form
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-600"
                >
                    বিক্রয় ফরম
                </div>
            </div>
        </header>

        <!-- Success -->
        <section
            v-if="submitted"
            class="px-4 pt-5"
        >
            <div
                class="mx-auto max-w-2xl rounded-3xl border border-green-200 bg-white p-7 text-center shadow-lg"
            >
                <div
                    class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-green-500"
                >
                    <svg
                        class="h-11 w-11 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="3"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </div>

                <h2
                    class="text-2xl font-bold text-gray-900"
                >
                    ফরম সফলভাবে জমা হয়েছে!
                </h2>

                <p
                    class="mt-3 text-sm leading-6 text-gray-600"
                >
                    আপনার দেওয়া তথ্য আমাদের কাছে পৌঁছেছে।
                    পরবর্তী প্রক্রিয়ার জন্য প্রয়োজনীয় নির্দেশনা
                    অনুসরণ করুন।
                </p>

                <button
                    type="button"
                    @click="resetForm"
                    class="mt-6 rounded-full bg-red-600 px-7 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-red-700"
                >
                    নতুন ফরম পূরণ করুন
                </button>
            </div>
        </section>

        <!-- Hero -->
        <section class="px-4 pb-4 pt-5">
            <div
                class="mx-auto max-w-2xl overflow-hidden rounded-3xl bg-gradient-to-br from-red-700 via-red-600 to-orange-500 p-6 text-white shadow-xl"
            >
                <div
                    class="flex items-center gap-3"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-3xl"
                    >
                        🪙
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-widest text-red-100"
                        >
                            Coin Selling
                        </p>

                        <h1
                            class="mt-1 text-2xl font-extrabold"
                        >
                            লাল কয়েন বিক্রয় ফরম
                        </h1>
                    </div>
                </div>

                <p
                    class="mt-5 text-sm leading-6 text-red-50"
                >
                    আপনার লাল কয়েন সম্পর্কিত তথ্য দিতে নিচের
                    ফরমটি সঠিকভাবে পূরণ করুন।
                </p>
            </div>
        </section>

        <!-- Main Form -->
        <main class="px-4 pb-28">
            <section class="mx-auto max-w-2xl">
                <div
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xl"
                >
                    <div class="mb-6">
                        <p
                            class="text-xs font-bold uppercase tracking-widest text-red-500"
                        >
                            Information
                        </p>

                        <h2
                            class="mt-1 text-xl font-bold text-gray-900"
                        >
                            লাল কয়েন বিক্রয় ফরম
                        </h2>

                        <p
                            class="mt-1 text-sm text-gray-500"
                        >
                            নিচের সব তথ্য সঠিকভাবে প্রদান করুন।
                        </p>
                    </div>

                    <form
                        @submit.prevent="handleFormSubmit"
                        class="space-y-5"
                    >
                        <!-- Name -->
                        <div>
                            <label
                                class="mb-2 block text-sm font-semibold text-gray-700"
                            >
                                নাম
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                v-model="form.name"
                                data-error-field="name"
                                type="text"
                                placeholder="আপনার নাম লিখুন"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-2 focus:ring-red-500/10"
                            />

                            <p
                                v-if="errors.name"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ errors.name }}
                            </p>
                        </div>

                        <!-- Address -->
                        <div>
                            <label
                                class="mb-2 block text-sm font-semibold text-gray-700"
                            >
                                সম্পূর্ণ ঠিকানা
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea
                                v-model="form.address"
                                data-error-field="address"
                                rows="3"
                                placeholder="গ্রাম/মহল্লা, থানা, জেলা সহ সম্পূর্ণ ঠিকানা লিখুন"
                                class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-2 focus:ring-red-500/10"
                            ></textarea>

                            <p
                                v-if="errors.address"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ errors.address }}
                            </p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label
                                class="mb-2 block text-sm font-semibold text-gray-700"
                            >
                                মোবাইল নাম্বার
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                v-model="form.phone"
                                data-error-field="phone"
                                type="tel"
                                inputmode="numeric"
                                placeholder="01XXXXXXXXX"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-2 focus:ring-red-500/10"
                            />

                            <p
                                v-if="errors.phone"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ errors.phone }}
                            </p>
                        </div>

                        <!-- Coin Count -->
                        <div>
                            <label
                                class="mb-2 block text-sm font-semibold text-gray-700"
                            >
                                কয়েনের সংখ্যা
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                v-model="form.coinCount"
                                data-error-field="coinCount"
                                type="text"
                                placeholder="যেমন: ১০০"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-2 focus:ring-red-500/10"
                            />

                            <p
                                v-if="errors.coinCount"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ errors.coinCount }}
                            </p>
                        </div>

                        <!-- Coin Year -->
                        <div>
                            <label
                                class="mb-2 block text-sm font-semibold text-gray-700"
                            >
                                কয়েনের সাল
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                v-model="form.coinYear"
                                data-error-field="coinYear"
                                type="text"
                                inputmode="numeric"
                                placeholder="যেমন: ১৯৭১"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-2 focus:ring-red-500/10"
                            />

                            <p
                                v-if="errors.coinYear"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ errors.coinYear }}
                            </p>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="isSubmittingApplication"
                            class="w-full rounded-2xl bg-gradient-to-r from-red-600 to-orange-500 px-5 py-4 text-base font-bold text-white shadow-lg transition active:scale-[0.99] hover:from-red-700 hover:to-orange-600 disabled:cursor-not-allowed disabled:bg-gray-300"
                        >
                            <span
                                v-if="isSubmittingApplication"
                                class="flex items-center justify-center gap-3"
                            >
                                <svg
                                    class="h-5 w-5 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />

                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                    />
                                </svg>

                                ফরম জমা হচ্ছে...
                            </span>

                            <span v-else>
                                ফরম জমা দিন
                            </span>
                        </button>
                    </form>
                </div>

                <!-- Notice -->
                <div
                    class="mt-4 rounded-2xl border border-orange-100 bg-orange-50 p-4"
                >
                    <p
                        class="text-sm font-semibold text-orange-700"
                    >
                        ⚠️ গুরুত্বপূর্ণ
                    </p>

                    <p
                        class="mt-1 text-xs leading-5 text-orange-800"
                    >
                        তথ্য দেওয়ার আগে নাম, মোবাইল নাম্বার,
                        ঠিকানা, কয়েনের সংখ্যা এবং কয়েনের সাল
                        ভালোভাবে যাচাই করে দিন।
                    </p>
                </div>
            </section>
        </main>

        <!-- Sticky CTA -->
        <div
            v-if="showStickyCta"
            class="fixed bottom-0 left-0 right-0 z-40 border-t border-gray-200 bg-white/95 p-3 shadow-2xl backdrop-blur"
        >
            <div class="mx-auto max-w-2xl">
                <button
                    type="button"
                    @click="showRegistrationModal = true"
                    class="w-full rounded-full bg-red-600 py-3 text-sm font-bold text-white shadow-lg hover:bg-red-700"
                >
                    🪙 লাল কয়েন বিক্রয় ফরম পূরণ করুন
                </button>
            </div>
        </div>

        <!-- Registration Modal -->
        <div
            v-if="showRegistrationModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
        >
            <div
                class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white p-5 shadow-2xl"
            >
                <div
                    class="mb-5 flex items-center justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-widest text-red-500"
                        >
                            Registration
                        </p>

                        <h3
                            class="text-xl font-bold text-gray-900"
                        >
                            লাল কয়েন বিক্রয় ফরম
                        </h3>
                    </div>

                    <button
                        type="button"
                        @click="showRegistrationModal = false"
                        class="rounded-full bg-gray-100 p-2 text-gray-500 hover:bg-gray-200"
                    >
                        ✕
                    </button>
                </div>

                <form
                    @submit.prevent="handleFormSubmit"
                    class="space-y-4"
                >
                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold"
                        >
                            নাম
                        </label>

                        <input
                            v-model="form.name"
                            data-error-field="name"
                            type="text"
                            placeholder="নাম লিখুন"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none focus:border-red-500"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold"
                        >
                            সম্পূর্ণ ঠিকানা
                        </label>

                        <textarea
                            v-model="form.address"
                            data-error-field="address"
                            rows="3"
                            placeholder="সম্পূর্ণ ঠিকানা লিখুন"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none focus:border-red-500"
                        ></textarea>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold"
                        >
                            মোবাইল নাম্বার
                        </label>

                        <input
                            v-model="form.phone"
                            data-error-field="phone"
                            type="tel"
                            placeholder="01XXXXXXXXX"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none focus:border-red-500"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold"
                        >
                            কয়েনের সংখ্যা
                        </label>

                        <input
                            v-model="form.coinCount"
                            data-error-field="coinCount"
                            type="text"
                            placeholder="কয়েনের সংখ্যা"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none focus:border-red-500"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold"
                        >
                            কয়েনের সাল
                        </label>

                        <input
                            v-model="form.coinYear"
                            data-error-field="coinYear"
                            type="text"
                            placeholder="যেমন: ১৯৭১"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none focus:border-red-500"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="isSubmittingApplication"
                        class="w-full rounded-xl bg-red-600 py-3 font-bold text-white hover:bg-red-700 disabled:bg-gray-300"
                    >
                        {{
                            isSubmittingApplication
                                ? 'জমা হচ্ছে...'
                                : 'ফরম জমা দিন'
                        }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Payment Method Modal -->
        <div
            v-if="showPaymentModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
        >
            <div
                class="w-full max-w-md rounded-3xl bg-white p-5 shadow-2xl"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-widest text-red-500"
                        >
                            Payment
                        </p>

                        <h3
                            class="text-xl font-bold text-gray-900"
                        >
                            পেমেন্ট পদ্ধতি নির্বাচন করুন
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            আবেদন জমা হয়েছে। ফি ৯৯.০০ টাকা। পেমেন্ট এখনো নিশ্চিত হয়নি।
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="showPaymentModal = false"
                        class="rounded-full bg-gray-100 p-2"
                    >
                        ✕
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button
                        v-for="method in paymentMethods"
                        :key="method.key"
                        type="button"
                        @click="
                            selectPaymentMethod(method.key)
                        "
                        class="flex items-center gap-3 rounded-2xl border-2 p-3 transition"
                        :class="
                            selectedPaymentMethod === method.key
                                ? 'border-red-500 bg-red-50'
                                : 'border-gray-100 bg-white hover:border-red-200'
                        "
                    >
                        <img
                            :src="method.logo"
                            :alt="method.label"
                            class="h-9 w-9 rounded-lg object-contain"
                        />

                        <span
                            class="text-sm font-semibold"
                        >
                            {{ method.label }}
                        </span>
                    </button>
                </div>

                <button
                    type="button"
                    :disabled="
                        !selectedPaymentMethod ||
                        isPaymentLoading
                    "
                    @click="openPaymentFrame"
                    class="mt-5 w-full rounded-2xl bg-red-600 py-3 font-bold text-white disabled:cursor-not-allowed disabled:bg-gray-300"
                >
                    {{
                        isPaymentLoading
                            ? 'পেমেন্ট প্রস্তুত হচ্ছে...'
                            : 'চালিয়ে যান'
                    }}
                </button>
            </div>
        </div>

        <!-- Payment Frame -->
        <div
            v-if="showPaymentFrame"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 px-4"
        >
            <div
                class="h-[90vh] w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b p-4"
                >
                    <h3
                        class="font-bold text-gray-900"
                    >
                        Payment
                    </h3>

                    <button
                        type="button"
                        @click="closePaymentFrame"
                        class="rounded-full bg-gray-100 px-3 py-2 text-sm"
                    >
                        ✕
                    </button>
                </div>

                <iframe
                    v-if="paymentFrameUrl"
                    :src="paymentFrameUrl"
                    title="Payment"
                    class="h-[calc(90vh-65px)] w-full border-0"
                ></iframe>
            </div>
        </div>

        <!-- Duplicate Phone Modal -->
        <div
            v-if="showDuplicatePhoneModal"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 px-4"
        >
            <div
                class="w-full max-w-md rounded-3xl bg-white p-6 text-center shadow-2xl"
            >
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 text-2xl"
                >
                    ⚠️
                </div>

                <h3
                    class="mt-4 text-xl font-bold text-gray-900"
                >
                    তথ্য আগে জমা দেওয়া হয়েছে
                </h3>

                <p
                    class="mt-3 text-sm leading-6 text-gray-600"
                >
                    {{
                        duplicatePhoneMessage ||
                        'এই মোবাইল নাম্বার দিয়ে আগে একটি ফরম জমা দেওয়া হয়েছে।'
                    }}
                </p>

                <button
                    type="button"
                    @click="
                        showDuplicatePhoneModal = false
                    "
                    class="mt-5 w-full rounded-xl bg-red-600 py-3 font-bold text-white"
                >
                    ঠিক আছে
                </button>
            </div>
        </div>
    </div>
</template>
